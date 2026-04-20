<?php

namespace App\Services\Finance;

use App\Models\MerchantStatement;
use App\Models\MerchantAccount;
use App\Models\LiveChargeback;
use App\Models\StatementDeposit;
use App\Models\StatementChargeback;
use App\Models\StatementFee;
use Illuminate\Support\Facades\DB;

/**
 * Provides aggregated finance metrics for the dashboard.
 */
class FinanceMetricsService
{
    /**
     * Get all top-level KPI metrics for a set of filters.
     *
     * @param array{merchant_account_id?: int|null, month?: string|null, date_from?: string|null, date_to?: string|null} $filters
     */
    public static function getKpis(array $filters = []): array
    {
        $defaults = [
            'gross_volume' => 0, 'credits' => 0, 'net_sales' => 0, 'net_deposits' => 0,
            'discount_fees' => 0, 'other_fees' => 0, 'total_fees' => 0,
            'total_chargebacks' => 0, 'total_reversals' => 0, 'net_chargeback_loss' => 0,
            'live_chargeback_exposure' => 0, 'chargeback_exposure' => 0, 'reserve_balance' => 0,
            'chargeback_ratio_pct' => 0, 'recovery_rate_pct' => 0, 'statement_count' => 0,
        ];

        try {
            $query = MerchantStatement::completed();

            if (!empty($filters['merchant_account_id'])) {
                $query->where('merchant_account_id', $filters['merchant_account_id']);
            }
            if (!empty($filters['month'])) {
                $query->where('statement_month', $filters['month']);
            }
            if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
                $query->whereBetween('statement_month', [$filters['date_from'], $filters['date_to']]);
            }

            $statements = $query->get();

            $grossVolume = $statements->sum('gross_sales');
            $credits = $statements->sum('credits');
            $netSales = $statements->sum('net_sales');
            $netDeposits = $statements->sum('total_deposits');
            $discountPaid = $statements->sum('discount_paid');
            $feesPaid = $statements->sum('fees_paid');
            $totalFees = $discountPaid + $feesPaid;
            $totalChargebacks = $statements->sum('total_chargebacks');
            $totalReversals = $statements->sum('total_reversals');
            $netCbLoss = $totalChargebacks - $totalReversals;
            $reserveBalance = $statements->max('reserve_ending_balance') ?? 0;

            // Live chargebacks (unsettled current month)
            $liveOpen = 0;
            try {
                $liveQuery = LiveChargeback::query();
                if (!empty($filters['merchant_account_id'])) {
                    $liveQuery->where('merchant_account_id', $filters['merchant_account_id']);
                }
                $liveOpen = $liveQuery->open()->sum('amount');
            } catch (\Throwable $e) {}

            $cbRatio = $grossVolume > 0 ? round(($totalChargebacks / $grossVolume) * 100, 4) : 0;
            $recoveryRate = $totalChargebacks > 0 ? round(($totalReversals / $totalChargebacks) * 100, 2) : 0;

            return [
                'gross_volume' => round($grossVolume, 2),
                'credits' => round($credits, 2),
                'net_sales' => round($netSales, 2),
                'net_deposits' => round($netDeposits, 2),
                'discount_fees' => round($discountPaid, 2),
                'other_fees' => round($feesPaid, 2),
                'total_fees' => round($totalFees, 2),
                'total_chargebacks' => round($totalChargebacks, 2),
                'total_reversals' => round($totalReversals, 2),
                'net_chargeback_loss' => round($netCbLoss, 2),
                'live_chargeback_exposure' => round($liveOpen, 2),
                'chargeback_exposure' => round($netCbLoss + $liveOpen, 2),
                'reserve_balance' => round($reserveBalance, 2),
                'chargeback_ratio_pct' => $cbRatio,
                'recovery_rate_pct' => $recoveryRate,
                'statement_count' => $statements->count(),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Finance KPIs query failed: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * Get daily deposit trend data for charting.
     */
    public static function getDailyDeposits(array $filters = []): array
    {
        try {
            $query = StatementDeposit::query()
                ->join('merchant_statements', 'statement_deposits.statement_id', '=', 'merchant_statements.id')
                ->where('merchant_statements.ai_parse_status', 'completed');

            if (!empty($filters['merchant_account_id'])) {
                $query->where('merchant_statements.merchant_account_id', $filters['merchant_account_id']);
            }
            if (!empty($filters['month'])) {
                $query->where('merchant_statements.statement_month', $filters['month']);
            }

            return $query
                ->selectRaw('statement_deposits.deposit_date, SUM(statement_deposits.net_deposit) as total')
                ->whereNotNull('statement_deposits.deposit_date')
                ->groupBy('statement_deposits.deposit_date')
                ->orderBy('statement_deposits.deposit_date')
                ->pluck('total', 'deposit_date')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get card brand breakdown for charting.
     */
    public static function getCardBreakdown(array $filters = []): array
    {
        try {
            $query = DB::table('statement_plan_summaries')
                ->join('merchant_statements', 'statement_plan_summaries.statement_id', '=', 'merchant_statements.id')
                ->where('merchant_statements.ai_parse_status', 'completed');

            if (!empty($filters['merchant_account_id'])) {
                $query->where('merchant_statements.merchant_account_id', $filters['merchant_account_id']);
            }
            if (!empty($filters['month'])) {
                $query->where('merchant_statements.statement_month', $filters['month']);
            }

            return $query
                ->selectRaw('statement_plan_summaries.card_brand, SUM(statement_plan_summaries.sales_amount) as total')
                ->groupBy('statement_plan_summaries.card_brand')
                ->orderByDesc('total')
                ->pluck('total', 'card_brand')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get fee composition breakdown for charting.
     */
    public static function getFeeBreakdown(array $filters = []): array
    {
        try {
            $query = StatementFee::query()
                ->join('merchant_statements', 'statement_fees.statement_id', '=', 'merchant_statements.id')
                ->where('merchant_statements.ai_parse_status', 'completed');

            if (!empty($filters['merchant_account_id'])) {
                $query->where('merchant_statements.merchant_account_id', $filters['merchant_account_id']);
            }
            if (!empty($filters['month'])) {
                $query->where('merchant_statements.statement_month', $filters['month']);
            }

            return $query
                ->selectRaw('statement_fees.fee_category, SUM(statement_fees.fee_total) as total')
                ->groupBy('statement_fees.fee_category')
                ->orderByDesc('total')
                ->pluck('total', 'fee_category')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * MID Health Score calculation.
     *
     * Score 0-100 based on:
     * - Chargeback ratio (40 pts)
     * - Fee burden (20 pts)
     * - Reserve trend (15 pts)
     * - Deposit consistency (15 pts)
     * - Refund activity (10 pts)
     */
    public static function calculateMidHealthScore(int $merchantAccountId, ?string $month = null): int
    {
        try {
            $query = MerchantStatement::completed()->where('merchant_account_id', $merchantAccountId);
            if ($month) $query->where('statement_month', $month);

            $stmt = $query->latest('statement_month')->first();
            if (!$stmt) return 0;

            $score = 100;
            $grossSales = (float) ($stmt->gross_sales ?? 0);
            $totalChargebacks = (float) ($stmt->total_chargebacks ?? 0);

            // 1. Chargeback ratio (max -40)
            $cbRatio = $grossSales > 0 ? ($totalChargebacks / $grossSales) * 100 : 0;
            if ($cbRatio > 2.0) $score -= 40;
            elseif ($cbRatio > 1.5) $score -= 30;
            elseif ($cbRatio > 1.0) $score -= 20;
            elseif ($cbRatio > 0.75) $score -= 10;
            elseif ($cbRatio > 0.5) $score -= 5;

            // 2. Fee-to-volume ratio (max -20)
            $totalFees = (float) ($stmt->discount_paid ?? 0) + (float) ($stmt->fees_paid ?? 0);
            $feeRatio = $grossSales > 0 ? ($totalFees / $grossSales) * 100 : 0;
            if ($feeRatio > 8) $score -= 20;
            elseif ($feeRatio > 6) $score -= 15;
            elseif ($feeRatio > 4) $score -= 10;
            elseif ($feeRatio > 3) $score -= 5;

            // 3. Reserve growth concern (max -15)
            $prevStmt = MerchantStatement::completed()
                ->where('merchant_account_id', $merchantAccountId)
                ->where('statement_month', '<', $stmt->statement_month)
                ->latest('statement_month')
                ->first();
            if ($prevStmt && (float) ($stmt->reserve_ending_balance ?? 0) > (float) ($prevStmt->reserve_ending_balance ?? 0) * 1.2) {
                $score -= 15;
            }

            // 4. Deposit consistency (max -15)
            try {
                $depositStddev = $stmt->deposits()->selectRaw('COALESCE(STDDEV(net_deposit), 0) as sd')->value('sd');
                $depositAvg = $stmt->deposits()->avg('net_deposit') ?: 1;
                $cv = $depositAvg != 0 ? ($depositStddev / abs($depositAvg)) * 100 : 0;
                if ($cv > 100) $score -= 15;
                elseif ($cv > 50) $score -= 10;
                elseif ($cv > 25) $score -= 5;
            } catch (\Throwable $e) {
                // STDDEV may not be supported on all DB drivers
            }

            // 5. Refund rate (max -10)
            $refundRate = $grossSales > 0 ? ((float) ($stmt->credits ?? 0) / $grossSales) * 100 : 0;
            if ($refundRate > 10) $score -= 10;
            elseif ($refundRate > 5) $score -= 5;

            return max(0, min(100, $score));
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
