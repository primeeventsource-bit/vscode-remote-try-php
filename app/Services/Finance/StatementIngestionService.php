<?php

namespace App\Services\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantStatementLineItem;
use App\Models\MerchantStatementSummary;
use App\Models\MerchantStatementUpload;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrates the full statement ingestion pipeline:
 * 1. Upload → 2. Detect format → 3. Parse/Extract → 4. Normalize → 5. Preview → 6. Import
 */
class StatementIngestionService
{
    /**
     * Process with pre-read content (bypasses storage read issues on cloud).
     */
    public static function processUploadWithContent(MerchantStatementUpload $upload, string $rawContent): array
    {
        return self::doProcess($upload, $rawContent);
    }

    /**
     * Process by reading from storage (fallback).
     */
    public static function processUpload(MerchantStatementUpload $upload): array
    {
        $content = self::readFileContent($upload);
        if (!$content) {
            $upload->update(['processing_status' => 'failed']);
            return ['success' => false, 'error' => 'Unable to read file content'];
        }
        return self::doProcess($upload, $content);
    }

    private static function doProcess(MerchantStatementUpload $upload, string $content): array
    {
        $upload->update(['processing_status' => 'processing']);

        try {
            if (!$content || strlen($content) === 0) {
                $upload->update(['processing_status' => 'failed']);
                return ['success' => false, 'error' => 'File content is empty'];
            }

            // For PDFs: extract text from binary content
            $isPdf = str_contains($upload->mime_type ?? '', 'pdf') || str_ends_with(strtolower($upload->original_filename), '.pdf');
            if ($isPdf) {
                $content = self::extractTextFromPdf($content);
                if (!$content || strlen(trim($content)) < 20) {
                    // PDF text extraction failed — try sending raw to AI as last resort
                    $content = 'PDF file: ' . $upload->original_filename . '. Unable to extract text locally. File size: ' . strlen($content) . ' bytes.';
                }
            }

            // Step 2: Detect processor/format (deterministic first)
            $detection = StatementFormatDetector::detect($content, $upload->original_filename, $upload->mime_type);

            // Step 2b: Always try AI extraction — it's smarter about field identification
            if (strlen($content) > 50) {
                try {
                    $aiData = self::aiAssistedDetection($content, $upload->original_filename);
                    if (!empty($aiData)) {
                        // AI merchant_info is more reliable for processor/business/owner
                        // AI OVERRIDES deterministic for these fields since deterministic gets confused
                        if (!empty($aiData['processor_name'])) $detection['processor'] = $aiData['processor_name'];
                        if (!empty($aiData['business_name'])) $detection['business_name'] = $aiData['business_name'];
                        if (!empty($aiData['descriptor'])) $detection['descriptor'] = $aiData['descriptor'];
                        if (!empty($aiData['owner_name'])) $detection['owner_name'] = $aiData['owner_name'];

                        // AI fills gaps for other fields
                        $detection['mid_number'] = $detection['mid_number'] ?? ($aiData['mid_number'] ?? null);
                        $detection['gateway_name'] = $detection['gateway_name'] ?? ($aiData['gateway_name'] ?? null);
                        $detection['currency'] = $detection['currency'] ?? ($aiData['currency'] ?? 'USD');
                        $detection['account_name'] = $detection['account_name'] ?? ($aiData['business_name'] ?? null);

                        // If AI found data, boost confidence
                        if ($aiData['mid_number'] ?? null) {
                            $detection['confidence'] = max($detection['confidence'], 0.75);
                        }

                        // Store AI-extracted summary data for later use
                        $detection['ai_summary'] = $aiData;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $upload->update([
                'detected_processor' => $detection['processor'],
                'detected_statement_type' => $detection['statement_type'],
                'confidence_score' => $detection['confidence'],
            ]);

            // Auto-match or auto-create MID from statement data
            if (!$upload->merchant_account_id) {
                $mid = self::resolveOrCreateMerchantAccount($detection, $upload->uploaded_by);
                if ($mid) {
                    $upload->update(['merchant_account_id' => $mid->id]);
                }
            }

            // Step 3: Parse content into structured data
            $parsed = self::parseContent($upload, $content, $detection);

            // Step 4: Create summary
            if (!empty($parsed['summary'])) {
                self::createSummary($upload, $parsed['summary']);
            }

            // Step 5: Create line items for preview
            // Store ALL extracted fields in raw_line_json so the importer can use them
            $warnings = [];
            $lineItems = [];
            foreach ($parsed['lines'] ?? [] as $line) {
                // Build enriched raw data — includes AI-extracted fields like
                // reason_code, due_date, card_brand, last4, category, etc.
                $rawData = $line['raw'] ?? [];
                foreach (['card_brand', 'last4', 'reason_code', 'reason_description', 'due_date', 'original_transaction_ref', 'category', 'status', 'type', 'customer_name', 'reference'] as $extraField) {
                    if (isset($line[$extraField]) && !isset($rawData[$extraField])) {
                        $rawData[$extraField] = $line[$extraField];
                    }
                }

                $item = MerchantStatementLineItem::create([
                    'merchant_statement_upload_id' => $upload->id,
                    'merchant_account_id' => $upload->merchant_account_id,
                    'line_type' => $line['type'] ?? 'transaction',
                    'external_reference' => $line['reference'] ?? null,
                    'transaction_date' => $line['date'] ?? null,
                    'description' => $line['description'] ?? null,
                    'amount' => $line['amount'] ?? 0,
                    'currency' => $line['currency'] ?? 'USD',
                    'mapped_status' => $line['status'] ?? null,
                    'raw_line_json' => $rawData,
                    'confidence_score' => $line['confidence'] ?? $detection['confidence'],
                    'needs_review' => ($line['confidence'] ?? 1) < 0.7,
                ]);
                $lineItems[] = $item;

                if ($item->needs_review) {
                    $warnings[] = "Line #{$item->id}: Low confidence ({$item->confidence_score}) — review needed";
                }
            }

            $upload->update([
                'processing_status' => 'parsed',
                'processed_at' => now(),
            ]);

            // Check if a MID was auto-created
            $autoCreatedMid = null;
            if ($upload->merchant_account_id) {
                $midModel = MerchantAccount::find($upload->merchant_account_id);
                if ($midModel && $midModel->notes === 'Auto-created from statement upload') {
                    $autoCreatedMid = [
                        'id' => $midModel->id,
                        'account_name' => $midModel->account_name,
                        'mid_number' => $midModel->mid_number,
                        'processor' => $midModel->processor_name,
                    ];
                }
            }

            return [
                'success' => true,
                'upload_id' => $upload->id,
                'detection' => $detection,
                'summary' => $parsed['summary'] ?? null,
                'line_count' => count($lineItems),
                'review_count' => collect($lineItems)->where('needs_review', true)->count(),
                'warnings' => $warnings,
                'auto_created_mid' => $autoCreatedMid,
            ];
        } catch (\Throwable $e) {
            $upload->update(['processing_status' => 'failed']);
            report($e);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse file content based on mime type.
     */
    private static function parseContent(MerchantStatementUpload $upload, string $content, array $detection): array
    {
        $mime = $upload->mime_type;

        if (str_contains($mime, 'csv') || str_ends_with($upload->original_filename, '.csv')) {
            return StatementCsvParser::parse($content, $detection);
        }

        if (str_contains($mime, 'pdf')) {
            return StatementPdfParser::parse($upload->file_path, $content, $detection);
        }

        // Fallback: try CSV-style parsing on plain text
        if (str_contains($mime, 'text') || str_contains($mime, 'plain')) {
            return StatementCsvParser::parse($content, $detection);
        }

        return ['summary' => null, 'lines' => []];
    }

    private static function createSummary(MerchantStatementUpload $upload, array $summary): void
    {
        if (!$upload->merchant_account_id) return;

        MerchantStatementSummary::create([
            'merchant_statement_upload_id' => $upload->id,
            'merchant_account_id' => $upload->merchant_account_id,
            'statement_start_date' => $summary['start_date'] ?? null,
            'statement_end_date' => $summary['end_date'] ?? null,
            'gross_volume' => $summary['gross_volume'] ?? null,
            'net_volume' => $summary['net_volume'] ?? null,
            'refunds_total' => $summary['refunds'] ?? null,
            'chargebacks_total' => $summary['chargebacks'] ?? null,
            'fees_total' => $summary['fees'] ?? null,
            'reserves_total' => $summary['reserves'] ?? null,
            'payouts_total' => $summary['payouts'] ?? null,
            'ending_balance' => $summary['ending_balance'] ?? null,
            'raw_summary_json' => $summary,
        ]);
    }

    /**
     * Find existing MerchantAccount by MID number, or create a new one
     * from the detected statement data.
     */
    private static function resolveOrCreateMerchantAccount(array $detection, int $userId): ?MerchantAccount
    {
        // 1. Try to match by MID number
        if (!empty($detection['mid_number'])) {
            $existing = MerchantAccount::where('mid_number', $detection['mid_number'])->first();
            if ($existing) return $existing;
        }

        // 2. Try to match by business name + processor combo
        if (!empty($detection['business_name']) && !empty($detection['processor'])) {
            $existing = MerchantAccount::where('business_name', $detection['business_name'])
                ->where('processor_name', $detection['processor'])
                ->first();
            if ($existing) return $existing;
        }

        // 3. Not enough info to create — need at least MID number or business name
        if (empty($detection['mid_number']) && empty($detection['business_name'])) {
            return null;
        }

        // 4. Auto-create new MerchantAccount from statement data
        $midNumber = $detection['mid_number'] ?? ('AUTO-' . now()->format('YmdHis'));
        $accountName = $detection['account_name']
            ?? $detection['business_name']
            ?? $detection['descriptor']
            ?? ('MID ' . $midNumber);

        // Use DB::table to insert — bypasses $fillable and handles both old and new column schemas
        $data = [
            'account_name' => $accountName,
            'mid_number' => $midNumber,
            'processor_name' => $detection['processor'] ?? 'Unknown',
            'gateway_name' => $detection['gateway_name'] ?? null,
            'descriptor' => $detection['descriptor'] ?? null,
            'business_name' => $detection['business_name'] ?? null,
            'account_status' => 'active',
            'currency' => $detection['currency'] ?? 'USD',
            'is_active' => true,
            'notes' => 'Auto-created from statement upload',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Old table has required 'name', 'mid_masked', 'active' columns
        if (\Illuminate\Support\Facades\Schema::hasColumn('merchant_accounts', 'name')) {
            $data['name'] = $accountName;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('merchant_accounts', 'mid_masked')) {
            $data['mid_masked'] = $midNumber;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('merchant_accounts', 'active')) {
            $data['active'] = true;
        }

        $id = \Illuminate\Support\Facades\DB::table('merchant_accounts')->insertGetId($data);
        return MerchantAccount::find($id);
    }

    /**
     * AI-assisted detection — delegates to StatementAiExtractor and flattens merchant_info.
     */
    public static function aiAssistedDetection(string $content, string $filename): array
    {
        try {
            $result = StatementAiExtractor::extract($content, $filename, []);
            $info = $result['merchant_info'] ?? [];

            // Flatten merchant_info to top level for easy merging
            return array_merge($info, [
                'mid_number' => $info['mid_number'] ?? null,
                'processor_name' => $info['processor_name'] ?? null,
                'business_name' => $info['business_name'] ?? null,
                'owner_name' => $info['owner_name'] ?? null,
                'descriptor' => $info['descriptor'] ?? null,
                'gateway_name' => $info['gateway_name'] ?? null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Extract readable text from PDF binary content.
     */
    private static function extractTextFromPdf(string $binaryContent): string
    {
        $text = '';

        // Method 1: Try writing to temp file and using pdftotext (if installed)
        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'stmt_');
            file_put_contents($tmpFile, $binaryContent);
            $txtFile = $tmpFile . '.txt';

            // Try pdftotext (poppler-utils)
            exec("pdftotext -layout '{$tmpFile}' '{$txtFile}' 2>/dev/null", $output, $exitCode);
            if ($exitCode === 0 && file_exists($txtFile)) {
                $text = file_get_contents($txtFile);
                @unlink($txtFile);
                @unlink($tmpFile);
                if (strlen(trim($text)) > 50) return $text;
            }
            @unlink($txtFile);
            @unlink($tmpFile);
        } catch (\Throwable) {}

        // Method 2: Regex extraction of text streams from PDF binary
        // PDF text is stored between BT...ET blocks with Tj/TJ operators
        try {
            // Extract text between stream...endstream
            if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $binaryContent, $streams)) {
                foreach ($streams[1] as $stream) {
                    // Try to decompress FlateDecode streams
                    $decoded = @gzuncompress($stream);
                    if (!$decoded) $decoded = @gzinflate($stream);
                    if (!$decoded) $decoded = $stream;

                    // Extract text from Tj and TJ operators
                    if (preg_match_all('/\(([^)]+)\)\s*Tj/s', $decoded, $tjMatches)) {
                        $text .= implode(' ', $tjMatches[1]) . "\n";
                    }
                    if (preg_match_all('/\[([^\]]+)\]\s*TJ/s', $decoded, $tjMatches)) {
                        foreach ($tjMatches[1] as $arr) {
                            if (preg_match_all('/\(([^)]*)\)/', $arr, $parts)) {
                                $text .= implode('', $parts[1]) . "\n";
                            }
                        }
                    }
                }
            }

            // Also grab any readable ASCII strings from the PDF
            if (strlen($text) < 100) {
                if (preg_match_all('/([A-Za-z0-9\s\$\.,\-\/\#\@\:]{10,200})/', $binaryContent, $ascii)) {
                    $text .= implode("\n", array_unique($ascii[1]));
                }
            }
        } catch (\Throwable) {}

        return $text;
    }

    private static function readFileContent(MerchantStatementUpload $upload): ?string
    {
        $path = $upload->file_path;

        // Try every possible storage location
        $attempts = [
            fn() => Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null,
            fn() => Storage::disk('local')->exists('public/' . $path) ? Storage::disk('local')->get('public/' . $path) : null,
            fn() => Storage::exists($path) ? Storage::get($path) : null,
            fn() => file_exists(storage_path('app/' . $path)) ? file_get_contents(storage_path('app/' . $path)) : null,
            fn() => file_exists(storage_path('app/private/' . $path)) ? file_get_contents(storage_path('app/private/' . $path)) : null,
            fn() => file_exists($path) ? file_get_contents($path) : null,
        ];

        foreach ($attempts as $attempt) {
            try {
                $content = $attempt();
                if ($content && strlen($content) > 0) return $content;
            } catch (\Throwable) {}
        }

        return null;
    }
}
