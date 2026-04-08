<?php

namespace App\Services\Payroll;

use App\Models\Deal;
use App\Models\PayrollSettingModel;

/**
 * Pure calculation engine. No database writes. No side effects.
 *
 * Formulas:
 *   calculation_base = collected_amount > 0 ? collected_amount : gross_amount
 *   calculation_base -= refunded_amount (if refund exists)
 *   if calculation_base < 0 → 0
 *
 *   fronter_commission = base * (fronter_percent / 100)
 *   closer_commission  = base * (closer_percent / 100)
 *   admin_commission   = base * (admin_percent / 100)
 *   processing_fee     = base * (processing_percent / 100)
 *   reserve_fee        = base * (reserve_percent / 100)
 *   marketing_cost     = base * (marketing_percent / 100)
 *
 *   company_net = base - all_above + manual_adjustment
 *   company_net_percent = company_net / base (or 0 if base = 0)
 */
class DealPayrollCalculator
{
    public static function calculate(Deal $deal, ?array $overridePercents = null): array
    {
        $defaults = PayrollSettingModel::getDefaults();

        // Use override percents if provided, otherwise use defaults
        $fronterPct = (float) ($overridePercents['fronter_percent'] ?? $defaults['fronter_percent']);
        $closerPct = (float) ($overridePercents['closer_percent'] ?? $defaults['closer_percent']);
        $adminPct = (float) ($overridePercents['admin_percent'] ?? $defaults['admin_percent']);
        $processingPct = (float) ($overridePercents['processing_percent'] ?? $defaults['processing_percent']);
        $reservePct = (float) ($overridePercents['reserve_percent'] ?? $defaults['reserve_percent']);
        $marketingPct = (float) ($overridePercents['marketing_percent'] ?? $defaults['marketing_percent']);

        // Source amounts
        $gross = (float) ($deal->gross_amount ?: $deal->fee ?: 0);
        $collected = (float) ($deal->collected_amount ?: 0);
        $refunded = (float) ($deal->refunded_amount ?: 0);
        $chargeback = (float) ($deal->chargeback_amount ?: 0);

        // Calculation base per spec
        $base = $collected > 0 ? $collected : $gross;
        $base -= $refunded;
        $base = max($base, 0);

        // Calculate commissions
        $fronterComm = round($base * ($fronterPct / 100), 2);
        $closerComm = round($base * ($closerPct / 100), 2);
        $adminComm = round($base * ($adminPct / 100), 2);

        // Calculate business deductions
        $processingFee = round($base * ($processingPct / 100), 2);
        $reserveFee = round($base * ($reservePct / 100), 2);
        $marketingCost = round($base * ($marketingPct / 100), 2);

        // Manual adjustment (from existing deal financial if present)
        $manualAdj = 0;
        if ($deal->dealFinancial) {
            $manualAdj = (float) $deal->dealFinancial->manual_adjustment;
        }

        // Company net
        $companyNet = round($base - $fronterComm - $closerComm - $adminComm - $processingFee - $reserveFee - $marketingCost + $manualAdj, 2);
        $companyNetPct = $base > 0 ? round($companyNet / $base, 4) : 0;

        return [
            // Amounts
            'gross_amount' => $gross,
            'collected_amount' => $collected > 0 ? $collected : $gross,
            'refunded_amount' => $refunded,
            'chargeback_amount' => $chargeback,
            'calculation_base' => $base,

            // Percent snapshot
            'fronter_percent' => $fronterPct,
            'closer_percent' => $closerPct,
            'admin_percent' => $adminPct,
            'processing_percent' => $processingPct,
            'reserve_percent' => $reservePct,
            'marketing_percent' => $marketingPct,

            // Commissions
            'fronter_commission' => $fronterComm,
            'closer_commission' => $closerComm,
            'admin_commission' => $adminComm,

            // Business deductions
            'processing_fee' => $processingFee,
            'reserve_fee' => $reserveFee,
            'marketing_cost' => $marketingCost,

            // Company result
            'manual_adjustment' => $manualAdj,
            'company_net' => $companyNet,
            'company_net_percent' => $companyNetPct,
        ];
    }
}
