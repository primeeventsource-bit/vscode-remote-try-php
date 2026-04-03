<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;

class CommissionCalculator
{
    public const SNR_PCT = 3;
    public const VD_PCT = 5;
    public const DEFAULT_CLOSER_PCT = 40;
    public const PANAMA_CLOSER_PCT = 25;
    public const MULTI_CLOSER_PCT = 10;
    public const FRONTER_PCT = 10;
    public const MAX_CLOSERS = 4;

    public static function calculate(Deal $deal): Deal
    {
        $fee = (float) ($deal->fee ?? 0);
        if ($fee <= 0) return $deal;

        $fronter = $deal->fronter ? User::find($deal->fronter) : null;
        $isPanama = ($fronter?->role === 'fronter_panama') || ($deal->fronter_role === 'fronter_panama');
        $deal->fronter_role = $fronter?->role ?? $deal->fronter_role;

        // Count closers on the deal
        try {
            $closerCount = DealCloser::where('deal_id', $deal->id)->count();
        } catch (\Throwable $e) {
            $closerCount = 0;
        }
        $isMulti = $closerCount > 1;

        // SNR and VD (always based on total deal, same for all)
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';
        $snrPerCloser = $fee * (self::SNR_PCT / 100);
        $vdPerCloser = $isVd ? $fee * (self::VD_PCT / 100) : 0;

        if ($isMulti) {
            // Multi-closer: each closer gets 10% minus deductions
            $closerPct = self::MULTI_CLOSER_PCT;
            $closerGross = $fee * ($closerPct / 100);
            $closerNet = $closerGross - $snrPerCloser - $vdPerCloser;

            // Update each closer record
            try {
                DealCloser::where('deal_id', $deal->id)->update([
                    'comm_pct' => $closerPct,
                    'comm_amount' => $closerGross,
                    'snr_deduction' => $snrPerCloser,
                    'vd_deduction' => $vdPerCloser,
                    'net_pay' => $closerNet,
                ]);
            } catch (\Throwable $e) {}

            // Store on deal for the original closer
            $deal->closer_comm_pct = $closerPct;
            $deal->closer_comm_amount = $closerGross;
            $deal->closer_net_pay = $closerNet;
        } else {
            // Single closer: normal rules
            if ($isPanama) {
                $closerPct = (float) ($deal->closer_comm_pct ?? self::PANAMA_CLOSER_PCT);
                if ($closerPct > self::PANAMA_CLOSER_PCT) $closerPct = self::PANAMA_CLOSER_PCT;
            } else {
                $closerPct = (float) ($deal->closer_comm_pct ?? self::DEFAULT_CLOSER_PCT);
                if ($closerPct > self::DEFAULT_CLOSER_PCT) $closerPct = self::DEFAULT_CLOSER_PCT;
            }

            $closerGross = $fee * ($closerPct / 100);
            $closerNet = $closerGross - $snrPerCloser - $vdPerCloser;

            $deal->closer_comm_pct = $closerPct;
            $deal->closer_comm_amount = $closerGross;
            $deal->closer_net_pay = $closerNet;

            // Update original closer record if exists
            try {
                DealCloser::where('deal_id', $deal->id)->where('is_original', true)->update([
                    'comm_pct' => $closerPct, 'comm_amount' => $closerGross,
                    'snr_deduction' => $snrPerCloser, 'vd_deduction' => $vdPerCloser, 'net_pay' => $closerNet,
                ]);
            } catch (\Throwable $e) {}
        }

        $deal->snr_deduction = $snrPerCloser;
        $deal->vd_deduction = $vdPerCloser;

        // Fronter commission
        if ($isPanama) {
            $deal->fronter_comm_amount = ($fee / 2) * (self::FRONTER_PCT / 100);
        } else {
            $deal->fronter_comm_amount = $fee * (self::FRONTER_PCT / 100);
        }

        $deal->save();
        return $deal;
    }
}
