<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\User;

class CommissionCalculator
{
    private const SNR_PCT = 3;
    private const VD_PCT = 5;
    private const DEFAULT_CLOSER_PCT = 40;
    private const PANAMA_CLOSER_PCT = 25;
    private const FRONTER_PCT = 10;

    /**
     * Calculate and store commissions for a charged deal.
     */
    public static function calculate(Deal $deal): Deal
    {
        $fee = (float) ($deal->fee ?? 0);
        if ($fee <= 0) return $deal;

        $fronter = $deal->fronter ? User::find($deal->fronter) : null;
        $isPanama = ($fronter?->role === 'fronter_panama') || ($deal->fronter_role === 'fronter_panama');

        // Store fronter role for reference
        $deal->fronter_role = $fronter?->role ?? $deal->fronter_role;

        // Closer commission percentage
        if ($isPanama) {
            $closerPct = (float) ($deal->closer_comm_pct ?? self::PANAMA_CLOSER_PCT);
            // Cap at 25% for Panama
            if ($closerPct > self::PANAMA_CLOSER_PCT) $closerPct = self::PANAMA_CLOSER_PCT;
        } else {
            $closerPct = (float) ($deal->closer_comm_pct ?? self::DEFAULT_CLOSER_PCT);
            // Cap at 40% for regular
            if ($closerPct > self::DEFAULT_CLOSER_PCT) $closerPct = self::DEFAULT_CLOSER_PCT;
        }

        $deal->closer_comm_pct = $closerPct;

        // Closer gross commission
        $closerGross = $fee * ($closerPct / 100);

        // SNR deduction (always 3% of total deal)
        $snr = $fee * (self::SNR_PCT / 100);
        $deal->snr_deduction = $snr;

        // VD deduction (5% of total deal if VD)
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';
        $vd = $isVd ? $fee * (self::VD_PCT / 100) : 0;
        $deal->vd_deduction = $vd;

        // Closer net
        $deal->closer_comm_amount = $closerGross;
        $deal->closer_net_pay = $closerGross - $snr - $vd;

        // Fronter commission
        if ($isPanama) {
            // Panama: 10% of HALVED deal amount = 5% of total
            $deal->fronter_comm_amount = ($fee / 2) * (self::FRONTER_PCT / 100);
        } else {
            // Regular: 10% of total
            $deal->fronter_comm_amount = $fee * (self::FRONTER_PCT / 100);
        }

        $deal->save();
        return $deal;
    }
}
