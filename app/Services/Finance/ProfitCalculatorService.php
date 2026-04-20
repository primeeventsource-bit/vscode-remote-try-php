<?php

namespace App\Services\Finance;

use App\Models\MerchantStatement;
use App\Models\MerchantAccount;
use App\Models\MerchantProfitSnapshot;
use App\Models\FinanceManualExpense;
use App\Models\FinanceAdjustment;
use App\Models\LiveChargeback;

/**
 * True net profit calculator with full waterfall breakdown.
 */
class ProfitCalculatorService
{
    /**
     * Calculate true net profit for a merchant account + month.
     *
     * @return array Full waterfall breakdown
     */
    public static function calculate(int $merchantAccountId, string $month): array
    {
        $merchant = MerchantAccount::findOrFail($merchantAccountId);
        $methodology = $merchant->profit_methodology ?? 'net_deposit'; // net_deposit or gross_minus_fees

        // Statement data
        $stmt = MerchantStatement::completed()
            ->where('merchant_account_id', $merchantAccountId)
            ->where('statement_month', $month)
            ->first();

        $grossSales = (float) ($stmt->gross_sales ?? 0);
        $credits = (float) ($stmt->credits ?? 0);
        $netSales = (float) ($stmt->net_sales ?? 0);
        $netDeposits = (float) ($stmt->total_deposits ?? 0);
        $discountFees = (float) ($stmt->discount_paid ?? 0);
        $otherFees = (float) ($stmt->fees_paid ?? 0);
        $totalProcessorFees = $discountFees + $otherFees;
        $totalChargebacks = (float) ($stmt->total_chargebacks ?? 0);
        $totalReversals = (float) ($stmt->total_reversals ?? 0);
        $netCbLoss = $totalChargebacks - $totalReversals;
        $reserveBalance = (float) ($stmt->reserve_ending_balance ?? 0);

        // Dispute fees from statement_fees
        $disputeFees = 0;
        if ($stmt) {
            $disputeFees = $stmt->fees()->where('fee_category', 'dispute')->sum('fee_total');
        }

        // Live chargeback losses (lost/open status)
        $liveCbLoss = LiveChargeback::where('merchant_account_id', $merchantAccountId)
            ->whereIn('status', ['lost', 'open'])
            ->whereMonth('created_at', \Carbon\Carbon::createFromFormat('Y-m', $month)->month)
            ->whereYear('created_at', \Carbon\Carbon::createFromFormat('Y-m', $month)->year)
            ->sum('amount');

        // Payroll
        $payroll = ['total_payroll' => 0, 'sales_payroll' => 0, 'admin_payroll' => 0, 'commissions' => 0, 'fronter_pay' => 0, 'hourly_pay' => 0, 'gross_pay' => 0, 'cb_deductions' => 0, 'snr_total' => 0];
        try {
            $payroll = PayrollCostService::getMonthlyPayroll($month);
        } catch (\Throwable $e) {
            // payroll tables may not exist
        }
        $payrollCost = $payroll['total_payroll'];

        // Operating expenses
        $dateStart = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $dateEnd = \Carbon\Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $opExpenses = FinanceManualExpense::where(function ($q) use ($merchantAccountId) {
                $q->where('merchant_account_id', $merchantAccountId)->orWhereNull('merchant_account_id');
            })
            ->whereBetween('expense_date', [$dateStart, $dateEnd])
            ->sum('amount');

        // Manual adjustments
        $adjustments = FinanceAdjustment::where(function ($q) use ($merchantAccountId) {
                $q->where('merchant_account_id', $merchantAccountId)->orWhereNull('merchant_account_id');
            })
            ->where('adjustment_month', $month)
            ->sum('amount');

        // TRUE PROFIT CALCULATION
        // Mode A: Net deposits already have discount deducted
        // Mode B: Explicitly subtract fees from gross
        if ($methodology === 'gross_minus_fees') {
            $processorAdjustedSales = $netSales - $totalProcessorFees;
        } else {
            // net_deposit mode — fees already deducted in deposit amounts
            $processorAdjustedSales = $netDeposits;
        }

        $riskAdjustedRevenue = $processorAdjustedSales - $netCbLoss - $disputeFees;
        $trueNetProfit = $riskAdjustedRevenue - $payrollCost - $opExpenses + $adjustments;
        $profitMarginPct = $grossSales > 0 ? round(($trueNetProfit / $grossSales) * 100, 4) : 0;
        $cbRatioPct = $grossSales > 0 ? round(($totalChargebacks / $grossSales) * 100, 4) : 0;
        $feeToVolumePct = $grossSales > 0 ? round(($totalProcessorFees / $grossSales) * 100, 4) : 0;
        $payrollBurdenPct = $grossSales > 0 ? round(($payrollCost / $grossSales) * 100, 4) : 0;
        $depositEfficiencyPct = $grossSales > 0 ? round(($netDeposits / $grossSales) * 100, 4) : 0;

        $healthScore = 0;
        try {
            $healthScore = FinanceMetricsService::calculateMidHealthScore($merchantAccountId, $month);
        } catch (\Throwable $e) {}

        $waterfall = [
            ['label' => 'Gross Sales', 'amount' => round($grossSales, 2), 'type' => 'total'],
            ['label' => 'Credits/Refunds', 'amount' => round(-$credits, 2), 'type' => 'subtract'],
            ['label' => 'Net Sales', 'amount' => round($netSales, 2), 'type' => 'subtotal'],
            ['label' => 'Discount Fees', 'amount' => round(-$discountFees, 2), 'type' => 'subtract'],
            ['label' => 'Other Processor Fees', 'amount' => round(-$otherFees, 2), 'type' => 'subtract'],
            ['label' => 'Processor Adjusted Sales', 'amount' => round($processorAdjustedSales, 2), 'type' => 'subtotal'],
            ['label' => 'Net Chargeback Loss', 'amount' => round(-$netCbLoss, 2), 'type' => 'subtract'],
            ['label' => 'Dispute Fees', 'amount' => round(-$disputeFees, 2), 'type' => 'subtract'],
            ['label' => 'Risk Adjusted Revenue', 'amount' => round($riskAdjustedRevenue, 2), 'type' => 'subtotal'],
            ['label' => 'Payroll Cost', 'amount' => round(-$payrollCost, 2), 'type' => 'subtract'],
            ['label' => 'Operating Expenses', 'amount' => round(-$opExpenses, 2), 'type' => 'subtract'],
            ['label' => 'Adjustments', 'amount' => round($adjustments, 2), 'type' => $adjustments >= 0 ? 'add' : 'subtract'],
            ['label' => 'True Net Profit', 'amount' => round($trueNetProfit, 2), 'type' => 'final'],
        ];

        return [
            'merchant_account_id' => $merchantAccountId,
            'month' => $month,
            'methodology' => $methodology,
            'gross_sales' => round($grossSales, 2),
            'credits' => round($credits, 2),
            'net_sales' => round($netSales, 2),
            'net_deposits' => round($netDeposits, 2),
            'discount_fees' => round($discountFees, 2),
            'other_processor_fees' => round($otherFees, 2),
            'total_processor_fees' => round($totalProcessorFees, 2),
            'total_chargebacks' => round($totalChargebacks, 2),
            'total_reversals' => round($totalReversals, 2),
            'net_chargeback_loss' => round($netCbLoss, 2),
            'live_chargeback_loss' => round($liveCbLoss, 2),
            'dispute_fees' => round($disputeFees, 2),
            'payroll_cost' => round($payrollCost, 2),
            'operating_expenses' => round($opExpenses, 2),
            'adjustments' => round($adjustments, 2),
            'true_net_profit' => round($trueNetProfit, 2),
            'profit_margin_pct' => $profitMarginPct,
            'chargeback_ratio_pct' => $cbRatioPct,
            'fee_to_volume_ratio_pct' => $feeToVolumePct,
            'payroll_burden_pct' => $payrollBurdenPct,
            'deposit_efficiency_pct' => $depositEfficiencyPct,
            'reserve_balance' => round($reserveBalance, 2),
            'mid_health_score' => $healthScore,
            'waterfall' => $waterfall,
            'payroll_detail' => $payroll,
        ];
    }

    /**
     * Snapshot profit data into merchant_profit_snapshots for historical tracking.
     */
    public static function snapshot(int $merchantAccountId, string $month): MerchantProfitSnapshot
    {
        $data = self::calculate($merchantAccountId, $month);

        return MerchantProfitSnapshot::updateOrCreate(
            [
                'merchant_account_id' => $merchantAccountId,
                'snapshot_month' => $month,
            ],
            [
                'gross_sales' => $data['gross_sales'],
                'credits' => $data['credits'],
                'net_sales' => $data['net_sales'],
                'net_deposits' => $data['net_deposits'],
                'discount_fees' => $data['discount_fees'],
                'other_processor_fees' => $data['other_processor_fees'],
                'total_chargebacks' => $data['total_chargebacks'],
                'total_reversals' => $data['total_reversals'],
                'net_chargeback_loss' => $data['net_chargeback_loss'],
                'dispute_fees' => $data['dispute_fees'],
                'payroll_cost' => $data['payroll_cost'],
                'operating_expenses' => $data['operating_expenses'],
                'adjustments' => $data['adjustments'],
                'true_net_profit' => $data['true_net_profit'],
                'profit_margin_pct' => $data['profit_margin_pct'],
                'chargeback_ratio_pct' => $data['chargeback_ratio_pct'],
                'fee_to_volume_ratio_pct' => $data['fee_to_volume_ratio_pct'],
                'reserve_balance' => $data['reserve_balance'],
                'mid_health_score' => $data['mid_health_score'],
                'waterfall_json' => $data['waterfall'],
            ]
        );
    }

    /**
     * Calculate profit for ALL active MIDs for a given month.
     */
    public static function calculateAll(string $month): array
    {
        $results = [];
        $merchants = MerchantAccount::active()->get();

        foreach ($merchants as $merchant) {
            try {
                $hasStatement = MerchantStatement::completed()
                    ->where('merchant_account_id', $merchant->id)
                    ->where('statement_month', $month)
                    ->exists();

                if ($hasStatement) {
                    $results[$merchant->id] = self::calculate($merchant->id, $month);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("ProfitCalculator failed for merchant {$merchant->id}: " . $e->getMessage());
            }
        }

        return $results;
    }
}
