<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\PayrollUserRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Payroll')]
class Payroll extends Component
{
    public string $tab = 'inputs';
    public bool $showRates = false;
    public int $weekOffset = 0;
    public array $userPayrollInputs = [];

    public function saveUserPayrollInfo(int $userId): void
    {
        $row = $this->userPayrollInputs[$userId] ?? [];

        $commPct = $this->normalizeNullableNumber($row['comm_pct'] ?? null);
        $snrPct = $this->normalizeNullableNumber($row['snr_pct'] ?? null);
        $hourlyRate = $this->normalizeNullableNumber($row['hourly_rate'] ?? null);

        if ($commPct !== null && ($commPct < 0 || $commPct > 100)) {
            $this->addError("userPayrollInputs.$userId.comm_pct", 'Commission % must be between 0 and 100.');
            return;
        }

        if ($snrPct !== null && ($snrPct < 0 || $snrPct > 100)) {
            $this->addError("userPayrollInputs.$userId.snr_pct", 'SNR % must be between 0 and 100.');
            return;
        }

        if ($hourlyRate !== null && $hourlyRate < 0) {
            $this->addError("userPayrollInputs.$userId.hourly_rate", 'Hourly rate cannot be negative.');
            return;
        }

        try {
            DB::table('payroll_user_rates')->updateOrInsert(
                ['user_id' => (string) $userId],
                [
                    'comm_pct' => $commPct,
                    'snr_pct' => $snrPct,
                    'hourly_rate' => $hourlyRate,
                    'updated_at' => now(),
                ]
            );

            User::where('id', $userId)->update(['comm_pct' => $commPct]);
        } catch (\Throwable $e) {}

        $this->resetErrorBag("userPayrollInputs.$userId");
    }

    public function saveAllUserPayrollInfo(): void
    {
        foreach (array_keys($this->userPayrollInputs) as $userId) {
            $this->saveUserPayrollInfo((int) $userId);
        }
    }

    private function normalizeNullableNumber(mixed $value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user) {
            return view('livewire.payroll', [
                'payCards' => collect(), 'rates' => [], 'weekLabel' => '',
                'sentSheets' => collect(), 'isMaster' => false, 'editableUsers' => collect(),
            ]);
        }

        $isMaster = $user->hasRole('master_admin');
        $monday = Carbon::now()->startOfWeek()->addWeeks($this->weekOffset);
        $friday = $monday->copy()->addDays(4);
        $weekLabel = $monday->format('M j') . ' - ' . $friday->format('M j, Y');

        // Rates — safe if table is empty or missing
        try {
            $settings = DB::table('payroll_settings')->first();
        } catch (\Throwable $e) {
            $settings = null;
        }
        $rates = [
            'closerPct' => (float) ($settings?->closer_pct ?? 50),
            'fronterPct' => (float) ($settings?->fronter_pct ?? 10),
            'snrPct' => (float) ($settings?->snr_pct ?? 2),
            'vdPct' => (float) ($settings?->vd_pct ?? 3),
            'adminSnrPct' => (float) ($settings?->admin_snr_pct ?? 2),
            'hourlyRate' => (float) ($settings?->hourly_rate ?? 19.50),
        ];

        try {
            $charged = Deal::where('charged', 'yes')
                ->where(fn($q) => $q->where('charged_back', '!=', 'yes')->orWhereNull('charged_back'))
                ->get();
            $cbDeals = Deal::where('charged_back', 'yes')->get();
        } catch (\Throwable $e) {
            $charged = collect();
            $cbDeals = collect();
        }

        $vdMult = 1 - ($rates['vdPct'] / 100);

        try {
            $userRatesMap = PayrollUserRate::query()->get()->keyBy(fn($r) => (int) $r->user_id);
        } catch (\Throwable $e) {
            $userRatesMap = collect();
        }

        if ($isMaster) {
            $editableUsers = User::whereIn('role', ['closer', 'fronter', 'admin', 'admin_limited'])
                ->orderBy('role')
                ->orderBy('name')
                ->get();

            if (empty($this->userPayrollInputs)) {
                foreach ($editableUsers as $editableUser) {
                    $customRate = $userRatesMap->get((int) $editableUser->id);
                    $this->userPayrollInputs[$editableUser->id] = [
                        'comm_pct' => $customRate?->comm_pct ?? $editableUser->comm_pct ?? '',
                        'snr_pct' => $customRate?->snr_pct ?? '',
                        'hourly_rate' => $customRate?->hourly_rate ?? '',
                    ];
                }
            }
        } else {
            $editableUsers = collect();
        }

        $buildCard = function (User $u) use ($charged, $cbDeals, $rates, $vdMult, $userRatesMap) {
            $field = match ($u->role) {
                'closer' => 'closer',
                'fronter' => 'fronter',
                default => 'assigned_admin',
            };
            $myDeals = $charged->where($field, $u->id);
            $myCB = $cbDeals->where($field, $u->id);
            $customRate = $userRatesMap->get((int) $u->id);

            $defaultPct = match ($u->role) {
                'closer' => $rates['closerPct'],
                'fronter' => $rates['fronterPct'],
                default => $rates['adminSnrPct'],
            };
            $effectiveCommPct = (float) ($customRate?->comm_pct ?? $u->comm_pct ?? $defaultPct);
            $commPct = $effectiveCommPct / 100;

            $totalSold = $myDeals->sum('fee');
            $totalPayout = $myDeals->sum(fn($d) => ($d->was_vd === 'Yes') ? $d->fee * $vdMult : $d->fee);
            $commission = $totalPayout * $commPct;
            $cbTotal = $myCB->sum('fee');
            $netPay = $commission - ($cbTotal * $commPct);

            return (object) [
                'u' => $u,
                'myDeals' => $myDeals,
                'totalSold' => $totalSold,
                'totalPayout' => $totalPayout,
                'commission' => $commission,
                'commPct' => $commPct,
                'cbTotal' => $cbTotal,
                'netPay' => $netPay,
                'dealCount' => $myDeals->count(),
                'cbCount' => $myCB->count(),
            ];
        };

        $payCards = collect();
        if (!$isMaster) {
            try {
                $payCards = collect([$buildCard($user)]);
            } catch (\Throwable $e) {
                $payCards = collect();
            }
        } else {
            $roleFilter = match ($this->tab) {
                'closers' => 'closer',
                'fronters' => 'fronter',
                'admins' => ['admin', 'admin_limited'],
                default => null,
            };
            if ($roleFilter) {
                $roleUsers = is_array($roleFilter)
                    ? User::whereIn('role', $roleFilter)->get()
                    : User::where('role', $roleFilter)->get();
                $payCards = $roleUsers->map($buildCard);
            }
        }

        try {
            $sentSheets = DB::table('payroll_sent_history')->get()->keyBy('user_id');
        } catch (\Throwable $e) {
            $sentSheets = collect();
        }

        return view('livewire.payroll', compact('payCards', 'rates', 'weekLabel', 'sentSheets', 'isMaster', 'editableUsers'));
    }
}
