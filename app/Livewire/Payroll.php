<?php
namespace App\Livewire;

use App\Models\Deal;
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
    public string $tab = 'closers';
    public bool $showRates = false;
    public int $weekOffset = 0;

    public function render()
    {
        $user = auth()->user();
        $isMaster = $user->hasRole('master_admin');
        $monday = Carbon::now()->startOfWeek()->addWeeks($this->weekOffset);
        $friday = $monday->copy()->addDays(4);
        $weekLabel = $monday->format('M j') . ' - ' . $friday->format('M j, Y');

        // Rates
        $settings = DB::table('payroll_settings')->first();
        $rates = [
            'closerPct' => $settings->closer_pct ?? 50, 'fronterPct' => $settings->fronter_pct ?? 10,
            'snrPct' => $settings->snr_pct ?? 2, 'vdPct' => $settings->vd_pct ?? 3,
            'adminSnrPct' => $settings->admin_snr_pct ?? 2, 'hourlyRate' => $settings->hourly_rate ?? 19.50,
        ];

        $charged = Deal::where('charged', 'yes')->where(fn($q) => $q->where('charged_back', '!=', 'yes')->orWhereNull('charged_back'))->get();
        $cbDeals = Deal::where('charged_back', 'yes')->get();
        $vdMult = 1 - ($rates['vdPct'] / 100);

        $buildCard = function(User $u) use ($charged, $cbDeals, $rates, $vdMult) {
            $field = match($u->role) { 'closer' => 'closer', 'fronter' => 'fronter', default => 'assigned_admin' };
            $myDeals = $charged->where($field, $u->id);
            $myCB = $cbDeals->where($field, $u->id);
            $commPct = ($u->comm_pct ?? ($u->role === 'closer' ? $rates['closerPct'] : ($u->role === 'fronter' ? $rates['fronterPct'] : $rates['adminSnrPct']))) / 100;
            $totalSold = $myDeals->sum('fee');
            $totalPayout = $myDeals->sum(fn($d) => $d->was_vd === 'Yes' ? $d->fee * $vdMult : $d->fee);
            $commission = $totalPayout * $commPct;
            $cbTotal = $myCB->sum('fee');
            $netPay = $commission - ($cbTotal * $commPct);
            return (object) compact('u', 'myDeals', 'totalSold', 'totalPayout', 'commission', 'commPct', 'cbTotal', 'netPay') + ['dealCount' => $myDeals->count(), 'cbCount' => $myCB->count()];
        };

        $payCards = collect();
        if (!$isMaster) {
            $payCards = collect([$buildCard($user)]);
        } else {
            $roleFilter = match($this->tab) { 'closers' => 'closer', 'fronters' => 'fronter', 'admins' => ['admin', 'admin_limited'], default => null };
            if ($roleFilter) {
                $roleUsers = is_array($roleFilter) ? User::whereIn('role', $roleFilter)->get() : User::where('role', $roleFilter)->get();
                $payCards = $roleUsers->map($buildCard);
            }
        }

        $sentSheets = DB::table('payroll_sent_history')->get()->keyBy('user_id');

        return view('livewire.payroll', compact('payCards', 'rates', 'weekLabel', 'sentSheets', 'isMaster'));
    }
}
