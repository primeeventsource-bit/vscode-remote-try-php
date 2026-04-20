<?php

namespace App\Livewire\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantStatement;
use App\Models\LiveChargeback;
use App\Models\FinanceManualExpense;
use App\Models\FinanceAdjustment;
use App\Models\FinanceReviewItem;
use App\Models\FinanceAuditLog;
use App\Services\Finance\FinanceMetricsService;
use App\Services\Finance\ProfitCalculatorService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

class Dashboard extends Component
{
    use WithPagination, WithFileUploads;

    // ── Filters ──────────────────────────────────────────
    public ?int $merchantAccountId = null;
    public ?string $selectedMonth = null;
    public string $activeTab = 'overview';

    // ── Upload Modal ─────────────────────────────────────
    public bool $showUploadModal = false;
    public $uploadFile = null;
    public ?int $uploadMerchantId = null;
    public ?string $uploadMonth = null;

    // ── Live Chargeback Modal ────────────────────────────
    public bool $showCbModal = false;
    public ?int $cbMerchantId = null;
    public ?string $cbReferenceNumber = null;
    public ?float $cbAmount = null;
    public ?string $cbStatus = 'open';
    public ?string $cbEventType = 'chargeback';
    public ?string $cbCardBrand = null;
    public ?string $cbReasonCode = null;
    public ?string $cbNotes = null;
    public ?string $cbDisputeDate = null;
    public ?string $cbDeadlineDate = null;

    // ── Expense Modal ────────────────────────────────────
    public bool $showExpenseModal = false;
    public ?int $expMerchantId = null;
    public ?string $expDate = null;
    public ?string $expCategory = 'misc';
    public ?string $expDescription = null;
    public ?float $expAmount = null;
    public bool $expRecurring = false;
    public ?string $expNotes = null;

    // ── Table Filters ────────────────────────────────────
    public string $depositSearch = '';
    public string $cbSearch = '';
    public string $feeSearch = '';
    public string $cbTab = 'statement';

    protected $queryString = ['activeTab', 'merchantAccountId', 'selectedMonth'];

    public function mount()
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) {
            abort(403);
        }

        // Default to current month
        if (!$this->selectedMonth) {
            $this->selectedMonth = now()->format('Y-m');
        }

        // Resolve tab from route name
        $routeName = request()->route()?->getName();
        if ($routeName) {
            $this->activeTab = match ($routeName) {
                'finance.statements' => 'statements',
                'finance.deposits' => 'deposits',
                'finance.chargebacks' => 'chargebacks',
                'finance.fees' => 'fees',
                'finance.reserves' => 'reserves',
                'finance.profit' => 'profit',
                'finance.mids' => 'mids',
                'finance.review-queue' => 'review',
                'finance.expenses' => 'expenses',
                'finance.settings' => 'settings',
                default => 'overview',
            };
        }
    }

    // ── Computed Data ────────────────────────────────────
    public function getKpisProperty(): array
    {
        try {
            return FinanceMetricsService::getKpis([
                'merchant_account_id' => $this->merchantAccountId,
                'month' => $this->selectedMonth,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Finance KPIs failed: ' . $e->getMessage());
            return [
                'gross_volume' => 0, 'credits' => 0, 'net_sales' => 0, 'net_deposits' => 0,
                'discount_fees' => 0, 'other_fees' => 0, 'total_fees' => 0,
                'total_chargebacks' => 0, 'total_reversals' => 0, 'net_chargeback_loss' => 0,
                'live_chargeback_exposure' => 0, 'chargeback_exposure' => 0, 'reserve_balance' => 0,
                'chargeback_ratio_pct' => 0, 'recovery_rate_pct' => 0, 'statement_count' => 0,
            ];
        }
    }

    public function getProfitDataProperty(): array
    {
        try {
            if (!$this->merchantAccountId || !$this->selectedMonth) {
                $results = ProfitCalculatorService::calculateAll($this->selectedMonth ?? now()->format('Y-m'));
                if (empty($results)) return [];

                $totals = [
                    'gross_sales' => 0, 'credits' => 0, 'net_sales' => 0, 'net_deposits' => 0,
                    'discount_fees' => 0, 'other_processor_fees' => 0, 'total_processor_fees' => 0,
                    'total_chargebacks' => 0, 'total_reversals' => 0, 'net_chargeback_loss' => 0,
                    'dispute_fees' => 0, 'payroll_cost' => 0, 'operating_expenses' => 0,
                    'adjustments' => 0, 'true_net_profit' => 0,
                ];
                foreach ($results as $r) {
                    foreach ($totals as $key => &$val) {
                        $val += $r[$key] ?? 0;
                    }
                }
                $totals['profit_margin_pct'] = $totals['gross_sales'] > 0
                    ? round(($totals['true_net_profit'] / $totals['gross_sales']) * 100, 4) : 0;
                return $totals;
            }

            return ProfitCalculatorService::calculate($this->merchantAccountId, $this->selectedMonth);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Finance profit data failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getDailyDepositsProperty(): array
    {
        try {
            return FinanceMetricsService::getDailyDeposits([
                'merchant_account_id' => $this->merchantAccountId,
                'month' => $this->selectedMonth,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Finance daily deposits failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getCardBreakdownProperty(): array
    {
        try {
            return FinanceMetricsService::getCardBreakdown([
                'merchant_account_id' => $this->merchantAccountId,
                'month' => $this->selectedMonth,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Finance card breakdown failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getFeeBreakdownProperty(): array
    {
        try {
            return FinanceMetricsService::getFeeBreakdown([
                'merchant_account_id' => $this->merchantAccountId,
                'month' => $this->selectedMonth,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Finance fee breakdown failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getMerchantsProperty()
    {
        try {
            return MerchantAccount::active()->with('processor')->orderBy('name')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function getAvailableMonthsProperty(): array
    {
        try {
            return MerchantStatement::completed()
                ->distinct()
                ->orderByDesc('statement_month')
                ->pluck('statement_month')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ── Actions ──────────────────────────────────────────
    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function uploadStatement()
    {
        $this->validate([
            'uploadFile' => 'required|file|mimes:pdf|max:20480',
            'uploadMerchantId' => 'required|exists:merchant_accounts,id',
            'uploadMonth' => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        $user = auth()->user();
        $file = $this->uploadFile;
        $merchantId = $this->uploadMerchantId;
        $month = $this->uploadMonth;

        // Check duplicate
        $existing = MerchantStatement::where('merchant_account_id', $merchantId)
            ->where('statement_month', $month)
            ->where('ai_parse_status', 'completed')
            ->first();

        if ($existing) {
            $this->addError('uploadFile', 'A completed statement already exists for this MID + month.');
            return;
        }

        // Store
        $path = $file->store('finance/statements/' . $merchantId, 'local');
        $filename = $file->getClientOriginalName();

        // Extract text — ALL pages
        $rawText = '';
        $fullPath = storage_path('app/' . $path);

        // Method 1: pdftotext (extracts all pages by default)
        try {
            $output = [];
            $exitCode = 1;
            exec('pdftotext -layout ' . escapeshellarg($fullPath) . ' -', $output, $exitCode);
            if ($exitCode === 0 && !empty($output)) {
                $rawText = implode("\n", $output);
            }
        } catch (\Throwable $e) {}

        // Method 2: smalot/pdfparser — extract page-by-page to guarantee ordering
        if (empty($rawText)) {
            try {
                if (class_exists(\Smalot\PdfParser\Parser::class)) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($fullPath);
                    $pages = $pdf->getPages();
                    $pageTexts = [];
                    foreach ($pages as $i => $page) {
                        $pageTexts[] = $page->getText();
                    }
                    $rawText = implode("\n\n", $pageTexts);
                }
            } catch (\Throwable $e) {}
        }

        if (empty($rawText)) {
            $this->addError('uploadFile', 'Could not extract text from PDF. The file may be image-based.');
            return;
        }

        // Log page count for debugging
        $pageCount = substr_count($rawText, "\f") + 1;
        \Illuminate\Support\Facades\Log::info('Finance PDF extracted', [
            'file' => $filename,
            'pages_detected' => $pageCount,
            'text_length' => strlen($rawText),
        ]);

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

        FinanceAuditLog::record($statement, 'uploaded', $user->id, ['notes' => "Uploaded: $filename"]);

        // Run parse synchronously so results appear immediately
        $job = new \App\Jobs\Finance\ParseMerchantStatementJob($statement->id);
        $job->handle();

        // Refresh statement to get updated parse status
        $statement->refresh();
        $parseStatus = $statement->ai_parse_status;

        $this->showUploadModal = false;
        $this->reset(['uploadFile', 'uploadMerchantId', 'uploadMonth']);

        if ($parseStatus === 'completed') {
            $cbCount = $statement->chargebacks()->count();
            $depCount = $statement->deposits()->count();
            $feeCount = $statement->fees()->count();
            session()->flash('success', "Statement parsed: {$depCount} deposits, {$cbCount} chargebacks, {$feeCount} fees found.");
        } else {
            session()->flash('success', "Statement uploaded — parse status: {$parseStatus}. Check Statements tab for details.");
        }
    }

    public function saveLiveChargeback()
    {
        $this->validate([
            'cbMerchantId' => 'required|exists:merchant_accounts,id',
            'cbAmount' => 'required|numeric|min:0.01',
            'cbStatus' => 'required|in:' . implode(',', LiveChargeback::STATUSES),
        ]);

        $user = auth()->user();

        $cb = LiveChargeback::create([
            'merchant_account_id' => $this->cbMerchantId,
            'reference_number' => $this->cbReferenceNumber,
            'amount' => $this->cbAmount,
            'status' => $this->cbStatus,
            'event_type' => $this->cbEventType ?? 'chargeback',
            'card_brand' => $this->cbCardBrand,
            'reason_code' => $this->cbReasonCode,
            'notes' => $this->cbNotes,
            'dispute_date' => $this->cbDisputeDate,
            'deadline_date' => $this->cbDeadlineDate,
            'processor_id' => MerchantAccount::find($this->cbMerchantId)?->processor_id,
            'created_by' => $user->id,
        ]);

        FinanceAuditLog::record($cb, 'created', $user->id);

        $this->showCbModal = false;
        $this->reset(['cbMerchantId', 'cbReferenceNumber', 'cbAmount', 'cbStatus', 'cbEventType', 'cbCardBrand', 'cbReasonCode', 'cbNotes', 'cbDisputeDate', 'cbDeadlineDate']);
        session()->flash('success', 'Live chargeback recorded.');
    }

    public function saveExpense()
    {
        $this->validate([
            'expDate' => 'required|date',
            'expDescription' => 'required|string|max:255',
            'expAmount' => 'required|numeric|min:0.01',
            'expCategory' => 'required|in:' . implode(',', FinanceManualExpense::CATEGORIES),
        ]);

        $user = auth()->user();

        FinanceManualExpense::create([
            'merchant_account_id' => $this->expMerchantId,
            'expense_date' => $this->expDate,
            'category' => $this->expCategory,
            'description' => $this->expDescription,
            'amount' => $this->expAmount,
            'is_recurring' => $this->expRecurring,
            'notes' => $this->expNotes,
            'created_by' => $user->id,
        ]);

        $this->showExpenseModal = false;
        $this->reset(['expMerchantId', 'expDate', 'expCategory', 'expDescription', 'expAmount', 'expRecurring', 'expNotes']);
        session()->flash('success', 'Expense recorded.');
    }

    public function reparseStatement(int $id)
    {
        $stmt = MerchantStatement::findOrFail($id);
        $stmt->update(['ai_parse_status' => 'pending']);

        // Run synchronously so results appear immediately
        $job = new \App\Jobs\Finance\ParseMerchantStatementJob($id);
        $job->handle();

        session()->flash('success', 'Statement reparsed successfully.');
    }

    public function reparseAll()
    {
        $statements = MerchantStatement::query()
            ->when($this->merchantAccountId, fn($q) => $q->where('merchant_account_id', $this->merchantAccountId))
            ->when($this->selectedMonth, fn($q, $m) => $q->where('statement_month', $m))
            ->get();

        $count = 0;
        foreach ($statements as $stmt) {
            $stmt->update(['ai_parse_status' => 'pending']);
            $job = new \App\Jobs\Finance\ParseMerchantStatementJob($stmt->id);
            $job->handle();
            $count++;
        }

        session()->flash('success', "$count statement(s) reparsed with updated parser.");
    }

    public function render()
    {
        try {
            $data = $this->buildTabData();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Finance dashboard render error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            $data = [];
        }

        // Provide empty paginators for any data not loaded (blade expects them)
        $empty = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25, 1, ['path' => request()->url()]);
        $data = array_merge([
            'statements' => $empty,
            'deposits' => $empty,
            'statementChargebacks' => $empty,
            'liveChargebacks' => $empty,
            'fees' => $empty,
            'reserves' => $empty,
            'reviewItems' => $empty,
            'expenses' => $empty,
        ], $data);

        return view('livewire.finance.dashboard', $data)
            ->layout('components.layouts.app', ['title' => 'Finance Command Center']);
    }

    private function buildTabData(): array
    {
        $data = [];

        if ($this->activeTab === 'statements') {
            $data['statements'] = MerchantStatement::with(['merchantAccount', 'uploader'])
                ->when($this->merchantAccountId, fn($q) => $q->where('merchant_account_id', $this->merchantAccountId))
                ->orderByDesc('statement_month')
                ->paginate(15, ['*'], 'stmtPage');
        }

        if ($this->activeTab === 'deposits') {
            $data['deposits'] = \App\Models\StatementDeposit::query()
                ->join('merchant_statements', 'statement_deposits.statement_id', '=', 'merchant_statements.id')
                ->where('merchant_statements.ai_parse_status', 'completed')
                ->when($this->merchantAccountId, fn($q) => $q->where('merchant_statements.merchant_account_id', $this->merchantAccountId))
                ->when($this->selectedMonth, fn($q, $m) => $q->where('merchant_statements.statement_month', $m))
                ->when($this->depositSearch, fn($q, $s) => $q->where('statement_deposits.reference_number', 'like', "%$s%"))
                ->select('statement_deposits.*')
                ->orderBy('statement_deposits.deposit_date')
                ->paginate(25, ['*'], 'depPage');
        }

        if ($this->activeTab === 'chargebacks') {
            $data['statementChargebacks'] = \App\Models\StatementChargeback::query()
                ->join('merchant_statements', 'statement_chargebacks.statement_id', '=', 'merchant_statements.id')
                ->where('merchant_statements.ai_parse_status', 'completed')
                ->when($this->merchantAccountId, fn($q) => $q->where('merchant_statements.merchant_account_id', $this->merchantAccountId))
                ->when($this->selectedMonth, fn($q, $m) => $q->where('merchant_statements.statement_month', $m))
                ->when($this->cbSearch, fn($q, $s) => $q->where('statement_chargebacks.reference_number', 'like', "%$s%"))
                ->select('statement_chargebacks.*')
                ->orderByDesc('statement_chargebacks.chargeback_date')
                ->paginate(25, ['*'], 'scbPage');

            $data['liveChargebacks'] = LiveChargeback::query()
                ->when($this->merchantAccountId, fn($q) => $q->where('merchant_account_id', $this->merchantAccountId))
                ->when($this->cbSearch, fn($q, $s) => $q->where('reference_number', 'like', "%$s%"))
                ->orderByDesc('created_at')
                ->paginate(25, ['*'], 'lcbPage');
        }

        if ($this->activeTab === 'fees') {
            $data['fees'] = \App\Models\StatementFee::query()
                ->join('merchant_statements', 'statement_fees.statement_id', '=', 'merchant_statements.id')
                ->where('merchant_statements.ai_parse_status', 'completed')
                ->when($this->merchantAccountId, fn($q) => $q->where('merchant_statements.merchant_account_id', $this->merchantAccountId))
                ->when($this->selectedMonth, fn($q, $m) => $q->where('merchant_statements.statement_month', $m))
                ->when($this->feeSearch, fn($q, $s) => $q->where('statement_fees.fee_description', 'like', "%$s%"))
                ->select('statement_fees.*')
                ->orderBy('statement_fees.fee_category')
                ->paginate(25, ['*'], 'feePage');
        }

        if ($this->activeTab === 'reserves') {
            $data['reserves'] = \App\Models\StatementReserve::query()
                ->join('merchant_statements', 'statement_reserves.statement_id', '=', 'merchant_statements.id')
                ->where('merchant_statements.ai_parse_status', 'completed')
                ->when($this->merchantAccountId, fn($q) => $q->where('merchant_statements.merchant_account_id', $this->merchantAccountId))
                ->when($this->selectedMonth, fn($q, $m) => $q->where('merchant_statements.statement_month', $m))
                ->select('statement_reserves.*')
                ->orderBy('statement_reserves.reserve_date')
                ->paginate(25, ['*'], 'resPage');
        }

        if ($this->activeTab === 'review') {
            $data['reviewItems'] = FinanceReviewItem::with('statement')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'revPage');
        }

        if ($this->activeTab === 'expenses') {
            $data['expenses'] = FinanceManualExpense::with('merchantAccount')
                ->when($this->merchantAccountId, fn($q) => $q->where('merchant_account_id', $this->merchantAccountId))
                ->orderByDesc('expense_date')
                ->paginate(20, ['*'], 'expPage');
        }

        return $data;
    }
}
