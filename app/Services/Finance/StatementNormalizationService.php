<?php

namespace App\Services\Finance;

use App\Models\MerchantStatement;
use App\Models\StatementPlanSummary;
use App\Models\StatementDeposit;
use App\Models\StatementChargeback;
use App\Models\StatementReserve;
use App\Models\StatementFee;
use Illuminate\Support\Facades\DB;

/**
 * Takes parsed normalized array data and writes it into the database.
 */
class StatementNormalizationService
{
    /**
     * Write all parsed sections into DB records for a statement.
     */
    public static function normalize(MerchantStatement $statement, array $parsed): void
    {
        DB::transaction(function () use ($statement, $parsed) {
            // Clear existing child data (for reparse)
            $statement->planSummaries()->delete();
            $statement->deposits()->delete();
            $statement->chargebacks()->delete();
            $statement->reserves()->delete();
            $statement->fees()->delete();

            // 1. Update statement header + summary totals
            $header = $parsed['header'] ?? [];
            $summary = $parsed['summary'] ?? [];
            $meta = $parsed['_meta'] ?? [];

            $statementMonth = $header['statement_month'] ?? $statement->statement_month;

            $statement->update([
                'statement_month' => $statementMonth ?: $statement->statement_month,
                'gross_sales' => $summary['gross_sales'] ?? 0,
                'credits' => $summary['credits'] ?? 0,
                'net_sales' => $summary['net_sales'] ?? 0,
                'discount_due' => $summary['discount_due'] ?? 0,
                'discount_paid' => $summary['discount_paid'] ?? 0,
                'fees_due' => $summary['fees_due'] ?? 0,
                'fees_paid' => $summary['fees_paid'] ?? 0,
                'net_fees_due' => ($summary['fees_due'] ?? 0) - ($summary['fees_paid'] ?? 0),
                'amount_deducted' => $summary['amount_deducted'] ?? 0,
                'total_deposits' => $summary['total_deposits'] ?? 0,
                'total_chargebacks' => $summary['total_chargebacks'] ?? 0,
                'total_reversals' => $summary['total_reversals'] ?? 0,
                'reserve_ending_balance' => $summary['reserve_ending_balance'] ?? 0,
                'parsed_json' => $parsed,
                'detected_processor' => $meta['detected_processor'] ?? null,
                'detection_confidence' => $meta['detection_confidence'] ?? 0,
                'parser_version' => $meta['parser_version'] ?? null,
                'processor_id' => $meta['processor_id'] ?? $statement->processor_id,
            ]);

            // 2. Plan summaries (chunk insert for performance)
            $planRows = [];
            foreach ($parsed['plan_summaries'] ?? [] as $plan) {
                $planRows[] = array_merge($plan, [
                    'statement_id' => $statement->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach (array_chunk($planRows, 100) as $chunk) {
                StatementPlanSummary::insert($chunk);
            }

            // 3. Deposits
            $depositDate = self::resolveMonthBase($statementMonth);
            $depositRows = [];
            foreach ($parsed['deposits'] ?? [] as $dep) {
                $day = $dep['deposit_day'] ?? null;
                $depositRows[] = [
                    'statement_id' => $statement->id,
                    'deposit_day' => $day,
                    'deposit_date' => $day && $depositDate ? $depositDate->copy()->day($day)->toDateString() : null,
                    'reference_number' => $dep['reference_number'] ?? null,
                    'batch_id' => $dep['batch_id'] ?? null,
                    'tran_code' => $dep['tran_code'] ?? null,
                    'plan_code' => $dep['plan_code'] ?? null,
                    'sales_count' => $dep['sales_count'] ?? 0,
                    'sales_amount' => $dep['sales_amount'] ?? 0,
                    'credits_amount' => $dep['credits_amount'] ?? 0,
                    'discount_paid' => $dep['discount_paid'] ?? 0,
                    'net_deposit' => $dep['net_deposit'] ?? 0,
                    'raw_row_text' => $dep['raw_row_text'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($depositRows, 100) as $chunk) {
                StatementDeposit::insert($chunk);
            }

            // 4. Chargebacks
            $cbRows = [];
            foreach ($parsed['chargebacks'] ?? [] as $cb) {
                $day = $cb['chargeback_day'] ?? null;
                $cbRows[] = [
                    'statement_id' => $statement->id,
                    'chargeback_day' => $day,
                    'chargeback_date' => $day && $depositDate ? $depositDate->copy()->day($day)->toDateString() : null,
                    'reference_number' => $cb['reference_number'] ?? null,
                    'tran_code' => $cb['tran_code'] ?? null,
                    'card_brand' => $cb['card_brand'] ?? null,
                    'reason_code' => $cb['reason_code'] ?? null,
                    'case_number' => $cb['case_number'] ?? null,
                    'amount' => $cb['amount'] ?? 0,
                    'event_type' => $cb['event_type'] ?? 'chargeback',
                    'recovered_flag' => $cb['recovered_flag'] ?? false,
                    'raw_row_text' => $cb['raw_row_text'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($cbRows, 100) as $chunk) {
                StatementChargeback::insert($chunk);
            }

            // 5. Reserves
            $resRows = [];
            foreach ($parsed['reserves'] ?? [] as $res) {
                $day = $res['reserve_day'] ?? null;
                $resRows[] = [
                    'statement_id' => $statement->id,
                    'reserve_day' => $day,
                    'reserve_date' => $day && $depositDate ? $depositDate->copy()->day($day)->toDateString() : null,
                    'reserve_amount' => $res['reserve_amount'] ?? 0,
                    'release_amount' => $res['release_amount'] ?? 0,
                    'running_balance' => $res['running_balance'] ?? 0,
                    'raw_row_text' => $res['raw_row_text'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($resRows, 100) as $chunk) {
                StatementReserve::insert($chunk);
            }

            // 6. Fees
            $feeRows = [];
            foreach ($parsed['fees'] ?? [] as $fee) {
                $feeRows[] = [
                    'statement_id' => $statement->id,
                    'fee_description' => $fee['fee_description'] ?? 'Unknown Fee',
                    'fee_category' => $fee['fee_category'] ?? 'misc',
                    'quantity' => $fee['quantity'] ?? 0,
                    'basis_amount' => $fee['basis_amount'] ?? 0,
                    'rate' => $fee['rate'] ?? null,
                    'fee_total' => $fee['fee_total'] ?? 0,
                    'raw_row_text' => $fee['raw_row_text'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($feeRows, 100) as $chunk) {
                StatementFee::insert($chunk);
            }
        });
    }

    private static function resolveMonthBase(?string $month): ?\Carbon\Carbon
    {
        if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) return null;
        try {
            return \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
