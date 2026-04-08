<?php

namespace App\Livewire\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantStatementUpload;
use App\Services\Finance\StatementImportService;
use App\Services\Finance\StatementIngestionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Statement Upload')]
class StatementUpload extends Component
{
    use WithFileUploads;

    public $file;
    public string $midFilter = '';
    public ?int $previewUploadId = null;
    public string $tab = 'upload'; // upload, preview, history

    // Notification state (persistent across re-renders, unlike session flash)
    public string $successMessage = '';
    public string $errorMessage = '';

    public function processStatement()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'file' => 'required|file|max:20480|mimes:pdf,csv,txt,xlsx,xls',
        ]);

        if (!$this->file) {
            $this->errorMessage = 'Please select a file first.';
            return;
        }

        try {
            $user = auth()->user();
            $originalName = $this->file->getClientOriginalName();
            $mimeType = $this->file->getMimeType();
            $fileSize = $this->file->getSize();

            // Read file content BEFORE storing — the temp file is guaranteed accessible
            $rawContent = file_get_contents($this->file->getRealPath());

            // Store to default disk (works on Laravel Cloud, local, S3, etc.)
            $path = $this->file->store('merchant-statements');

            $upload = MerchantStatementUpload::create([
                'merchant_account_id' => $this->midFilter ?: null,
                'original_filename' => $originalName,
                'file_path' => $path,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
                'processing_status' => 'pending',
            ]);

            // Process with pre-read content (bypasses storage read issues)
            $result = StatementIngestionService::processUploadWithContent($upload, $rawContent);

            $this->previewUploadId = $upload->id;
            $this->file = null;

            if ($result['success']) {
                $msg = "Statement parsed successfully: {$result['line_count']} lines found, {$result['review_count']} need review.";

                if (!empty($result['auto_created_mid'])) {
                    $mid = $result['auto_created_mid'];
                    $msg .= " New MID auto-created: {$mid['account_name']} ({$mid['mid_number']}) — {$mid['processor']}.";
                }

                $det = $result['detection'] ?? [];
                if (!empty($det['processor'])) $msg .= " Processor: {$det['processor']}.";
                if (!empty($det['mid_number'])) $msg .= " MID: {$det['mid_number']}.";
                if (!empty($det['business_name'])) $msg .= " Business: {$det['business_name']}.";

                $this->successMessage = $msg;
                $this->tab = 'preview';
            } else {
                $this->errorMessage = 'Failed to parse statement: ' . ($result['error'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Upload failed: ' . $e->getMessage();
        }
    }

    public function confirmImport()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (!$this->previewUploadId) {
            $this->errorMessage = 'No statement to import.';
            return;
        }

        try {
            $upload = MerchantStatementUpload::find($this->previewUploadId);
            if (!$upload) {
                $this->errorMessage = 'Upload not found.';
                return;
            }

            if (!$upload->merchant_account_id) {
                $this->errorMessage = 'Please assign a merchant account before importing.';
                return;
            }

            $result = StatementImportService::import($upload, auth()->id());

            if ($result['success']) {
                $this->successMessage = "Import complete: {$result['imported']} rows imported. {$result['duplicates']} duplicates skipped. {$result['failed']} failures.";
                $this->tab = 'history';
                $this->previewUploadId = null;
            } else {
                $this->errorMessage = 'Import failed: ' . ($result['error'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Import error: ' . $e->getMessage();
        }
    }

    public function assignMid(int $uploadId, string $midId)
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            MerchantStatementUpload::where('id', $uploadId)->update(['merchant_account_id' => (int) $midId]);
            $this->successMessage = 'Merchant account assigned.';
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Failed to assign MID.';
        }
    }

    public function clearMessages()
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function reparse(int $uploadId)
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $upload = MerchantStatementUpload::findOrFail($uploadId);

            // Delete old line items and summary so we get fresh results
            $upload->lineItems()->delete();
            if ($upload->summary) {
                $upload->summary->delete();
            }

            $upload->update(['processing_status' => 'pending']);

            $result = StatementIngestionService::processUpload($upload);

            if ($result['success']) {
                $this->successMessage = "Re-parsed successfully: {$result['line_count']} lines found, {$result['review_count']} need review.";
                $this->previewUploadId = $upload->id;
                $this->tab = 'preview';
            } else {
                $this->errorMessage = 'Re-parse failed: ' . ($result['error'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Re-parse error: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('master_admin') && !$user->hasPerm('view_finance'))) abort(403);

        $mids = collect();
        $previewUpload = null;
        $previewLines = collect();
        $history = collect();

        try {
            $mids = MerchantAccount::active()->orderBy('account_name')->get();

            $previewUpload = $this->previewUploadId ? MerchantStatementUpload::with(['summary', 'lineItems', 'merchantAccount'])->find($this->previewUploadId) : null;
            $previewLines = $previewUpload ? $previewUpload->lineItems()->orderBy('id')->get() : collect();

            $history = MerchantStatementUpload::with(['merchantAccount', 'summary'])
                ->orderByDesc('uploaded_at')
                ->limit(25)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.finance.statement-upload', compact('mids', 'previewUpload', 'previewLines', 'history'));
    }
}
