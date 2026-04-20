<?php

namespace App\Services\Finance;

use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\AdminPayroll;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates payroll costs for finance profit calculations.
 */
class PayrollCostService
{
    /**
     * Get total payroll cost for a date range.
     *
     * @param array{date_from?: string, date_to?: string, payroll_type?: string, office?: string} $filters
     */
    public static function getTotalPayroll(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        // Sales payroll from payroll_entries
        $salesPayroll = null;
        try {
            $salesQuery = DB::table('payroll_entries')
                ->join('payroll_runs', 'payroll_entries.run_id', '=', 'payroll_runs.id');

            if ($dateFrom) $salesQuery->where('payroll_runs.week_start', '>=', $dateFrom);
            if ($dateTo) $salesQuery->where('payroll_runs.week_end', '<=', $dateTo);

            $salesPayroll = $salesQuery->selectRaw("
                COALESCE(SUM(payroll_entries.commission_amount), 0) as commissions,
                COALESCE(SUM(payroll_entries.fronter_cut), 0) as fronter_pay,
                COALESCE(SUM(payroll_entries.hourly_pay), 0) as hourly_pay,
                COALESCE(SUM(payroll_entries.gross_pay), 0) as gross_pay,
                COALESCE(SUM(payroll_entries.final_pay), 0) as final_pay,
                COALESCE(SUM(payroll_entries.snr_amount), 0) as snr_total,
                COALESCE(SUM(payroll_entries.cb_total), 0) as cb_deductions
            ")->first();
        } catch (\Throwable $e) {
            // payroll_entries/payroll_runs tables may not exist yet
        }

        // Admin payroll
        $adminQuery = DB::table('admin_payroll');
        if ($dateFrom) $adminQuery->where('pay_date', '>=', $dateFrom);
        if ($dateTo) $adminQuery->where('pay_date', '<=', $dateTo);

        $adminTotal = 0;
        try {
            $adminTotal = $adminQuery->sum('amount') ?? 0;
        } catch (\Throwable $e) {
            // admin_payroll table may not exist
        }

        $salesTotal = (float) ($salesPayroll->final_pay ?? 0);
        $totalPayroll = $salesTotal + $adminTotal;

        return [
            'sales_payroll' => round($salesTotal, 2),
            'admin_payroll' => round((float) $adminTotal, 2),
            'commissions' => round((float) ($salesPayroll->commissions ?? 0), 2),
            'fronter_pay' => round((float) ($salesPayroll->fronter_pay ?? 0), 2),
            'hourly_pay' => round((float) ($salesPayroll->hourly_pay ?? 0), 2),
            'gross_pay' => round((float) ($salesPayroll->gross_pay ?? 0), 2),
            'cb_deductions' => round((float) ($salesPayroll->cb_deductions ?? 0), 2),
            'snr_total' => round((float) ($salesPayroll->snr_total ?? 0), 2),
            'total_payroll' => round($totalPayroll, 2),
        ];
    }

    /**
     * Get payroll cost for a specific month (converts YYYY-MM to date range).
     */
    public static function getMonthlyPayroll(string $month): array
    {
        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $end = \Carbon\Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        return self::getTotalPayroll([
            'date_from' => $start,
            'date_to' => $end,
        ]);
    }
}
