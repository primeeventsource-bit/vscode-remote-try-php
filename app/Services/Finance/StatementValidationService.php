<?php

namespace App\Services\Finance;

use App\Models\MerchantStatement;
use App\Models\FinanceReviewItem;

/**
 * Validates parsed statement data against consistency rules.
 */
class StatementValidationService
{
    private const TOLERANCE_PCT = 2.0; // 2% tolerance for rounding

    /**
     * Validate a parsed statement and return status + notes.
     *
     * @return array{status: string, notes: string[]}
     */
    public static function validate(MerchantStatement $statement): array
    {
        $notes = [];
        $hasWarning = false;
        $hasFail = false;

        // 1. Deposit totals check
        $depositSum = $statement->deposits()->sum('net_deposit');
        if ($statement->total_deposits != 0) {
            $diff = abs($depositSum - (float) $statement->total_deposits);
            $pct = (float) $statement->total_deposits != 0 ? ($diff / abs((float) $statement->total_deposits)) * 100 : 0;
            if ($pct > self::TOLERANCE_PCT && $diff > 1.00) {
                $notes[] = "Deposit rows sum ($depositSum) differs from statement total ({$statement->total_deposits}) by " . round($pct, 2) . "%";
                $hasWarning = true;
            }
        }

        // 2. Net sales = gross - credits
        $expectedNet = (float) $statement->gross_sales - (float) $statement->credits;
        if ($expectedNet != 0 && (float) $statement->net_sales != 0) {
            $diff = abs($expectedNet - (float) $statement->net_sales);
            if ($diff > 1.00) {
                $notes[] = "Net sales ({$statement->net_sales}) doesn't match gross ({$statement->gross_sales}) - credits ({$statement->credits}) = $expectedNet";
                $hasWarning = true;
            }
        }

        // 3. Fee rows sum check
        $feeRowSum = $statement->fees()->sum('fee_total');
        $stmtFees = (float) $statement->fees_paid;
        if ($stmtFees > 0) {
            $diff = abs($feeRowSum - $stmtFees);
            $pct = ($diff / $stmtFees) * 100;
            if ($pct > self::TOLERANCE_PCT && $diff > 1.00) {
                $notes[] = "Fee rows sum ($feeRowSum) differs from statement fees paid ($stmtFees) by " . round($pct, 2) . "%";
                $hasWarning = true;
            }
        }

        // 4. Chargeback totals check
        $cbSum = $statement->chargebacks()->where('event_type', 'chargeback')->sum('amount');
        if ((float) $statement->total_chargebacks > 0) {
            $diff = abs($cbSum - (float) $statement->total_chargebacks);
            if ($diff > 1.00) {
                $notes[] = "Chargeback rows sum ($cbSum) differs from statement total ({$statement->total_chargebacks})";
                $hasWarning = true;
            }
        }

        // 5. Reserve balance reconciliation
        $lastReserve = $statement->reserves()->orderByDesc('reserve_day')->first();
        if ($lastReserve && (float) $statement->reserve_ending_balance > 0) {
            $diff = abs((float) $lastReserve->running_balance - (float) $statement->reserve_ending_balance);
            if ($diff > 1.00) {
                $notes[] = "Last reserve row balance ({$lastReserve->running_balance}) differs from ending balance ({$statement->reserve_ending_balance})";
                $hasWarning = true;
            }
        }

        // 6. Missing critical data check
        if ((float) $statement->gross_sales == 0 && $statement->deposits()->count() > 0) {
            $notes[] = "Gross sales is zero but deposits exist — possible header parse failure";
            $hasFail = true;
        }

        if ($statement->detection_confidence < 50) {
            $notes[] = "Low processor detection confidence ({$statement->detection_confidence}%) — manual review recommended";
            $hasWarning = true;
        }

        // Determine status
        $status = 'pass';
        if ($hasWarning) $status = 'warning';
        if ($hasFail) $status = 'fail';

        // Update the statement
        $statement->update([
            'validation_status' => $status,
            'validation_notes' => implode("\n", $notes),
        ]);

        // If warning/fail, seed review queue
        if ($status !== 'pass') {
            $statement->update(['review_status' => 'pending']);
        }

        return ['status' => $status, 'notes' => $notes];
    }
}
