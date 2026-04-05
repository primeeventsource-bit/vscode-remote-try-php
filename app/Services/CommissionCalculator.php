<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionCalculator
{
    // Fallback constants if DB lookup fails
    public const SNR_PCT = 3;
    public const VD_PCT = 5;
    public const DEFAULT_CLOSER_PCT = 40;
    public const PANAMA_CLOSER_PCT = 25;
    public const MULTI_CLOSER_PCT = 10;
    public const FRONTER_PCT = 10;
    public const MAX_CLOSERS = 4;

    /**
     * Load rates from payroll_settings table, fall back to constants.
     */
    private static function loadRates(): array
    {
        try {
            $row = DB::table('payroll_settings')->first();
            if ($row) {
                return [
                    'snr_pct' => (float) ($row->snr_pct ?? self::SNR_PCT),
                    'vd_pct' => (float) ($row->vd_pct ?? self::VD_PCT),
                    'closer_pct' => (float) ($row->closer_pct ?? self::DEFAULT_CLOSER_PCT),
                    'fronter_pct' => (float) ($row->fronter_pct ?? self::FRONTER_PCT),
                    'admin_snr_pct' => (float) ($row->admin_snr_pct ?? self::SNR_PCT),
                ];
            }
        } catch (\Throwable $e) {}

        return [
            'snr_pct' => self::SNR_PCT,
            'vd_pct' => self::VD_PCT,
            'closer_pct' => self::DEFAULT_CLOSER_PCT,
            'fronter_pct' => self::FRONTER_PCT,
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
