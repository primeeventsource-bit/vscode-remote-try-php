<?php

namespace App\Services\Payroll;

use App\Models\Deal;
use App\Models\DealFinancial;
use App\Models\FinanceAudit;
use Illuminate\Support\Facades\DB;

/**
 * Creates/updates deal_financials snapshot. Handles locking, reversals, and audit trails.
 */
class DealPayrollSyncService
{
    /**
     * Calculate and save financial snapshot for a deal.
     */
    public static function syncForDeal(Deal $deal, bool $lock = false, ?array $overridePercents = null): DealFinancial
    {
        return DB::transaction(function () use ($deal, $lock, $overridePercents) {
            $calc = DealPayrollCalculator::calculate($deal, $overridePercents);

            $existing = DealFinancial::where('deal_id', $deal->id)->first();
            $before = $existing ? $existing->toArray() : null;

            // Do not overwrite locked financials unless this is an explicit override
            if ($existing && $existing->is_locked && !$lock) {
                return $existing;
            }

            $data = [
                'deal_id' => $deal->id,
                'fronter_percent' => $calc['fronter_percent'],
                'closer_percent' => $calc['closer_percent'],
                'admin_percent' => $calc['admin_percent'],
                'processing_percent' => $calc['processing_percent'],
                'reserve_percent' => $calc['reserve_percent'],
                'marketing_percent' => $calc['marketing_percent'],
                'gross_amount' => $calc['gross_amount'],
                'collected_amount' => $calc['collected_amount'],
                'refunded_amount' => $calc['refunded_amount'],
                'chargeback_amount' => $calc['chargeback_amount'],
                'fronter_commission' => $calc['fronter_commission'],
                'closer_commission' => $calc['closer_commission'],
                'admin_commission' => $calc['admin_commission'],
                'processing_fee' => $calc['processing_fee'],
                'reserve_fee' => $calc['reserve_fee'],
                'marketing_cost' => $calc['marketing_cost'],
                'company_net' => $calc['company_net'],
                'company_net_percent' => $calc['company_net_percent'],
                'calculated_at' => now(),
                'updated_by' => auth()->id(),
            ];

            if (!$existing) {
                $data['created_by'] = auth()->id();
            }

            if ($lock) {
                $data['is_locked'] = true;
                $data['locked_at'] = now();
            }

            $financial = DealFinancial::updateOrCreate(
                ['deal_id' => $deal->id],
                $data
            );

            // Update deal status
            $deal->update([
                'payroll_status' => 'calculated',
                'commission_status' => $lock ? 'locked' : 'calculated',
                'finance_snapshot_id' => $financial->id,
                'gross_amount' => $calc['gross_amount'],
                'collected_amount' => $calc['collected_amount'],
            ]);

            if ($lock) {
                $deal->update([
                    'payroll_locked_at' => now(),
                    'payroll_locked_by' => auth()->id(),
                ]);
            }

            // Audit
            FinanceAudit::record(
                'DealFinancial', $financial->id,
                $before ? 'recalculated' : 'created',
                $before, $financial->toArray(),
                $lock ? 'Calculated and locked' : 'Calculated'
            );

            return $financial;
        });
    }

    /**
     * Recalculate an unlocked deal.
     */
    public static function recalculateUnlockedDeal(Deal $deal): DealFinancial
    {
        $existing = DealFinancial::where('deal_id', $deal->id)->first();
        if ($existing && $existing->is_locked) {
            throw new \RuntimeException('Cannot recalculate locked deal financial. Use Master Admin override.');
        }

        return self::syncForDeal($deal, lock: false);
    }

    /**
     * Reverse a deal's payroll (chargeback, full refund, or void).
     */
    public static function reverseDeal(Deal $deal, string $reason): void
    {
        DB::transaction(function () use ($deal, $reason) {
            $financial = DealFinancial::where('deal_id', $deal->id)->first();
            if (!$financial) return;

            $before = $financial->toArray();

            $financial->update([
                'is_reversed' => true,
                'is_disputed' => true,
                'manual_adjustment' => -($financial->company_net),
                'adjustment_reason' => 'REVERSED: ' . $reason,
                'updated_by' => auth()->id(),
            ]);

            $deal->update([
                'payroll_status' => 'void',
                'commission_status' => 'reversed',
            ]);

            FinanceAudit::record(
                'DealFinancial', $financial->id,
                'reversed',
                $before, $financial->fresh()->toArray(),
                $reason
            );
        });
    }

    /**
     * Mark a deal as disputed (chargeback received).
     */
    public static function markDisputed(Deal $deal, string $reason = 'Chargeback received'): void
    {
        DB::transaction(function () use ($deal, $reason) {
            $financial = DealFinancial::where('deal_id', $deal->id)->first();
            if (!$financial) return;

            $before = $financial->toArray();
            $financial->update(['is_disputed' => true, 'updated_by' => auth()->id()]);
            $deal->update(['payroll_status' => 'disputed', 'is_chargeback' => true]);

            FinanceAudit::record('DealFinancial', $financial->id, 'disputed', $before, $financial->fresh()->toArray(), $reason);
        });
    }

    /**
     * Add manual adjustment to a deal financial.
     */
    public static function addAdjustment(Deal $deal, float $amount, string $reason): DealFinancial
    {
        return DB::transaction(function () use ($deal, $amount, $reason) {
            $financial = DealFinancial::where('deal_id', $deal->id)->first();
            if (!$financial) {
                $financial = self::syncForDeal($deal);
            }

            if ($financial->is_locked && !auth()->user()?->hasRole('master_admin')) {
                throw new \RuntimeException('Cannot adjust locked deal financial without Master Admin.');
            }

            $before = $financial->toArray();
            $newAdj = (float) $financial->manual_adjustment + $amount;
            $newNet = (float) $financial->company_net + $amount;

            $financial->update([
                'manual_adjustment' => round($newAdj, 2),
                'adjustment_reason' => $reason,
                'company_net' => round($newNet, 2),
                'company_net_percent' => $financial->calculation_base > 0 ? round($newNet / $financial->calculation_base, 4) : 0,
                'updated_by' => auth()->id(),
            ]);

            $deal->update(['payroll_status' => 'adjusted']);

            FinanceAudit::record('DealFinancial', $financial->id, 'adjustment', $before, $financial->fresh()->toArray(), "Adjustment: \${$amount} — {$reason}");

            return $financial->fresh();
        });
    }
}
