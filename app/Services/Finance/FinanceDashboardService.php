<?php

namespace App\Services\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantChargeback;
use App\Models\MerchantFinancialEntry;
use App\Models\MerchantStatementUpload;
use App\Models\MerchantTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Produces all data payloads for the Finance Command Center executive dashboard.
 */
class FinanceDashboardService
{
    public static function getFullDashboard(?int $midId = null, $from = null, $to = null): array
    {
        $summaryCards = [];
        $profitability = [];
        $midBreakdown = [];
        $chargebackSummary = [];
        $transactionTrends = [];
        $financialBurden = [];
        $importHealth = [];

        try { $summaryCards = self::getSummaryCards($midId, $from, $to); } catch (\Throwable $e) { report($e); }
        try { $profitability = self::getProfitabilitySummary($midId, $from, $to); } catch (\Throwable $e) { report($e); }
        try { $midBreakdown = self::getMidBreakdown($from, $to); } catch (\Throwable $e) { report($e); }
        try { $chargebackSummary = self::getChargebackSummary($midId, $from, $to); } catch (\Throwable $e) { report($e); }
        try { $transactionTrends = self::getTransactionTrends($midId, $from, $to); } catch (\Throwable $e) { report($e); }
        try { $financialBurden = self::getFinancialBurden($from, $to); } catch (\Throwable $e) { report($e); }
        try { $importHealth = self::getStatementImportHealth(); } catch (\Throwable $e) { report($e); }

        return [
            'summary_cards' => $summaryCards,
            'profitability' => $profitability,
            'mid_breakdown' => $midBreakdown,
            'chargeback_summary' => $chargebackSummary,
            'transaction_trends' => $transactionTrends,
            'financial_burden' => $financialBurden,
            'statement_import_health' => $importHealth,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'mid_filter' => $midId,
                'date_from' => $from,
                'date_to' => $to,
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // ROW 1 — EXECUTIVE SUMMARY CARDS
    // ═══════════════════════════════════════════════════════

    public static function getSummaryCards(?int $midId = null, $from = null, $to = null): array
    {
        $p = ProfitabilityCalculationService::calculate($midId, $from, $to);

        return [
            ['key' => 'gross_volume', 'label' => 'Gross Processing Volume', 'value' => $p['gross_volume'], 'format' => 'currency', 'color' => 'blue'],
            ['key' => 'approved_txn', 'label' => 'Approved Transactions', 'value' => $p['approved_count'], 'format' => 'number', 'color' => 'emerald'],
            ['key' => 'declined_txn', 'label' => 'Declined Transactions', 'value' => $p['declined_count'], 'format' => 'number', 'color' => 'red'],
            ['key' => 'refunds', 'label' => 'Total Refunds', 'value' => $p['refunds'], 'format' => 'currency', 'color' => 'amber'],
            ['key' => 'chargebacks', 'label' => 'Total Chargebacks', 'value' => $p['chargebacks'], 'format' => 'currency', 'color' => 'red'],
            ['key' => 'fees', 'label' => 'Total Fees', 'value' => $p['fees'], 'format' => 'currency', 'color' => 'orange'],
            ['key' => 'reserves', 'label' => 'Total Reserve Holds', 'value' => $p['reserve_holds'], 'format' => 'currency', 'color' => 'purple'],
            ['key' => 'payouts', 'label' => 'Total Payouts / Deposits', 'value' => $p['payouts'], 'format' => 'currency', 'color' => 'green'],
            ['key' => 'net_result', 'label' => 'Estimated Net Result', 'value' => $p['net_result'], 'format' => 'currency', 'color' => $p['net_result'] >= 0 ? 'emerald' : 'red'],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // ROW 2 — PROFITABILITY SUMMARY
    // ═══════════════════════════════════════════════════════

    public static function getProfitabilitySummary(?int $midId = null, $from = null, $to = null): array
    {
        $overall = ProfitabilityCalculationService::calculateOverall($from, $to);
        $byMid = ProfitabilityCalculationService::calculateByMid($from, $to);

        return [
            'overall' => $overall,
            'by_mid' => $byMid,
        ];
    }

    // ═══════════════════════════════════════════════════════
    // ROW 3 — MID BREAKDOWN TABLE
    // ═══════════════════════════════════════════════════════

    public static function getMidBreakdown($from = null, $to = null): array
    {
        return ProfitabilityCalculationService::calculateByMid($from, $to);
    }

    // ═══════════════════════════════════════════════════════
    // ROW 4 — CHARGEBACK COMMAND CENTER
    // ═══════════════════════════════════════════════════════

    public static function getChargebackSummary(?int $midId = null, $from = null, $to = null): array
    {
        try {
            $base = MerchantChargeback::query();
            if ($midId) $base->where('merchant_account_id', $midId);

        $openCount = (clone $base)->open()->count();
        $dueSoon = (clone $base)->dueSoon()->count();
        $overdueCount = (clone $base)->overdue()->count();
        $wonCount = (clone $base)->won()->count();
        $lostCount = (clone $base)->lost()->count();
        $totalValue = (clone $base)->when($from, fn($q) => $q->where('opened_at', '>=', $from))->when($to, fn($q) => $q->where('opened_at', '<=', $to))->sum('amount');

        // Value by MID
        $valueByMid = MerchantChargeback::select('merchant_account_id')
            ->selectRaw('SUM(amount) as total, COUNT(*) as cnt')
            ->when($midId, fn($q) => $q->where('merchant_account_id', $midId))
            ->when($from, fn($q) => $q->where('opened_at', '>=', $from))
            ->when($to, fn($q) => $q->where('opened_at', '<=', $to))
            ->groupBy('merchant_account_id')
            ->get()
            ->map(function ($row) {
                $mid = MerchantAccount::find($row->merchant_account_id);
                return [
                    'mid_id' => $row->merchant_account_id,
                    'mid_name' => $mid?->account_name ?? 'Unknown',
                    'mid_number' => $mid?->mid_number ?? '--',
                    'total' => (float) $row->total,
                    'count' => (int) $row->cnt,
                ];
            })->toArray();

        // Reason code trends
        $reasonCodes = MerchantChargeback::select('reason_code')
            ->selectRaw('COUNT(*) as cnt, SUM(amount) as total')
            ->when($midId, fn($q) => $q->where('merchant_account_id', $midId))
            ->when($from, fn($q) => $q->where('opened_at', '>=', $from))
            ->when($to, fn($q) => $q->where('opened_at', '<=', $to))
            ->whereNotNull('reason_code')
            ->groupBy('reason_code')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get()
            ->toArray();

        // Status breakdown
        $statusBreakdown = MerchantChargeback::select('internal_status')
            ->selectRaw('COUNT(*) as cnt, SUM(amount) as total')
            ->when($midId, fn($q) => $q->where('merchant_account_id', $midId))
            ->groupBy('internal_status')
            ->get()
            ->toArray();

        return [
            'open' => $openCount,
            'due_soon' => $dueSoon,
            'overdue' => $overdueCount,
            'won' => $wonCount,
            'lost' => $lostCount,
            'total_value' => (float) $totalValue,
            'value_by_mid' => $valueByMid,
            'reason_codes' => $reasonCodes,
            'status_breakdown' => $statusBreakdown,
        ];
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════
    // ROW 5 — TRANSACTION & VOLUME TRENDS
    // ═══════════════════════════════════════════════════════

    public static function getTransactionTrends(?int $midId = null, $from = null, $to = null): array
    {
        $from = $from ?? now()->subDays(30)->toDateString();
        $to = $to ?? now()->toDateString();

        $dailyVolume = MerchantTransaction::select(DB::raw('transaction_date as day'))
            ->selectRaw("SUM(CASE WHEN transaction_status IN ('approved','settled') THEN amount ELSE 0 END) as approved_volume")
            ->selectRaw("SUM(CASE WHEN transaction_status = 'declined' THEN amount ELSE 0 END) as declined_volume")
            ->selectRaw("SUM(CASE WHEN transaction_status IN ('refunded','partial_refund') THEN amount ELSE 0 END) as refund_volume")
            ->selectRaw("COUNT(*) as txn_count")
            ->selectRaw("SUM(CASE WHEN transaction_status IN ('approved','settled') THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN transaction_status = 'declined' THEN 1 ELSE 0 END) as declined_count")
            ->when($midId, fn($q) => $q->where('merchant_account_id', $midId))
            ->whereBetween('transaction_date', [$from, $to])
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->get()
            ->toArray();

        $dailyFees = MerchantFinancialEntry::select(DB::raw('entry_date as day'))
            ->selectRaw("SUM(CASE WHEN entry_type = 'fee' THEN amount ELSE 0 END) as fee_total")
            ->selectRaw("SUM(CASE WHEN entry_type IN ('payout','deposit') THEN amount ELSE 0 END) as payout_total")
            ->when($midId, fn($q) => $q->where('merchant_account_id', $midId))
            ->whereBetween('entry_date', [$from, $to])
            ->groupBy('entry_date')
            ->orderBy('entry_date')
            ->get()
            ->toArray();

        return [
            'daily_volume' => $dailyVolume,
            'daily_fees' => $dailyFees,
        ];
    }

    // ═══════════════════════════════════════════════════════
    // ROW 6 — FINANCIAL BURDEN / RISK
    // ═══════════════════════════════════════════════════════

    public static function getFinancialBurden($from = null, $to = null): array
    {
        $mids = MerchantAccount::active()->get();
        $rows = [];

        foreach ($mids as $mid) {
            $fees = (float) MerchantFinancialEntry::fees()->byMid($mid->id)->inRange($from, $to)->sum('amount');
            $reserves = (float) MerchantFinancialEntry::reserveHolds()->byMid($mid->id)->inRange($from, $to)->sum('amount');
            $cbAmount = (float) MerchantChargeback::byMid($mid->id)->when($from, fn($q) => $q->where('opened_at', '>=', $from))->when($to, fn($q) => $q->where('opened_at', '<=', $to))->sum('amount');
            $approvedVol = (float) MerchantTransaction::approved()->byMid($mid->id)->inRange($from, $to)->sum('amount');
            $cbRate = $approvedVol > 0 ? round($cbAmount / $approvedVol * 100, 2) : 0;
            $totalBurden = abs($fees) + abs($reserves) + abs($cbAmount);

            $rows[] = [
                'mid_id' => $mid->id,
                'account_name' => $mid->account_name,
                'mid_number' => $mid->mid_number,
                'processor' => $mid->processor_name,
                'fees' => abs($fees),
                'reserves' => abs($reserves),
                'chargebacks' => abs($cbAmount),
                'chargeback_rate' => $cbRate,
                'total_burden' => $totalBurden,
                'risk_level' => $cbRate >= 1.5 ? 'high' : ($cbRate >= 0.75 ? 'medium' : 'low'),
            ];
        }

        usort($rows, fn($a, $b) => $b['total_burden'] <=> $a['total_burden']);
        return $rows;
    }

    // ═══════════════════════════════════════════════════════
    // ROW 7 — STATEMENT IMPORT HEALTH
    // ═══════════════════════════════════════════════════════

    public static function getStatementImportHealth(): array
    {
        try {
            $recent = MerchantStatementUpload::orderByDesc('uploaded_at')
                ->limit(10)
                ->get()
                ->map(function ($u) {
                    $mid = $u->merchantAccount;
                    $summary = $u->summary;
                    $batch = $u->importBatches()->latest()->first();
                    $reviewCount = 0;
                    try { $reviewCount = $u->lineItems()->where('needs_review', true)->count(); } catch (\Throwable) {}

                    return [
                        'id' => $u->id,
                        'filename' => $u->original_filename,
                        'mid_name' => $mid?->account_name ?? 'Unassigned',
                        'processor' => $u->detected_processor ?? $mid?->processor_name ?? '--',
                        'status' => $u->processing_status,
                        'confidence' => $u->confidence_score,
                        'period' => $summary ? ($summary->statement_start_date?->format('n/j') . ' - ' . $summary->statement_end_date?->format('n/j/Y')) : '--',
                        'imported_rows' => $batch?->imported_rows ?? 0,
                        'failed_rows' => $batch?->failed_rows ?? 0,
                        'duplicate_rows' => $batch?->duplicate_rows ?? 0,
                        'review_count' => $reviewCount,
                        'uploaded_at' => $u->uploaded_at?->format('n/j/Y g:ia'),
                    ];
                })->toArray();

            $totalPending = MerchantStatementUpload::where('processing_status', 'pending')->count();
            $totalReview = 0;
            try { $totalReview = \App\Models\MerchantStatementLineItem::where('needs_review', true)->count(); } catch (\Throwable) {}

            return [
                'recent' => $recent,
                'pending_count' => $totalPending,
                'review_queue_count' => $totalReview,
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['recent' => [], 'pending_count' => 0, 'review_queue_count' => 0];
        }
    }
}
