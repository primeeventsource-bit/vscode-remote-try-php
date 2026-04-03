<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;

class CommissionCalculator
{
    // SNR: ALWAYS 3% of TOTAL DEAL AMOUNT per closer. Fixed. Not editable.
    public const SNR_PCT = 3;
    // VD: ALWAYS 5% of TOTAL DEAL AMOUNT per closer. Fixed. Not editable.
    public const VD_PCT = 5;
    // Base commission
    public const DEFAULT_CLOSER_PCT = 40;
    public const PANAMA_CLOSER_PCT = 25;
    public const MULTI_CLOSER_PCT = 10;
    public const FRONTER_PCT = 10;
    public const MAX_CLOSERS = 4;

    /**
     * Formula: Closer Net = Base Commission - SNR(3% of deal) - VD(5% of deal if VD)
     * SNR and VD are ALWAYS flat amounts based on total deal, same for every closer.
     */
    public static function calculate(Deal $deal): Deal
    {
        $fee = (float) ($deal->fee ?? 0);
        if ($fee <= 0) return $deal;

        $fronter = $deal->fronter ? User::find($deal->fronter) : null;
        $isPanama = ($fronter?->role === 'fronter_panama') || ($deal->fronter_role === 'fronter_panama');
        $deal->fronter_role = $fronter?->role ?? $deal->fronter_role;
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';

        // CONSTANT deductions — always same per closer regardless of closer count
        $snr = $fee * (self::SNR_PCT / 100);       // 3% of deal amount
        $vd = $isVd ? $fee * (self::VD_PCT / 100) : 0; // 5% of deal amount if VD

        // Count closers
        $closerCount = 1;
        try {
            $c = DealCloser::where('deal_id', $deal->id)->count();
            if ($c > 0) $closerCount = $c;
        } catch (\Throwable $e) {}

        if ($closerCount > 1) {
            // Multi-closer: each gets 10%
            $base = $fee * (self::MULTI_CLOSER_PCT / 100);
            $net = $base - $snr - $vd;

            try {
                DealCloser::where('deal_id', $deal->id)->update([
                    'comm_pct' => self::MULTI_CLOSER_PCT,
                    'comm_amount' => $base,
                    'snr_deduction' => $snr,
                    'vd_deduction' => $vd,
                    'net_pay' => $net,
                ]);
            } catch (\Throwable $e) {}

            $deal->closer_comm_pct = self::MULTI_CLOSER_PCT;
            $deal->closer_comm_amount = $base;
        } else {
            // Single closer
            if ($isPanama) {
                $pct = (float) ($deal->closer_comm_pct ?? self::PANAMA_CLOSER_PCT);
                if ($pct > self::PANAMA_CLOSER_PCT) $pct = self::PANAMA_CLOSER_PCT;
            } else {
                $pct = (float) ($deal->closer_comm_pct ?? self::DEFAULT_CLOSER_PCT);
                if ($pct > self::DEFAULT_CLOSER_PCT) $pct = self::DEFAULT_CLOSER_PCT;
            }

            $base = $fee * ($pct / 100);
            $net = $base - $snr - $vd;

            $deal->closer_comm_pct = $pct;
            $deal->closer_comm_amount = $base;

            try {
                DealCloser::where('deal_id', $deal->id)->where('is_original', true)->update([
                    'comm_pct' => $pct, 'comm_amount' => $base,
                    'snr_deduction' => $snr, 'vd_deduction' => $vd, 'net_pay' => $net,
                ]);
            } catch (\Throwable $e) {}
        }

        $deal->snr_deduction = $snr;
        $deal->vd_deduction = $vd;
        $deal->closer_net_pay = $base - $snr - $vd;

        // Fronter
        $deal->fronter_comm_amount = $isPanama
            ? ($fee / 2) * (self::FRONTER_PCT / 100)
            : $fee * (self::FRONTER_PCT / 100);

        $deal->save();
        return $deal;
    }
}
