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
     * ALL rep pay calculated from Net Deal Amount = Deal Total - SNR - VD
     * NOT from the gross deal total.
     *
     * Example: $4,788 deal, VD
     *   SNR = $4,788 × 3% = $143.64
     *   VD  = $4,788 × 5% = $239.40
     *   Net = $4,788 - $143.64 - $239.40 = $4,404.96
     *   Closer 40% = $4,404.96 × 40% = $1,761.98
     *   Fronter 10% = $4,404.96 × 10% = $440.50
     */
    public static function calculate(Deal $deal): Deal
    {
        $dealTotal = (float) ($deal->fee ?? 0);
        if ($dealTotal <= 0) return $deal;

        $fronter = $deal->fronter ? User::find($deal->fronter) : null;
        $isPanama = ($fronter?->role === 'fronter_panama') || ($deal->fronter_role === 'fronter_panama');
        $deal->fronter_role = $fronter?->role ?? $deal->fronter_role;
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';

        // Step 1: Calculate SNR and VD from gross deal total
        $snrAmount = round($dealTotal * (self::SNR_PCT / 100), 2);
        $vdAmount = $isVd ? round($dealTotal * (self::VD_PCT / 100), 2) : 0;

        // Step 2: Net Deal Amount = Deal Total - SNR - VD
        $netDealAmount = $dealTotal - $snrAmount - $vdAmount;
        if ($netDealAmount < 0) $netDealAmount = 0;

        // Step 3: ALL rep pay based on Net Deal Amount
        $closerCount = 1;
        try {
            $c = DealCloser::where('deal_id', $deal->id)->count();
            if ($c > 0) $closerCount = $c;
        } catch (\Throwable $e) {}

        if ($closerCount > 1) {
            // Multi-closer: each gets 10% of NET deal amount
            $closerPay = round($netDealAmount * (self::MULTI_CLOSER_PCT / 100), 2);

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

            // Closer pay from NET deal amount
            $closerPay = round($netDealAmount * ($pct / 100), 2);

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

        // Fronter pay from NET deal amount
        if ($isPanama) {
            // Panama: 10% of HALVED net deal amount
            $deal->fronter_comm_amount = round(($netDealAmount / 2) * (self::FRONTER_PCT / 100), 2);
        } else {
            $deal->fronter_comm_amount = round($netDealAmount * (self::FRONTER_PCT / 100), 2);
        }

        $deal->snr_deduction = $snrAmount;
        $deal->vd_deduction = $vdAmount;

        $deal->save();
        return $deal;
    }
}
