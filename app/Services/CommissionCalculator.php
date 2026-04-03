<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;

class CommissionCalculator
{
    // SNR: single closer = 3% of deal amount
    // SNR: multi closer = 1% × num_closers, applied to each closer's PAY
    public const SNR_SINGLE_PCT = 3;       // 3% of deal amount
    public const SNR_MULTI_PER_CLOSER = 1; // 1% per closer, of closer's pay
    public const VD_PCT = 3;               // 3% of deal amount (changed from 5%)
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
        $isVd = $deal->is_vd_deal || $deal->was_vd === 'Yes';

        // Count closers
        $closerCount = 1;
        try {
            $dbCount = DealCloser::where('deal_id', $deal->id)->count();
            if ($dbCount > 0) $closerCount = $dbCount;
        } catch (\Throwable $e) {}

        $isMulti = $closerCount > 1;

        // VD deduction: always 3% of total deal amount
        $vdDeduction = $isVd ? $fee * (self::VD_PCT / 100) : 0;

        if ($isMulti) {
            // Multi-closer: each gets 10%
            $closerBase = $fee * (self::MULTI_CLOSER_PCT / 100);

            // SNR: 1% × num_closers, applied to each closer's PAY
            $snrPct = self::SNR_MULTI_PER_CLOSER * $closerCount; // e.g. 2 closers = 2%, 3 = 3%, 4 = 4%
            $snrDeduction = $closerBase * ($snrPct / 100);

            $closerNet = $closerBase - $snrDeduction - $vdDeduction;

            // Update each closer record
            try {
                DealCloser::where('deal_id', $deal->id)->update([
                    'comm_pct' => self::MULTI_CLOSER_PCT,
                    'comm_amount' => $closerBase,
                    'snr_deduction' => $snrDeduction,
                    'vd_deduction' => $vdDeduction,
                    'net_pay' => $closerNet,
                ]);
            } catch (\Throwable $e) {}

            $deal->closer_comm_pct = self::MULTI_CLOSER_PCT;
            $deal->closer_comm_amount = $closerBase;
            $deal->snr_deduction = $snrDeduction;
            $deal->vd_deduction = $vdDeduction;
            $deal->closer_net_pay = $closerNet;
        } else {
            // Single closer
            if ($isPanama) {
                $closerPct = (float) ($deal->closer_comm_pct ?? self::PANAMA_CLOSER_PCT);
                if ($closerPct > self::PANAMA_CLOSER_PCT) $closerPct = self::PANAMA_CLOSER_PCT;
            } else {
                $closerPct = (float) ($deal->closer_comm_pct ?? self::DEFAULT_CLOSER_PCT);
                if ($closerPct > self::DEFAULT_CLOSER_PCT) $closerPct = self::DEFAULT_CLOSER_PCT;
            }

            $closerBase = $fee * ($closerPct / 100);

            // SNR: 3% of total deal amount for single closer
            $snrDeduction = $fee * (self::SNR_SINGLE_PCT / 100);

            $closerNet = $closerBase - $snrDeduction - $vdDeduction;

            $deal->closer_comm_pct = $closerPct;
            $deal->closer_comm_amount = $closerBase;
            $deal->snr_deduction = $snrDeduction;
            $deal->vd_deduction = $vdDeduction;
            $deal->closer_net_pay = $closerNet;

            try {
                DealCloser::where('deal_id', $deal->id)->where('is_original', true)->update([
                    'comm_pct' => $closerPct, 'comm_amount' => $closerBase,
                    'snr_deduction' => $snrDeduction, 'vd_deduction' => $vdDeduction, 'net_pay' => $closerNet,
                ]);
            } catch (\Throwable $e) {}
        }

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
