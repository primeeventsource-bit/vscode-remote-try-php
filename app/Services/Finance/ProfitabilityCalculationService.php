<?php

namespace App\Services\Finance;

use App\Models\FinanceSetting;
use App\Models\MerchantAccount;
use App\Models\MerchantChargeback;
use App\Models\MerchantFinancialEntry;
use App\Models\MerchantTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Profitability formula:
 *   Gross Volume
 *   - Refunds
 *   - Chargebacks
 *   - Fees
 *   - Reserve Holds (configurable)
 *   + Reserve Releases (configurable)
 *   + Adjustments (configurable)
 *   = Estimated Net Result
 */
class ProfitabilityCalculationService
{
    public static function calculate(?int $midId = null, $from = null, $to = null): array
    {
        $defaults = [
            'gross_volume' => 0, 'declined_volume' => 0, 'refunds' => 0,
            'chargebacks' => 0, 'fees' => 0, 'reserve_holds' => 0,
            'reserve_releases' => 0, 'payouts' => 0, 'adjustments' => 0,
            'net_result' => 0, 'transaction_count' => 0, 'approved_count' => 0,
            'declined_count' => 0, 'chargeback_count' => 0, 'chargeback_rate' => 0,
        ];

        try {
            $settings = self::getSettings();

            $grossVolume = self::sumTransactions($midId, $from, $to, ['approved', 'settled']);
            $declinedVolume = self::sumTransactions($midId, $from, $to, ['declined']);
            $refunds = abs(self::sumTransactions($midId, $from, $to, ['refunded', 'partial_refund']));
            $chargebacks = abs(self::sumChargebacks($midId, $from, $to));
            $fees = abs(self::sumEntries($midId, $from, $to, 'fee'));
            $reserveHolds = abs(self::sumEntries($midId, $from, $to, 'reserve_hold'));
            $reserveReleases = abs(self::sumEntries($midId, $from, $to, 'reserve_release'));
            $payouts = abs(self::sumEntries($midId, $from, $to, 'payout') + self::sumEntries($midId, $from, $to, 'deposit'));
            $adjustments = self::sumEntries($midId, $from, $to, 'adjustment');

            $netResult = $grossVolume - $refunds - $chargebacks - $fees;

            if ($settings['include_reserve_holds']) $netResult -= $reserveHolds;
            if ($settings['include_reserve_releases']) $netResult += $reserveReleases;
            if ($settings['include_adjustments']) $netResult += $adjustments;

            $txnCount = self::countTransactions($midId, $from, $to);
            $approvedCount = self::countTransactions($midId, $from, $to, ['approved', 'settled']);
            $declinedCount = self::countTransactions($midId, $from, $to, ['declined']);
            $cbCount = self::countChargebacks($midId, $from, $to);
            $cbRate = $approvedCount > 0 ? round($cbCount / $approvedCount * 100, 2) : 0;

            return [
                'gross_volume' => $grossVolume,
                'declined_volume' => $declinedVolume,
                'refunds' => $refunds,
                'chargebacks' => $chargebacks,
                'fees' => $fees,
                'reserve_holds' => $reserveHolds,
                'reserve_releases' => $reserveReleases,
                'payouts' => $payouts,
                'adjustments' => $adjustments,
                'net_result' => round($netResult, 2),
                'transaction_count' => $txnCount,
                'approved_count' => $approvedCount,
                'declined_count' => $declinedCount,
                'chargeback_count' => $cbCount,
                'chargeback_rate' => $cbRate,
            ];
        } catch (\Throwable $e) {
            report($e);
            return $defaults;
        }
    }

    public static function calculateByMid($from = null, $to = null): array
    {
        try {
            $mids = MerchantAccount::active()->get();
            $rows = [];

            foreach ($mids as $mid) {
                try {
                    $p = self::calculate($mid->id, $from, $to);
                    $rows[] = array_merge([
                        'mid_id' => $mid->id,
                        'mid_number' => $mid->mid_number,
                        'account_name' => $mid->account_name,
                        'processor' => $mid->processor_name,
                    ], $p);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    public static function calculateOverall($from = null, $to = null): array
    {
        return self::calculate(null, $from, $to);
    }

    // ── Aggregation helpers ─────────────────────────────

    private static function sumTransactions(?int $midId, $from, $to, ?array $statuses = null): float
    {
        $q = MerchantTransaction::query();
        if ($midId) $q->where('merchant_account_id', $midId);
        if ($statuses) $q->whereIn('transaction_status', $statuses);
        if ($from) $q->where('transaction_date', '>=', $from);
        if ($to) $q->where('transaction_date', '<=', $to);
        return (float) $q->sum('amount');
    }

    private static function countTransactions(?int $midId, $from, $to, ?array $statuses = null): int
    {
        $q = MerchantTransaction::query();
        if ($midId) $q->where('merchant_account_id', $midId);
        if ($statuses) $q->whereIn('transaction_status', $statuses);
        if ($from) $q->where('transaction_date', '>=', $from);
        if ($to) $q->where('transaction_date', '<=', $to);
        return $q->count();
    }

    private static function sumChargebacks(?int $midId, $from, $to): float
    {
        $q = MerchantChargeback::query();
        if ($midId) $q->where('merchant_account_id', $midId);
        if ($from) $q->where('opened_at', '>=', $from);
        if ($to) $q->where('opened_at', '<=', $to);
        return (float) $q->sum('amount');
    }

    private static function countChargebacks(?int $midId, $from, $to): int
    {
        $q = MerchantChargeback::query();
        if ($midId) $q->where('merchant_account_id', $midId);
        if ($from) $q->where('opened_at', '>=', $from);
        if ($to) $q->where('opened_at', '<=', $to);
        return $q->count();
    }

    private static function sumEntries(?int $midId, $from, $to, string $type): float
    {
        $q = MerchantFinancialEntry::where('entry_type', $type);
        if ($midId) $q->where('merchant_account_id', $midId);
        if ($from) $q->where('entry_date', '>=', $from);
        if ($to) $q->where('entry_date', '<=', $to);
        return (float) $q->sum('amount');
    }

    private static function getSettings(): array
    {
        return [
            'include_reserve_holds' => FinanceSetting::get('profitability.include_reserve_holds', true),
            'include_reserve_releases' => FinanceSetting::get('profitability.include_reserve_releases', true),
            'include_adjustments' => FinanceSetting::get('profitability.include_adjustments', true),
        ];
    }
}
