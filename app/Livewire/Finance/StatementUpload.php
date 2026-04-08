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
    public array $previewResult = [];
    public string $tab = 'upload'; // upload, preview, history

    public function upload()
    {
        $this->validate([
            'file' => 'required|file|max:20480|mimes:pdf,csv,txt,xlsx,xls',
        ]);

        $user = auth()->user();
        $originalName = $this->file->getClientOriginalName();
        $path = $this->file->store('merchant-statements', 'local');

        $upload = MerchantStatementUpload::create([
            'merchant_account_id' => $this->midFilter ?: null,
            'original_filename' => $originalName,
            'file_path' => $path,
            'mime_type' => $this->file->getMimeType(),
            'file_size' => $this->file->getSize(),
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'processing_status' => 'pending',
        ]);

        // Process immediately (could be queued in production)
        $result = StatementIngestionService::processUpload($upload);

        $this->previewUploadId = $upload->id;
        $this->previewResult = $result;
        $this->tab = 'preview';
        $this->file = null;

        if ($result['success']) {
            $msg = "Statement parsed: {$result['line_count']} lines found, {$result['review_count']} need review.";

            if (!empty($result['auto_created_mid'])) {
                $mid = $result['auto_created_mid'];
                $msg .= " New MID auto-created: {$mid['account_name']} ({$mid['mid_number']}) — {$mid['processor']}.";
            }

            $det = $result['detection'] ?? [];
            if (!empty($det['processor'])) $msg .= " Processor: {$det['processor']}.";
            if (!empty($det['mid_number'])) $msg .= " MID: {$det['mid_number']}.";
            if (!empty($det['business_name'])) $msg .= " Business: {$det['business_name']}.";

            session()->flash('finance_success', $msg);
        } else {
            session()->flash('finance_error', 'Failed to parse statement: ' . ($result['error'] ?? 'Unknown error'));
        }
    }

    public function confirmImport()
    {
        if (!$this->previewUploadId) return;

        $upload = MerchantStatementUpload::find($this->previewUploadId);
        if (!$upload) return;

        if (!$upload->merchant_account_id) {
            session()->flash('finance_error', 'Please assign a merchant account before importing.');
            return;
        }

        $result = StatementImportService::import($upload, auth()->id());

        if ($result['success']) {
            session()->flash('finance_success', "Imported {$result['imported']} rows. {$result['duplicates']} duplicates skipped. {$result['failed']} failures.");
            $this->tab = 'history';
            $this->previewUploadId = null;
            $this->previewResult = [];
        } else {
            session()->flash('finance_error', 'Import failed: ' . ($result['error'] ?? 'Unknown error'));
        }
    }

    public function assignMid(int $uploadId, int $midId)
    {
        MerchantStatementUpload::where('id', $uploadId)->update(['merchant_account_id' => $midId]);
        session()->flash('finance_success', 'Merchant account assigned.');
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin') && !$user->hasPerm('view_finance')) abort(403);

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
