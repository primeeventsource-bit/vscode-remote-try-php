<?php

namespace App\Services\Finance;

use App\Models\MerchantStatement;
use App\Models\StatementChargeback;

/**
 * Attempts to link reversals to their original chargebacks.
 */
class ChargebackLinkingService
{
    /**
     * After parsing, attempt to auto-link reversals to chargebacks
     * within the same statement.
     */
    public static function linkReversals(MerchantStatement $statement): int
    {
        $linked = 0;

        $reversals = $statement->chargebacks()
            ->whereIn('event_type', ['reversal', 'representment_credit'])
            ->whereNull('linked_chargeback_id')
            ->get();

        $chargebacks = $statement->chargebacks()
            ->where('event_type', 'chargeback')
            ->get()
            ->keyBy('reference_number');

        foreach ($reversals as $reversal) {
            $match = null;
            $confidence = 0;

            // Exact reference match
            if ($reversal->reference_number && $chargebacks->has($reversal->reference_number)) {
                $match = $chargebacks[$reversal->reference_number];
                $confidence = 95;
            }

            // Fallback: match by amount + close date
            if (!$match) {
                $candidates = $statement->chargebacks()
                    ->where('event_type', 'chargeback')
                    ->where('amount', $reversal->amount)
                    ->whereNull('linked_reversal_id')
                    ->get();

                if ($candidates->count() === 1) {
                    $match = $candidates->first();
                    $confidence = 70;
                } elseif ($candidates->count() > 1 && $reversal->chargeback_day) {
                    // Pick the one closest in day
                    $match = $candidates->sortBy(function ($cb) use ($reversal) {
                        return abs(($cb->chargeback_day ?? 0) - ($reversal->chargeback_day ?? 0));
                    })->first();
                    $confidence = 55;
                }
            }

            if ($match) {
                $reversal->update([
                    'linked_chargeback_id' => $match->id,
                    'matching_confidence' => $confidence,
                ]);
                $match->update([
                    'linked_reversal_id' => $reversal->id,
                    'recovered_flag' => true,
                ]);
                $linked++;
            }
        }

        return $linked;
    }
}
