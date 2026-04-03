<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\PayrollReport;
use App\Models\PayrollUserRate;
use App\Models\User;
use App\Services\CommissionCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Payroll')]
class Payroll extends Component
{
    public string $tab = 'closers';
    public int $weekOffset = 0;
    public array $userPayrollInputs = [];
    public string $payrollMessage = '';

    public function mount(): void
    {
        // Hide payroll from fronter_panama
        $user = auth()->user();
        if ($user?->role === 'fronter_panama') {
            $this->redirectRoute('dashboard');
            session()->flash('error', 'Payroll is not available for your role.');
        }
    }

    public function prevWeek(): void { $this->weekOffset--; }
    public function nextWeek(): void { $this->weekOffset++; }
    public function thisWeek(): void { $this->weekOffset = 0; }

    public function saveUserPayrollInfo(int $userId): void
    {
        $row = $this->userPayrollInputs[$userId] ?? [];
        $commPct = $this->norm($row['comm_pct'] ?? null);

        if ($commPct !== null && $commPct > 40) {
            $this->addError("userPayrollInputs.$userId.comm_pct", 'Max 40% for regular, 25% for Panama.');
            return;
        }

        try {
            DB::table('payroll_user_rates')->updateOrInsert(
                ['user_id' => (string) $userId],
                ['comm_pct' => $commPct, 'snr_pct' => $this->norm($row['snr_pct'] ?? null), 'hourly_rate' => $this->norm($row['hourly_rate'] ?? null), 'updated_at' => now()]
            );
            User::where('id', $userId)->update(['comm_pct' => $commPct]);
        } catch (\Throwable $e) {}

        $this->resetErrorBag("userPayrollInputs.$userId");
    }

    public function saveAllUserPayrollInfo(): void
    {
        foreach (array_keys($this->userPayrollInputs) as $uid) {
            $this->saveUserPayrollInfo((int) $uid);
        }
        $this->payrollMessage = 'All rates saved.';
    }

    public function generateWeeklyReport(): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin')) return;

        $monday = Carbon::now()->startOfWeek()->addWeeks($this->weekOffset);
        $sunday = $monday->copy()->addDays(6);
        $weekLabel = $monday->format('M j') . ' - ' . $sunday->format('M j, Y');

        // Get all charged deals for this week that aren't finalized
        $deals = Deal::where('charged', 'yes')
            ->where('payroll_finalized', false)
            ->get();

        // Calculate commissions for any that haven't been calculated
        foreach ($deals as $deal) {
            if ($deal->closer_comm_amount === null) {
                CommissionCalculator::calculate($deal);
            }
        }

        // Generate reports per user (closers, fronters, admins — NOT fronter_panama)
        $agents = User::whereIn('role', ['closer', 'fronter', 'admin', 'admin_limited', 'master_admin'])->get();

        foreach ($agents as $agent) {
            $field = match ($agent->role) { 'closer' => 'closer', 'fronter' => 'fronter', default => 'assigned_admin' };
            $agentDeals = $deals->where($field, $agent->id);
            if ($agentDeals->isEmpty()) continue;

            $details = $agentDeals->map(fn($d) => [
                'deal_id' => $d->id,
                'owner_name' => $d->owner_name,
                'fee' => (float) $d->fee,
                'is_vd' => $d->is_vd_deal || $d->was_vd === 'Yes',
                'fronter_role' => $d->fronter_role,
                'closer_comm' => (float) ($agent->role === 'closer' ? $d->closer_net_pay : 0),
                'fronter_comm' => (float) (in_array($agent->role, ['fronter']) ? $d->fronter_comm_amount : 0),
                'snr' => (float) ($agent->role === 'closer' ? $d->snr_deduction : 0),
                'vd' => (float) ($agent->role === 'closer' ? $d->vd_deduction : 0),
            ])->values()->toArray();

            $totalComm = $agent->role === 'closer'
                ? $agentDeals->sum('closer_net_pay')
                : ($agent->role === 'fronter' ? $agentDeals->sum('fronter_comm_amount') : 0);

            PayrollReport::updateOrCreate(
                ['user_id' => $agent->id, 'week_start' => $monday->toDateString()],
                [
                    'week_label' => $weekLabel,
                    'week_end' => $sunday->toDateString(),
                    'user_role' => $agent->role,
                    'total_deals_amount' => $agentDeals->sum('fee'),
                    'total_commission' => $totalComm,
                    'total_snr' => $agent->role === 'closer' ? $agentDeals->sum('snr_deduction') : 0,
                    'total_vd' => $agent->role === 'closer' ? $agentDeals->sum('vd_deduction') : 0,
                    'net_pay' => $totalComm,
                    'deal_count' => $agentDeals->count(),
                    'deal_details' => $details,
                    'status' => 'draft',
                    'generated_by' => $user->id,
                ]
            );
        }

        $this->payrollMessage = 'Weekly payroll reports generated for ' . $agents->count() . ' agents.';
    }

    public function finalizeWeeklyReport(): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin')) return;

        $monday = Carbon::now()->startOfWeek()->addWeeks($this->weekOffset);

        // Finalize reports
        PayrollReport::where('week_start', $monday->toDateString())
            ->where('status', 'draft')
            ->update(['status' => 'finalized', 'finalized_at' => now()]);

        // Mark deals as payroll finalized
        Deal::where('charged', 'yes')
            ->where('payroll_finalized', false)
            ->update(['payroll_finalized' => true, 'payroll_week' => $monday->format('Y-W')]);

        $this->payrollMessage = 'Payroll finalized. Totals reset for new week.';
    }

    private function norm(mixed $v): ?float
    {
        return ($v === '' || $v === null) ? null : (is_numeric($v) ? (float) $v : null);
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user || $user->role === 'fronter_panama') {
            return view('livewire.payroll', [
                'payCards' => collect(), 'weekLabel' => '', 'isMaster' => false,
                'editableUsers' => collect(), 'users' => collect(),
                'pastReports' => collect(), 'currentReports' => collect(),
            ]);
        }

        $isMaster = $user->hasRole('master_admin');
        $isAdmin = $user->hasRole('master_admin', 'admin');
        $monday = Carbon::now()->startOfWeek()->addWeeks($this->weekOffset);
        $sunday = $monday->copy()->addDays(6);
        $weekLabel = $monday->format('M j') . ' - ' . $sunday->format('M j, Y');

        // Get charged unfiled deals for live calculation
        try {
            $chargedDeals = Deal::where('charged', 'yes')->where('payroll_finalized', false)->get();
        } catch (\Throwable $e) {
            $chargedDeals = collect();
        }

        // Calculate commissions on the fly for any uncalculated deals
        foreach ($chargedDeals as $deal) {
            if ($deal->closer_comm_amount === null) {
                try { CommissionCalculator::calculate($deal); } catch (\Throwable $e) {}
            }
        }

        $users = User::all()->keyBy('id');

        // Build pay cards from real deal data
        $payCards = collect();
        $roleFilter = match ($this->tab) {
            'closers' => 'closer',
            'fronters' => ['fronter'],
            'admins' => ['admin', 'admin_limited', 'master_admin'],
            default => null,
        };

        if ($roleFilter && ($isMaster || $isAdmin)) {
            $roleUsers = is_array($roleFilter)
                ? User::whereIn('role', $roleFilter)->get()
                : User::where('role', $roleFilter)->get();

            foreach ($roleUsers as $ru) {
                $field = match ($ru->role) { 'closer' => 'closer', 'fronter' => 'fronter', default => 'assigned_admin' };
                $myDeals = $chargedDeals->where($field, $ru->id);

                $payCards->push([
                    'user_id' => $ru->id,
                    'gross_revenue' => $myDeals->sum('fee'),
                    'deals' => $myDeals->map(fn($d) => [
                        'owner_name' => $d->owner_name,
                        'fee' => (float) $d->fee,
                        'is_vd' => $d->is_vd_deal || $d->was_vd === 'Yes',
                        'fronter_role' => $d->fronter_role,
                        'closer_comm' => (float) $d->closer_net_pay,
                        'fronter_comm' => (float) $d->fronter_comm_amount,
                        'snr' => (float) $d->snr_deduction,
                        'vd' => (float) $d->vd_deduction,
                        'charged_back' => $d->charged_back,
                    ])->values()->toArray(),
                    'commission' => $ru->role === 'closer' ? $myDeals->sum('closer_net_pay') : ($ru->role === 'fronter' ? $myDeals->sum('fronter_comm_amount') : 0),
                    'total_snr' => $ru->role === 'closer' ? $myDeals->sum('snr_deduction') : 0,
                    'total_vd' => $ru->role === 'closer' ? $myDeals->sum('vd_deduction') : 0,
                    'final_pay' => $ru->role === 'closer' ? $myDeals->sum('closer_net_pay') : ($ru->role === 'fronter' ? $myDeals->sum('fronter_comm_amount') : 0),
                    'deal_count' => $myDeals->count(),
                ]);
            }
        } elseif (!$isMaster && !$isAdmin) {
            // Non-admin: show own pay card
            $field = match ($user->role) { 'closer' => 'closer', 'fronter' => 'fronter', default => 'assigned_admin' };
            $myDeals = $chargedDeals->where($field, $user->id);
            $payCards->push([
                'user_id' => $user->id,
                'gross_revenue' => $myDeals->sum('fee'),
                'deals' => $myDeals->map(fn($d) => ['owner_name' => $d->owner_name, 'fee' => (float) $d->fee, 'charged_back' => $d->charged_back])->values()->toArray(),
                'commission' => $user->role === 'closer' ? $myDeals->sum('closer_net_pay') : $myDeals->sum('fronter_comm_amount'),
                'final_pay' => $user->role === 'closer' ? $myDeals->sum('closer_net_pay') : $myDeals->sum('fronter_comm_amount'),
                'deal_count' => $myDeals->count(),
            ]);
        }

        // Editable users for rate inputs
        $editableUsers = collect();
        if ($isMaster) {
            $editableUsers = User::whereIn('role', ['closer', 'fronter', 'admin', 'admin_limited'])->orderBy('role')->orderBy('name')->get();
            if (empty($this->userPayrollInputs)) {
                try {
                    $ratesMap = DB::table('payroll_user_rates')->get()->keyBy(fn($r) => (int) $r->user_id);
                } catch (\Throwable $e) { $ratesMap = collect(); }
                foreach ($editableUsers as $eu) {
                    $cr = $ratesMap->get((int) $eu->id);
                    $this->userPayrollInputs[$eu->id] = [
                        'comm_pct' => $cr?->comm_pct ?? $eu->comm_pct ?? '',
                        'snr_pct' => $cr?->snr_pct ?? '',
                        'hourly_rate' => $cr?->hourly_rate ?? '',
                    ];
                }
            }
        }

        // Past reports
        try {
            $pastReports = PayrollReport::where('status', 'finalized')
                ->orderByDesc('week_start')
                ->limit(20)
                ->get();
            $currentReports = PayrollReport::where('week_start', $monday->toDateString())
                ->where('status', 'draft')
                ->get();
        } catch (\Throwable $e) {
            $pastReports = collect();
            $currentReports = collect();
        }

        return view('livewire.payroll', compact(
            'payCards', 'weekLabel', 'isMaster', 'editableUsers', 'users',
            'pastReports', 'currentReports', 'isAdmin'
        ));
    }
}
