<?php

namespace App\Jobs\Finance;

use App\Models\MerchantStatement;
use App\Models\FinanceAuditLog;
use App\Services\Finance\StatementParserManager;
use App\Services\Finance\StatementNormalizationService;
use App\Services\Finance\StatementValidationService;
use App\Services\Finance\ChargebackLinkingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Parses a merchant statement PDF's extracted text.
 * Queue-safe, retry-safe, idempotent.
 */
class ParseMerchantStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $statementId,
        public ?string $overrideProcessorSlug = null,
    ) {
        $this->onQueue('finance');
    }

    public function handle(): void
    {
        $statement = MerchantStatement::find($this->statementId);
        if (!$statement) {
            Log::warning('ParseMerchantStatementJob: Statement not found', ['id' => $this->statementId]);
            return;
        }

        // Idempotency: skip if already completed and not a forced reparse
        if ($statement->ai_parse_status === 'completed' && !$this->overrideProcessorSlug) {
            return;
        }

        $statement->update(['ai_parse_status' => 'processing']);

        try {
            $rawText = $statement->raw_text;
            if (empty($rawText)) {
                $statement->update([
                    'ai_parse_status' => 'failed',
                    'validation_notes' => 'No raw text available for parsing.',
                ]);
                return;
            }

            // Log text stats for debugging multi-page issues
            $textLength = strlen($rawText);
            $pageBreaks = substr_count($rawText, "\f");
            Log::info('ParseMerchantStatementJob: Starting parse', [
                'statement_id' => $this->statementId,
                'text_length' => $textLength,
                'form_feed_page_breaks' => $pageBreaks,
                'estimated_pages' => $pageBreaks + 1,
            ]);

            // Parse
            $parsed = StatementParserManager::parseStatement(
                $rawText,
                $statement->id,
                $this->overrideProcessorSlug
            );

            // Normalize into DB
            StatementNormalizationService::normalize($statement, $parsed);

            // Log parse results for debugging
            Log::info('ParseMerchantStatementJob: Parse complete', [
                'statement_id' => $this->statementId,
                'deposits_found' => count($parsed['deposits'] ?? []),
                'chargebacks_found' => count($parsed['chargebacks'] ?? []),
                'fees_found' => count($parsed['fees'] ?? []),
                'reserves_found' => count($parsed['reserves'] ?? []),
                'plans_found' => count($parsed['plan_summaries'] ?? []),
                'confidence' => $parsed['confidence'] ?? 0,
                'processor' => $parsed['_meta']['parser_slug'] ?? 'unknown',
            ]);

            // Link chargebacks to reversals
            ChargebackLinkingService::linkReversals($statement);

            // Update merchant account header if new info found
            $this->updateMerchantAccountFromHeader($statement, $parsed['header'] ?? []);

            // Mark completed
            $statement->update([
                'ai_parse_status' => 'completed',
                'parsed_at' => now(),
            ]);

            // Audit
            FinanceAuditLog::record($statement, 'parsed', null, [
                'notes' => "Parser: {$parsed['_meta']['parser_slug']} v{$parsed['_meta']['parser_version']}",
            ]);

            // Run validation + profit snapshot synchronously
            $validateJob = new ValidateMerchantStatementJob($this->statementId);
            $validateJob->handle();

            $rebuildJob = new RebuildMerchantProfitSnapshotsJob(
                $statement->merchant_account_id,
                $statement->statement_month
            );
            $rebuildJob->handle();

        } catch (\Throwable $e) {
            Log::error('ParseMerchantStatementJob failed', [
                'statement_id' => $this->statementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statement->update([
                'ai_parse_status' => 'failed',
                'validation_notes' => 'Parse error: ' . $e->getMessage(),
            ]);

            throw $e; // Let queue retry
        }
    }

    private function updateMerchantAccountFromHeader(MerchantStatement $statement, array $header): void
    {
        $merchant = $statement->merchantAccount;
        if (!$merchant) return;

        $updates = [];
        if (!empty($header['merchant_number']) && empty($merchant->merchant_number)) {
            $updates['merchant_number'] = $header['merchant_number'];
        }
        if (!empty($header['association_number']) && empty($merchant->association_number)) {
            $updates['association_number'] = $header['association_number'];
        }
        if (!empty($header['routing_last4']) && empty($merchant->routing_last4)) {
            $updates['routing_last4'] = $header['routing_last4'];
        }
        if (!empty($header['deposit_account_last4']) && empty($merchant->deposit_account_last4)) {
            $updates['deposit_account_last4'] = $header['deposit_account_last4'];
        }

        if (!empty($updates)) {
            $merchant->update($updates);
        }
    }
}
