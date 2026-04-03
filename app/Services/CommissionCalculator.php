<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;

class CommissionCalculator
{
    public const SNR_PCT = 3;  // 3% of deal amount, per closer
    public const VD_PCT = 5;   // 5% of deal amount, per closer (if VD)
    public const DEFAULT_CLOSER_PCT = 40;
    public const PANAMA_CLOSER_PCT = 25;
    public const MULTI_CLOSER_PCT = 10;
    public const FRONTER_PCT = 10;
    public const MAX_CLOSERS = 4;

    /**
     * Closer Net = (Deal Amount × Base%) - SNR(3% of deal) - VD(5% of deal if VD)
     * Fronter: 1-3 closers = normal rules. 4 closers = matches closer net pay.
     */
    public static function calculate(Deal $deal): Deal
    {
        $fee = (float) ($deal->fee ?? 0);
        if ($fee <= 0) return $deal;

        $fronter = $deal->fronter ? User::find($deal->fronter) : null;
        $isPanama = ($fronter?->role === 'fronter_panama') || ($deal->fronter_role === 'fronter_panama');
        $deal->fronter_role = $fronter?->role ?? $deal->fronter_role;
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';

        // Flat deductions per closer — always based on total deal amount
        $snr = round($fee * (self::SNR_PCT / 100), 2);
        $vd = $isVd ? round($fee * (self::VD_PCT / 100), 2) : 0;

        // Count closers
        $closerCount = 1;
        try {
            $c = DealCloser::where('deal_id', $deal->id)->count();
            if ($c > 0) $closerCount = $c;
        } catch (\Throwable $e) {}

        if ($closerCount > 1) {
            // Multi-closer: each gets 10% of deal amount
            $closerBase = round($fee * (self::MULTI_CLOSER_PCT / 100), 2);
            $closerNet = round($closerBase - $snr - $vd, 2);

            try {
                DealCloser::where('deal_id', $deal->id)->update([
                    'comm_pct' => self::MULTI_CLOSER_PCT,
                    'comm_amount' => $closerBase,
                    'snr_deduction' => $snr,
                    'vd_deduction' => $vd,
                    'net_pay' => $closerNet,
                ]);
            } catch (\Throwable $e) {}

            $deal->closer_comm_pct = self::MULTI_CLOSER_PCT;
            $deal->closer_comm_amount = $closerBase;
            $deal->closer_net_pay = $closerNet;
        } else {
            // Single closer
            if ($isPanama) {
                $pct = (float) ($deal->closer_comm_pct ?? self::PANAMA_CLOSER_PCT);
                if ($pct > self::PANAMA_CLOSER_PCT) $pct = self::PANAMA_CLOSER_PCT;
            } else {
                $pct = (float) ($deal->closer_comm_pct ?? self::DEFAULT_CLOSER_PCT);
                if ($pct > self::DEFAULT_CLOSER_PCT) $pct = self::DEFAULT_CLOSER_PCT;
            }

            $closerBase = round($fee * ($pct / 100), 2);
            $closerNet = round($closerBase - $snr - $vd, 2);

            $deal->closer_comm_pct = $pct;
            $deal->closer_comm_amount = $closerBase;
            $deal->closer_net_pay = $closerNet;

            try {
                DealCloser::where('deal_id', $deal->id)->where('is_original', true)->update([
                    'comm_pct' => $pct, 'comm_amount' => $closerBase,
                    'snr_deduction' => $snr, 'vd_deduction' => $vd, 'net_pay' => $closerNet,
                ]);
            } catch (\Throwable $e) {}
        }

        $deal->snr_deduction = $snr;
        $deal->vd_deduction = $vd;

        // FRONTER COMMISSION
        // 4 closers: fronter gets SAME as closer net pay
        // 1-3 closers: normal rules
        if ($closerCount >= 4) {
            // Calculate what one closer nets (all multi-closers net the same)
            $oneCloserBase = round($fee * (self::MULTI_CLOSER_PCT / 100), 2);
            $oneCloserNet = round($oneCloserBase - $snr - $vd, 2);
            $deal->fronter_comm_amount = $oneCloserNet;
        } else {
            if ($isPanama) {
                $deal->fronter_comm_amount = round(($fee / 2) * (self::FRONTER_PCT / 100), 2);
            } else {
                $deal->fronter_comm_amount = round($fee * (self::FRONTER_PCT / 100), 2);
            }
        }

        $deal->save();
        return $deal;
    }
}
