<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\PayrollSettingModel;
use App\Models\User;

/**
 * Legacy per-deal sales-commission engine.
 *
 * Sister engine: App\Services\Payroll\DealPayrollCalculator. The two are NOT
 * redundant — they solve different problems and both are intentionally kept:
 *
 *   - This engine (CommissionCalculator):
 *       * Computes per-deal CLOSER + FRONTER commission with SNR / VD
 *         deductions, multi-closer split, Panama 25% cap, MAX_CLOSERS hard cap.
 *       * Writes back to Deal columns (closer_comm_amount, fronter_comm_amount,
 *         snr_deduction, vd_deduction, ...) and saves the model.
 *       * Used by Livewire\Deals (deal save/edit), Livewire\Payroll (v1
 *         dashboard), and PipelineStateService (state transitions).
 *
 *   - DealPayrollCalculator (newer):
 *       * Computes finance-grade company-net using gross / collected /
 *         refunded / processing-fee / reserve-fee / marketing-cost /
 *         manual-adjustment.
 *       * Pure function — returns an array, no DB writes.
 *       * Used by DealPayrollSyncService → writes `deal_financials`.
 *       * Has no notion of SNR / VD / Panama / multi-closer.
 *
 * As of 2026-05 the two engines share a SINGLE rate source — the
 * `payroll_settings` key/value table accessed through PayrollSettingModel.
 * Before this fix, this class read from the (renamed-away) wide-column
 * payroll_settings table and silently fell back to its constants while
 * DealPayrollCalculator read the new key/value table — meaning UI rate
 * changes affected one engine but not the other.
 *
 * SNR / VD / Panama / multi-closer constants stay below — they are legacy-
 * engine-only concerns with no UI-configurable equivalent.
 */
class CommissionCalculator
{
    // Fallback constants — used when no PayrollSettingModel row is set.
    public const SNR_PCT = 3;                  // legacy-only, no UI knob
    public const VD_PCT = 5;                   // legacy-only, no UI knob
    public const DEFAULT_CLOSER_PCT = 40;      // fallback for closer_default_percent
    public const PANAMA_CLOSER_PCT = 25;       // legacy-only, no UI knob
    public const MULTI_CLOSER_PCT = 10;        // legacy-only, no UI knob
    public const FRONTER_PCT = 10;             // fallback for fronter_default_percent
    public const MAX_CLOSERS = 4;              // legacy-only

    /**
     * Load commission rates. Closer / fronter percentages come from the
     * unified PayrollSettingModel store (same source as DealPayrollCalculator).
     * SNR / VD remain class constants — they have no PayrollSettingModel key.
     */
    private static function loadRates(): array
    {
        return [
            'snr_pct'       => self::SNR_PCT,
            'vd_pct'        => self::VD_PCT,
            'closer_pct'    => (float) PayrollSettingModel::get('closer_default_percent', self::DEFAULT_CLOSER_PCT),
            'fronter_pct'   => (float) PayrollSettingModel::get('fronter_default_percent', self::FRONTER_PCT),
            'admin_snr_pct' => self::SNR_PCT,
        ];
    }

    public static function calculate(Deal $deal): Deal
    {
        $rates = self::loadRates();
        $dealAmount = (float) ($deal->fee ?? 0);
        if ($dealAmount <= 0) return $deal;

        $fronter = $deal->fronter ? User::find($deal->fronter) : null;
        $isPanama = ($fronter?->role === 'fronter_panama') || ($deal->fronter_role === 'fronter_panama');
        $deal->fronter_role = $fronter?->role ?? $deal->fronter_role;
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';

        // STEP 1: Calculate payable using DB rates
        $snrAmount = round($dealAmount * ($rates['snr_pct'] / 100), 2);
        $vdAmount = $isVd ? round($dealAmount * ($rates['vd_pct'] / 100), 2) : 0;
        $payable = max(0, $dealAmount - $snrAmount - $vdAmount);

        // Count closers
        $closerCount = 1;
        try {
            $c = DealCloser::where('deal_id', $deal->id)->count();
            if ($c > 0) $closerCount = $c;
        } catch (\Throwable $e) {}

        // STEP 2: Closer commissions
        if ($closerCount > 1) {
            $closerPay = round($payable * (self::MULTI_CLOSER_PCT / 100), 2);
            try {
                DealCloser::where('deal_id', $deal->id)->update([
                    'comm_pct' => self::MULTI_CLOSER_PCT,
                    'comm_amount' => $closerPay,
                    'snr_deduction' => $snrAmount,
                    'vd_deduction' => $vdAmount,
                    'net_pay' => $closerPay,
                ]);
            } catch (\Throwable $e) {}
            $deal->closer_comm_pct = self::MULTI_CLOSER_PCT;
            $deal->closer_comm_amount = $closerPay;
            $deal->closer_net_pay = $closerPay;
        } else {
            if ($isPanama) {
                $pct = (float) ($deal->closer_comm_pct ?? self::PANAMA_CLOSER_PCT);
                if ($pct > self::PANAMA_CLOSER_PCT) $pct = self::PANAMA_CLOSER_PCT;
            } else {
                $pct = (float) ($deal->closer_comm_pct ?? $rates['closer_pct']);
                if ($pct > $rates['closer_pct']) $pct = $rates['closer_pct'];
            }
            $closerPay = round($payable * ($pct / 100), 2);
            $deal->closer_comm_pct = $pct;
            $deal->closer_comm_amount = $closerPay;
            $deal->closer_net_pay = $closerPay;
            try {
                DealCloser::where('deal_id', $deal->id)->where('is_original', true)->update([
                    'comm_pct' => $pct, 'comm_amount' => $closerPay,
                    'snr_deduction' => $snrAmount, 'vd_deduction' => $vdAmount, 'net_pay' => $closerPay,
                ]);
            } catch (\Throwable $e) {}
        }

        $deal->snr_deduction = $snrAmount;
        $deal->vd_deduction = $vdAmount;

        // STEP 3: Fronter commission using DB rate
        $fronterPct = $rates['fronter_pct'];
        if ($closerCount >= self::MAX_CLOSERS) {
            $deal->fronter_comm_amount = round($payable * ($fronterPct / 100), 2);
        } else {
            if ($isPanama) {
                $deal->fronter_comm_amount = round(($payable / 2) * ($fronterPct / 100), 2);
            } else {
                $deal->fronter_comm_amount = round($payable * ($fronterPct / 100), 2);
            }
        }

        $deal->save();
        return $deal;
    }
}
