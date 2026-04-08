<?php

namespace App\Services\Finance;

use App\Models\MerchantChargeback;
use App\Models\MerchantFinancialEntry;
use App\Models\MerchantImportBatch;
use App\Models\MerchantImportFailure;
use App\Models\MerchantStatementLineItem;
use App\Models\MerchantStatementUpload;
use App\Models\MerchantTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Imports parsed/confirmed statement line items into merchant_transactions,
 * merchant_chargebacks, and merchant_financial_entries.
 */
class StatementImportService
{
    public static function import(MerchantStatementUpload $upload, int $userId, bool $skipReview = false): array
    {
        $midId = $upload->merchant_account_id;
        if (!$midId) {
            return ['success' => false, 'error' => 'No merchant account assigned to this upload'];
        }

        $lineItems = $upload->lineItems()
            ->when(!$skipReview, fn($q) => $q->where('needs_review', false))
            ->get();

        $batch = MerchantImportBatch::create([
            'merchant_statement_upload_id' => $upload->id,
            'merchant_account_id' => $midId,
            'import_type' => 'full',
            'total_rows' => $lineItems->count(),
            'status' => 'processing',
            'created_by' => $userId,
        ]);

        $imported = 0;
        $failed = 0;
        $duplicates = 0;

        foreach ($lineItems as $line) {
            try {
                $result = self::importLine($line, $midId, $upload->id, $batch->id);
                if ($result === 'imported') {
                    $imported++;
                } elseif ($result === 'duplicate') {
                    $duplicates++;
                }
            } catch (\Throwable $e) {
                $failed++;
                MerchantImportFailure::create([
                    'merchant_import_batch_id' => $batch->id,
                    'row_number' => $line->id,
                    'error_type' => 'import_error',
                    'error_message' => $e->getMessage(),
                    'row_data_json' => $line->raw_line_json,
                ]);
            }
        }

        $batch->update([
            'imported_rows' => $imported,
            'failed_rows' => $failed,
            'duplicate_rows' => $duplicates,
            'status' => $failed === $lineItems->count() ? 'failed' : 'completed',
        ]);

        $upload->update(['processing_status' => 'imported']);

        return [
            'success' => true,
            'batch_id' => $batch->id,
            'total' => $lineItems->count(),
            'imported' => $imported,
            'failed' => $failed,
            'duplicates' => $duplicates,
        ];
    }

    private static function importLine(MerchantStatementLineItem $line, int $midId, int $uploadId, int $batchId): string
    {
        $type = $line->line_type;

        if ($type === 'transaction' || $type === 'refund') {
            return self::importTransaction($line, $midId, $uploadId, $batchId);
        }

        if ($type === 'chargeback') {
            return self::importChargeback($line, $midId, $uploadId, $batchId);
        }

        // fee, reserve_hold, reserve_release, payout, deposit, adjustment
        return self::importFinancialEntry($line, $midId, $uploadId);
    }

    private static function importTransaction(MerchantStatementLineItem $line, int $midId, int $uploadId, int $batchId): string
    {
        // Duplicate check
        if ($line->external_reference) {
            $exists = MerchantTransaction::where('merchant_account_id', $midId)
                ->where('external_transaction_id', $line->external_reference)
                ->exists();
            if ($exists) return 'duplicate';
        }

        $raw = $line->raw_line_json ?? [];
        $status = 'approved';
        if ($line->line_type === 'refund' || ($line->amount ?? 0) < 0) $status = 'refunded';
        if ($line->mapped_status) $status = self::normalizeTransactionStatus($line->mapped_status);
        if (!empty($raw['status'])) $status = self::normalizeTransactionStatus($raw['status']);

        MerchantTransaction::create([
            'merchant_account_id' => $midId,
            'statement_upload_id' => $uploadId,
            'external_transaction_id' => $line->external_reference ?? ($raw['reference'] ?? null),
            'customer_name' => $raw['customer_name'] ?? null,
            'card_brand' => $raw['card_brand'] ?? null,
            'last4' => $raw['last4'] ?? null,
            'amount' => $line->amount ?? 0,
            'currency' => $line->currency ?? 'USD',
            'transaction_status' => $status,
            'transaction_type' => $raw['type'] ?? (($line->amount ?? 0) < 0 ? 'refund' : 'sale'),
            'transaction_date' => $line->transaction_date ?? now()->toDateString(),
            'source_type' => 'statement_import',
            'source_batch_id' => $batchId,
            'raw_data_json' => $raw,
        ]);

        return 'imported';
    }

    private static function importChargeback(MerchantStatementLineItem $line, int $midId, int $uploadId, int $batchId): string
    {
        $raw = $line->raw_line_json ?? [];

        // Duplicate check by reference
        $cbRef = $line->external_reference ?? ($raw['reference'] ?? null);
        if ($cbRef) {
            $exists = MerchantChargeback::where('merchant_account_id', $midId)
                ->where('external_chargeback_id', $cbRef)
                ->exists();
            if ($exists) return 'duplicate';
        }

        // Try to link to original transaction
        $txnId = null;
        $origRef = $raw['original_transaction_ref'] ?? null;
        if ($origRef) {
            $txnId = MerchantTransaction::where('merchant_account_id', $midId)
                ->where('external_transaction_id', $origRef)
                ->value('id');
        }

        // Parse due date from AI data
        $dueDate = $raw['due_date'] ?? null;
        if ($dueDate) {
            try { $dueDate = (new \DateTime($dueDate))->format('Y-m-d'); } catch (\Throwable) { $dueDate = null; }
        }

        MerchantChargeback::create([
            'merchant_account_id' => $midId,
            'merchant_transaction_id' => $txnId,
            'statement_upload_id' => $uploadId,
            'external_chargeback_id' => $cbRef ?? 'CB-' . $line->id,
            'amount' => abs($line->amount ?? 0),
            'currency' => $line->currency ?? 'USD',
            'card_brand' => $raw['card_brand'] ?? null,
            'reason_code' => $raw['reason_code'] ?? null,
            'reason_description' => $raw['reason_description'] ?? $line->description,
            'internal_status' => self::normalizeChargebackStatus($raw['status'] ?? 'new'),
            'opened_at' => $line->transaction_date,
            'due_at' => $dueDate,
            'raw_data_json' => $raw,
        ]);

        return 'imported';
    }

    private static function normalizeChargebackStatus(?string $raw): string
    {
        if (!$raw) return 'new';
        $lower = strtolower(trim($raw));
        return match (true) {
            str_contains($lower, 'won') || str_contains($lower, 'reversed') => 'won',
            str_contains($lower, 'lost') => 'lost',
            str_contains($lower, 'pending') || str_contains($lower, 'response') => 'pending_response',
            str_contains($lower, 'evidence') || str_contains($lower, 'submitted') => 'evidence_submitted',
            str_contains($lower, 'open') => 'open',
            str_contains($lower, 'closed') => 'closed',
            default => 'new',
        };
    }

    private static function importFinancialEntry(MerchantStatementLineItem $line, int $midId, int $uploadId): string
    {
        MerchantFinancialEntry::create([
            'merchant_account_id' => $midId,
            'statement_upload_id' => $uploadId,
            'entry_type' => $line->line_type,
            'category' => self::categorizeEntry($line->line_type, $line->description ?? ''),
            'description' => $line->description,
            'amount' => $line->amount ?? 0,
            'currency' => $line->currency ?? 'USD',
            'entry_date' => $line->transaction_date,
            'external_reference' => $line->external_reference,
            'raw_data_json' => $line->raw_line_json,
        ]);

        return 'imported';
    }

    private static function normalizeTransactionStatus(string $raw): string
    {
        $lower = strtolower(trim($raw));
        return match (true) {
            str_contains($lower, 'approve') || str_contains($lower, 'success') => 'approved',
            str_contains($lower, 'decline') || str_contains($lower, 'denied') => 'declined',
            str_contains($lower, 'settle') => 'settled',
            str_contains($lower, 'refund') => 'refunded',
            str_contains($lower, 'void') || str_contains($lower, 'reverse') => 'reversed',
            str_contains($lower, 'pending') => 'pending',
            str_contains($lower, 'chargeback') => 'chargeback',
            default => 'approved',
        };
    }

    private static function categorizeEntry(string $type, string $description): string
    {
        $lower = strtolower($description);
        if ($type === 'fee') {
            if (str_contains($lower, 'chargeback')) return 'chargeback_fee';
            if (str_contains($lower, 'monthly')) return 'monthly_fee';
            if (str_contains($lower, 'interchange')) return 'interchange_fee';
            if (str_contains($lower, 'processing')) return 'processing_fee';
            if (str_contains($lower, 'pci')) return 'pci_fee';
            return 'processing_fee';
        }
        return $type;
    }
}
