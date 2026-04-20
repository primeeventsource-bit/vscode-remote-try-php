<?php

namespace App\Http\Controllers;

use App\Models\MerchantStatement;
use App\Models\MerchantAccount;
use App\Models\FinanceAuditLog;
use App\Jobs\Finance\ParseMerchantStatementJob;
use App\Services\Finance\FinanceExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FinanceStatementController extends Controller
{
    /**
     * Upload a merchant statement PDF.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
            'merchant_account_id' => 'required|exists:merchant_accounts,id',
            'statement_month' => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        $user = $request->user();
        if (!$user->hasRole('master_admin', 'admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $merchantId = $request->integer('merchant_account_id');
        $month = $request->input('statement_month');

        // Check for duplicate
        $existing = MerchantStatement::where('merchant_account_id', $merchantId)
            ->where('statement_month', $month)
            ->first();

        if ($existing && $existing->ai_parse_status === 'completed') {
            return response()->json([
                'error' => 'A statement already exists for this MID + month. Use reparse to update.',
                'statement_id' => $existing->id,
            ], 409);
        }

        // Store file
        $file = $request->file('file');
        $path = $file->store('finance/statements/' . $merchantId, 'local');
        $filename = $file->getClientOriginalName();

        // Extract text from PDF
        $rawText = $this->extractPdfText(storage_path('app/' . $path));

        if (empty($rawText)) {
            return response()->json(['error' => 'Could not extract text from PDF. The file may be scanned/image-based and requires OCR.'], 422);
        }

        // Create or update statement record
        $statement = MerchantStatement::updateOrCreate(
            ['merchant_account_id' => $merchantId, 'statement_month' => $month],
            [
                'processor_id' => MerchantAccount::find($merchantId)?->processor_id,
                'upload_filename' => $filename,
                'upload_file_path' => $path,
                'raw_text' => $rawText,
                'ai_parse_status' => 'pending',
                'validation_status' => 'pending',
                'review_status' => 'none',
                'uploaded_by' => $user->id,
            ]
        );

        // Audit
        FinanceAuditLog::record($statement, 'uploaded', $user->id, [
            'notes' => "Uploaded: $filename",
        ]);

        // Parse synchronously so results appear immediately
        $job = new ParseMerchantStatementJob($statement->id);
        $job->handle();

        $statement->refresh();

        return response()->json([
            'success' => true,
            'statement_id' => $statement->id,
            'parse_status' => $statement->ai_parse_status,
            'deposits' => $statement->deposits()->count(),
            'chargebacks' => $statement->chargebacks()->count(),
            'fees' => $statement->fees()->count(),
            'message' => 'Statement uploaded and parsed.',
        ]);
    }

    /**
     * Reparse an existing statement.
     */
    public function reparse(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user->hasRole('master_admin', 'admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $statement = MerchantStatement::findOrFail($id);
        $overrideSlug = $request->input('processor_slug');

        FinanceAuditLog::record($statement, 'reparse_requested', $user->id, [
            'notes' => 'Manual reparse triggered' . ($overrideSlug ? " with override: $overrideSlug" : ''),
        ]);

        $statement->update(['ai_parse_status' => 'pending']);

        $job = new ParseMerchantStatementJob($statement->id, $overrideSlug);
        $job->handle();

        $statement->refresh();

        return response()->json(['success' => true, 'message' => 'Reparse queued.']);
    }

    /**
     * Export finance data to CSV.
     */
    public function export(Request $request)
    {
        $user = $request->user();
        if (!$user->hasRole('master_admin', 'admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $type = $request->input('type', 'deposits');
        $filters = $request->only(['merchant_account_id', 'month']);

        $csv = match ($type) {
            'deposits' => FinanceExportService::exportDeposits($filters),
            'chargebacks' => FinanceExportService::exportChargebacks($filters),
            'live_chargebacks' => FinanceExportService::exportChargebacks($filters, 'live'),
            'fees' => FinanceExportService::exportFees($filters),
            'profit' => FinanceExportService::exportProfit($filters),
            default => '',
        };

        $filename = "finance_{$type}_" . now()->format('Y-m-d_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    /**
     * Extract text from PDF using available tools.
     */
    private function extractPdfText(string $filePath): string
    {
        // Try pdftotext (poppler-utils / spatie/pdf-to-text)
        try {
            $output = [];
            $exitCode = 0;
            exec('pdftotext -layout ' . escapeshellarg($filePath) . ' -', $output, $exitCode);
            if ($exitCode === 0 && !empty($output)) {
                return implode("\n", $output);
            }
        } catch (\Throwable $e) {
            // pdftotext not available
        }

        // Try PHP-native with smalot/pdfparser if available
        try {
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                return $pdf->getText();
            }
        } catch (\Throwable $e) {
            // smalot/pdfparser not available
        }

        // Fallback: try Tesseract OCR
        try {
            $output = [];
            exec('tesseract ' . escapeshellarg($filePath) . ' stdout pdf', $output, $exitCode);
            if ($exitCode === 0 && !empty($output)) {
                return implode("\n", $output);
            }
        } catch (\Throwable $e) {
            // Tesseract not available
        }

        return '';
    }
}
