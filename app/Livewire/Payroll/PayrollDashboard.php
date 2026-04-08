<?php

namespace App\Livewire\Payroll;

use App\Models\Deal;
use App\Models\DealFinancial;
use App\Models\PayrollBatchV2;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Payroll Dashboard')]
class PayrollDashboard extends Component
{
    public string $dateRange = 'week';

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin', 'admin')) abort(403);

        $cards = [
            'gross_week' => 0, 'gross_month' => 0,
            'payroll_week' => 0, 'payroll_month' => 0,
            'net_week' => 0, 'net_month' => 0,
            'cb_week' => 0, 'refund_week' => 0,
        ];
        $profitableDeals = collect();
        $repPayroll = collect();
        $batches = collect();
        $users = collect();

        try {
            $now = Carbon::now();
            $weekStart = $now->copy()->startOfWeek();
            $weekEnd = $now->copy()->endOfWeek();
            $monthStart = $now->copy()->startOfMonth();

            // Summary cards
            $weekFinancials = DealFinancial::whereBetween('calculated_at', [$weekStart, $weekEnd])->where('is_reversed', false);
            $monthFinancials = DealFinancial::where('calculated_at', '>=', $monthStart)->where('is_reversed', false);

            $cards = [
                'gross_week' => (float) (clone $weekFinancials)->sum(DB::raw('CASE WHEN collected_amount > 0 THEN collected_amount ELSE gross_amount END')),
                'gross_month' => (float) (clone $monthFinancials)->sum(DB::raw('CASE WHEN collected_amount > 0 THEN collected_amount ELSE gross_amount END')),
                'payroll_week' => (float) (clone $weekFinancials)->sum(DB::raw('fronter_commission + closer_commission + admin_commission')),
                'payroll_month' => (float) (clone $monthFinancials)->sum(DB::raw('fronter_commission + closer_commission + admin_commission')),
                'net_week' => (float) (clone $weekFinancials)->sum('company_net'),
                'net_month' => (float) (clone $monthFinancials)->sum('company_net'),
                'cb_week' => (float) Deal::whereBetween('payment_date', [$weekStart, $weekEnd])->where('is_chargeback', true)->sum('chargeback_amount'),
                'refund_week' => (float) Deal::whereBetween('payment_date', [$weekStart, $weekEnd])->where('is_refunded', true)->sum('refunded_amount'),
            ];

            // Profitable deals table
            $profitableDeals = DealFinancial::with('deal')
                ->where('is_reversed', false)
                ->orderByDesc('calculated_at')
                ->limit(50)
                ->get();

            // Rep payroll table
            $repPayroll = DB::table('deal_financials as df')
                ->join('deals as d', 'df.deal_id', '=', 'd.id')
                ->where('df.is_reversed', false)
                ->where('df.calculated_at', '>=', $weekStart)
                ->select(
                    'd.fronter_user_id',
                    'd.closer_user_id_payroll',
                    DB::raw('COUNT(*) as deal_count'),
                    DB::raw('SUM(CASE WHEN df.collected_amount > 0 THEN df.collected_amount ELSE df.gross_amount END) as gross_volume'),
                    DB::raw('SUM(df.fronter_commission) as total_fronter'),
                    DB::raw('SUM(df.closer_commission) as total_closer'),
                    DB::raw('SUM(df.admin_commission) as total_admin'),
                    DB::raw('SUM(df.company_net) as total_net')
                )
                ->groupBy('d.fronter_user_id', 'd.closer_user_id_payroll')
                ->get();

            $batches = PayrollBatchV2::orderByDesc('period_start')->limit(10)->get();
            $users = User::all()->keyBy('id');
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.payroll.dashboard', compact('cards', 'profitableDeals', 'repPayroll', 'batches', 'users'));
    }
}
