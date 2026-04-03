<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;

class CommissionCalculator
{
    public const SNR_PCT = 3;  // 3% of deal total
    public const VD_PCT = 5;   // 5% of deal total (if VD)
    public const DEFAULT_CLOSER_PCT = 40;
    public const PANAMA_CLOSER_PCT = 25;
    public const MULTI_CLOSER_PCT = 10;
    public const FRONTER_PCT = 10;
    public const MAX_CLOSERS = 4;

    /**
     * STEP 1: payable = deal_amount - SNR(3%) - VD(5% if VD)
     * STEP 2: commissions = % of payable_amount
     * STEP 3: 4 closers = 5 agents, all get 10% of payable (Panama halving OFF)
     */
    public static function calculate(Deal $deal): Deal
    {
        $dealAmount = (float) ($deal->fee ?? 0);
        if ($dealAmount <= 0) return $deal;

        $fronter = $deal->fronter ? User::find($deal->fronter) : null;
        $isPanama = ($fronter?->role === 'fronter_panama') || ($deal->fronter_role === 'fronter_panama');
        $deal->fronter_role = $fronter?->role ?? $deal->fronter_role;
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';

        // STEP 1: Calculate payable amount
        $snrAmount = round($dealAmount * (self::SNR_PCT / 100), 2);
        $vdAmount = $isVd ? round($dealAmount * (self::VD_PCT / 100), 2) : 0;
        $payable = $dealAmount - $snrAmount - $vdAmount;
        if ($payable < 0) $payable = 0;

        // Count closers
        $closerCount = 1;
        try {
            $c = DealCloser::where('deal_id', $deal->id)->count();
            if ($c > 0) $closerCount = $c;
        } catch (\Throwable $e) {}

        // STEP 2: Closer commissions from payable amount
        if ($closerCount > 1) {
            // Multi-closer: each gets 10% of payable
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
            // Single closer
            if ($isPanama) {
                $pct = (float) ($deal->closer_comm_pct ?? self::PANAMA_CLOSER_PCT);
                if ($pct > self::PANAMA_CLOSER_PCT) $pct = self::PANAMA_CLOSER_PCT;
            } else {
                $pct = (float) ($deal->closer_comm_pct ?? self::DEFAULT_CLOSER_PCT);
                if ($pct > self::DEFAULT_CLOSER_PCT) $pct = self::DEFAULT_CLOSER_PCT;
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

        // STEP 3: Fronter commission
        if ($closerCount >= 4) {
            // 4 closers = 5 agents total, all get 10% of payable
            // Panama halving does NOT apply at 4 closers
            $deal->fronter_comm_amount = round($payable * (self::FRONTER_PCT / 100), 2);
        } else {
            if ($isPanama) {
                // Panama: 10% of halved payable
                $deal->fronter_comm_amount = round(($payable / 2) * (self::FRONTER_PCT / 100), 2);
            } else {
                $deal->fronter_comm_amount = round($payable * (self::FRONTER_PCT / 100), 2);
            }
        }

        $deal->save();
        return $deal;
    }
}
