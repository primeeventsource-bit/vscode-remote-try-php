<?php

namespace App\Livewire;

use App\Models\ChargebackCase;
use App\Models\ChargebackEvidence;
use App\Models\ClientAuditLog;
use App\Models\CrmNote;
use App\Models\Deal;
use App\Models\User;
use App\Services\ClientAuditService;
use App\Services\CrmNoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Clients')]
class Clients extends Component
{
    use WithFileUploads;

    // ── List state ──────────────────────────────────────────────
    public string $search = '';
    public string $statusTab = 'all';
    public ?int $selectedClient = null;

    // ── Detail / editing state ──────────────────────────────────
    public string $activeTab = 'info'; // info | deal_sheet | banking | payment | audit | chargebacks
    public bool $editing = false;

    // Editable form arrays per section
    public array $clientForm = [];
    public array $dealSheetForm = [];
    public array $bankingForm = [];
    public array $paymentForm = [];

    // ── Chargeback case state ──────────────────────────────────
    public bool $showCreateCase = false;
    public array $caseForm = [];
    public ?int $selectedCaseId = null;
    public $evidenceUpload = null; // file upload
    public string $uploadDocType = '';
    public bool $showSendCaseToAdmin = false;
    public string $sendCaseAdminRecipientId = '';
    public string $sendCaseAdminMessage = '';

    // ── Permission cache (computed per-request) ─────────────────
    public bool $canEdit = false;
    public bool $canViewDealSheet = false;
    public bool $canEditDealSheet = false;
    public bool $canViewBanking = false;
    public bool $canEditBanking = false;
    public bool $canViewPayment = false;
    public bool $canEditPayment = false;
    public bool $canViewSensitiveFinancial = false;
    public bool $canEditSensitiveFinancial = false;
    public bool $canViewAudit = false;

    // ── Notes ───────────────────────────────────────────────────
    public string $clientNoteBody = '';
    public ?int $clientEditingNoteId = null;
    public string $clientEditingNoteBody = '';
    public ?int $clientSendNoteId = null;
    public string $clientSendNoteRecipientId = '';
    public string $clientSendNoteMessage = '';

    // ── Success / error flash ───────────────────────────────────
    public string $flashMessage = '';
    public string $flashType = 'success'; // success | error

    public function selectClient($id)
    {
        if ($this->selectedClient === $id) {
            $this->selectedClient = null;
            $this->editing = false;
            return;
        }

        $this->selectedClient = $id;
        $this->activeTab = 'info';
        $this->editing = false;
        $this->flashMessage = '';

        $this->loadClientForms();
    }

    public function setTab(string $tab)
    {
        $deal = $this->getActiveDeal();
        if (!$deal) return;

        $user = auth()->user();

        // Backend permission check before allowing tab access
        if ($tab === 'deal_sheet' && !Gate::allows('viewDealSheet', $deal)) return;
        if ($tab === 'banking' && !Gate::allows('viewBanking', $deal)) return;
        if ($tab === 'payment' && !Gate::allows('viewPaymentProfile', $deal)) return;
        if ($tab === 'audit' && !Gate::allows('viewAuditLogs', $deal)) return;
        if ($tab === 'chargebacks' && !$user->hasRole('master_admin', 'admin')) return;

        $this->activeTab = $tab;
        $this->selectedCaseId = null; // reset case selection when switching tabs
        $this->editing = false;

        // Audit: log sensitive section views
        if (in_array($tab, ['banking', 'payment', 'audit'])) {
            ClientAuditService::logView($user, $deal, $tab);
        }
    }

    public function startEditing()
    {
        $deal = $this->getActiveDeal();
        if (!$deal) return;

        // Check permission for current section
        $allowed = match ($this->activeTab) {
            'info' => Gate::allows('edit', $deal),
            'deal_sheet' => Gate::allows('editDealSheet', $deal),
            'banking' => Gate::allows('editBanking', $deal),
            'payment' => Gate::allows('editPaymentProfile', $deal),
            default => false,
        };

        if (!$allowed) {
            $this->flash('You do not have permission to edit this section.', 'error');
            return;
        }

        $this->editing = true;
    }

    public function cancelEditing()
    {
        $this->editing = false;
        $this->loadClientForms();
    }

    /**
     * Save changes for the currently active section.
     * All authorization is enforced server-side before any DB write.
     */
    public function saveSection()
    {
        $deal = $this->getActiveDeal();
        if (!$deal) return;

        $user = auth()->user();

        try {
            DB::beginTransaction();

            $wrote = match ($this->activeTab) {
                'info' => $this->saveClientInfo($user, $deal),
                'deal_sheet' => $this->saveDealSheet($user, $deal),
                'banking' => $this->saveBanking($user, $deal),
                'payment' => $this->savePayment($user, $deal),
                default => false,
            };

            DB::commit();

            $this->editing = false;
            $this->loadClientForms();

            // Only confirm success if a DB write actually happened and committed
            if ($wrote) {
                $this->flash('Changes saved successfully.');
            } else {
                $this->flash('No changes detected.', 'error');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            $this->flash('Failed to save changes: ' . $e->getMessage(), 'error');
        }
    }

    // ── Section save methods ────────────────────────────────────

    private function saveClientInfo(User $user, Deal $deal): bool
    {
        if (!Gate::allows('edit', $deal)) {
            throw new \RuntimeException('Unauthorized: cannot edit client info.');
        }

        $validated = $this->validateSection($this->clientForm, [
            'owner_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'primary_phone' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:50',
            'mailing_address' => 'nullable|string|max:255',
            'city_state_zip' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'status' => 'nullable|string|max:50',
            'assigned_admin' => 'nullable|integer|exists:users,id',
        ]);

        return $this->applyAndAudit($user, $deal, 'client_info', $validated, Deal::CLIENT_INFO_FIELDS);
    }

    private function saveDealSheet(User $user, Deal $deal): bool
    {
        if (!Gate::allows('editDealSheet', $deal)) {
            throw new \RuntimeException('Unauthorized: cannot edit deal sheet.');
        }

        $validated = $this->validateSection($this->dealSheetForm, [
            'fee' => 'nullable|numeric|min:0|max:9999999.99',
            'weeks' => 'nullable|string|max:100',
            'asking_rental' => 'nullable|string|max:255',
            'resort_name' => 'nullable|string|max:255',
            'resort_city_state' => 'nullable|string|max:255',
            'exchange_group' => 'nullable|string|max:255',
            'bed_bath' => 'nullable|string|max:50',
            'usage' => 'nullable|string|max:100',
            'asking_sale_price' => 'nullable|string|max:255',
            'using_timeshare' => 'nullable|string|max:255',
            'looking_to_get_out' => 'nullable|string|max:255',
            'verification_num' => 'nullable|string|max:255',
            'fronter' => 'nullable|integer|exists:users,id',
            'closer' => 'nullable|integer|exists:users,id',
            'was_vd' => 'nullable|string|in:Yes,No',
            'snr' => 'nullable|string|max:50',
            'merchant' => 'nullable|string|max:255',
        ]);

        return $this->applyAndAudit($user, $deal, 'deal_sheet', $validated, Deal::DEAL_SHEET_FIELDS);
    }

    private function saveBanking(User $user, Deal $deal): bool
    {
        if (!Gate::allows('editBanking', $deal)) {
            throw new \RuntimeException('Unauthorized: cannot edit banking info.');
        }

        $validated = $this->validateSection($this->bankingForm, [
            'bank' => 'nullable|string|max:255',
            'bank2' => 'nullable|string|max:255',
            'billing_address' => 'nullable|string|max:255',
        ]);

        return $this->applyAndAudit($user, $deal, 'banking', $validated, Deal::BANKING_FIELDS);
    }

    private function savePayment(User $user, Deal $deal): bool
    {
        if (!Gate::allows('editPaymentProfile', $deal)) {
            throw new \RuntimeException('Unauthorized: cannot edit payment profile.');
        }

        $validated = $this->validateSection($this->paymentForm, [
            'name_on_card' => 'nullable|string|max:255',
            'card_type' => 'nullable|string|max:50',
            'card_brand' => 'nullable|string|max:30',
            'exp_date' => 'nullable|string|max:20',
            'card_brand2' => 'nullable|string|max:30',
            'exp_date2' => 'nullable|string|max:20',
        ]);

        // Only allow editing safe payment fields
        $safe = array_intersect_key($validated, array_flip(Deal::EDITABLE_PAYMENT_FIELDS));

        return $this->applyAndAudit($user, $deal, 'payment_profile', $safe, Deal::EDITABLE_PAYMENT_FIELDS);
    }

    // ── Core save + audit helper ────────────────────────────────

    /**
     * @return bool True if a DB write occurred, false if no changes detected.
     */
    private function applyAndAudit(User $user, Deal $deal, string $section, array $newValues, array $allowedFields): bool
    {
        // Only process allowed fields
        $newValues = array_intersect_key($newValues, array_flip($allowedFields));

        // Detect actual changes
        $changed = [];
        $before = [];
        $after = [];

        foreach ($newValues as $field => $value) {
            $oldValue = $deal->getOriginal($field) ?? $deal->getAttribute($field);
            $newStr = (string) ($value ?? '');
            $oldStr = (string) ($oldValue ?? '');

            if ($newStr !== $oldStr) {
                $changed[] = $field;
                $before[$field] = $oldValue;
                $after[$field] = $value;
            }
        }

        if (empty($changed)) return false; // Nothing changed

        // Apply update
        $newValues['last_edited_by'] = $user->id;
        $newValues['last_edited_at'] = now();

        // updated_by column only exists after migration runs
        if (\Schema::hasColumn('deals', 'updated_by')) {
            $newValues['updated_by'] = $user->id;
        }

        $deal->fill($newValues);
        $deal->save();

        // Verify the write actually persisted by re-reading from DB
        $persisted = Deal::find($deal->id);
        foreach ($changed as $field) {
            $expected = (string) ($after[$field] ?? '');
            $actual = (string) ($persisted->getAttributes()[$field] ?? '');
            if ($expected !== $actual) {
                throw new \RuntimeException("Save verification failed: field '{$field}' did not persist.");
            }
        }

        // Audit log
        ClientAuditService::logEdit($user, $deal, $section, $changed, $before, $after);

        return true;
    }

    // ── Validation helper ───────────────────────────────────────

    private function validateSection(array $data, array $rules): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);

        if ($validator->fails()) {
            $firstError = $validator->errors()->first();
            throw new \RuntimeException("Validation failed: {$firstError}");
        }

        return $validator->validated();
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function getActiveDeal(): ?Deal
    {
        if (!$this->selectedClient) return null;
        return Deal::find($this->selectedClient);
    }

    private function loadClientForms(): void
    {
        $deal = $this->getActiveDeal();
        if (!$deal) return;

        $user = auth()->user();
        $this->computePermissions($user, $deal);

        // Client info (always visible to authorized viewers)
        $this->clientForm = $deal->only(Deal::CLIENT_INFO_FIELDS);

        // Deal sheet
        if ($this->canViewDealSheet) {
            $this->dealSheetForm = $deal->only(Deal::DEAL_SHEET_FIELDS);
        }

        // Banking
        if ($this->canViewBanking) {
            $this->bankingForm = $deal->only(Deal::BANKING_FIELDS);
        }

        // Payment profile - only safe fields, never raw card numbers
        if ($this->canViewPayment) {
            $this->paymentForm = $deal->only(Deal::EDITABLE_PAYMENT_FIELDS);
            // Add display-only fields
            $this->paymentForm['masked_card'] = $deal->masked_card;
            $this->paymentForm['masked_card2'] = $deal->masked_card2;
            $this->paymentForm['card_last4'] = $deal->card_last4;
            $this->paymentForm['card_last4_2'] = $deal->card_last4_2;
        }
    }

    private function computePermissions(User $user, Deal $deal): void
    {
        $this->canEdit = Gate::allows('edit', $deal);
        $this->canViewDealSheet = Gate::allows('viewDealSheet', $deal);
        $this->canEditDealSheet = Gate::allows('editDealSheet', $deal);
        $this->canViewBanking = Gate::allows('viewBanking', $deal);
        $this->canEditBanking = Gate::allows('editBanking', $deal);
        $this->canViewPayment = Gate::allows('viewPaymentProfile', $deal);
        $this->canEditPayment = Gate::allows('editPaymentProfile', $deal);
        $this->canViewSensitiveFinancial = Gate::allows('viewSensitiveFinancial', $deal);
        $this->canEditSensitiveFinancial = Gate::allows('editSensitiveFinancial', $deal);
        $this->canViewAudit = Gate::allows('viewAuditLogs', $deal);
    }

    // ── Chargeback case methods ─────────────────────────────

    public function openCreateCase(): void
    {
        $user = auth()->user();
        if (!$user || !Gate::allows('create', ChargebackCase::class)) {
            $this->flash('Unauthorized.', 'error'); return;
        }
        $active = $this->getActiveDeal();
        if (!$active) return;

        $this->caseForm = [
            'case_number' => 'CB-' . strtoupper(substr(md5(now()->timestamp), 0, 8)),
            'card_type' => $active->card_type ?? '',
            'card_brand' => $active->card_brand ?? $active->card_type ?? '',
            'processor_name' => $active->merchant ?? '',
            'reason_code' => '',
            'reason_description' => '',
            'transaction_amount' => $active->fee ?? '',
            'disputed_amount' => $active->fee ?? '',
            'transaction_id' => '',
            'order_id' => $active->verification_num ?? '',
            'response_due_at' => '',
            'sale_date' => $active->charged_date?->format('Y-m-d') ?? '',
            'service_start_date' => $active->timestamp?->format('Y-m-d') ?? '',
            'customer_ip_address' => '',
            'internal_comments' => '',
        ];
        $this->showCreateCase = true;
    }

    public function saveChargebackCase(): void
    {
        $user = auth()->user();
        if (!$user || !Gate::allows('create', ChargebackCase::class)) {
            $this->flash('Unauthorized.', 'error'); return;
        }
        $active = $this->getActiveDeal();
        if (!$active) return;

        if (!trim($this->caseForm['case_number'] ?? '')) {
            $this->flash('Case number is required.', 'error'); return;
        }

        try {
            $cbCase = ChargebackCase::create([
                'client_id' => $active->id,
                'deal_id' => $active->id,
                'case_number' => $this->caseForm['case_number'],
                'card_type' => $this->caseForm['card_type'] ?: null,
                'card_brand' => $this->caseForm['card_brand'] ?: null,
                'processor_name' => $this->caseForm['processor_name'] ?: null,
                'reason_code' => $this->caseForm['reason_code'] ?: null,
                'reason_description' => $this->caseForm['reason_description'] ?: null,
                'transaction_amount' => $this->caseForm['transaction_amount'] ?: null,
                'disputed_amount' => $this->caseForm['disputed_amount'] ?: null,
                'transaction_id' => $this->caseForm['transaction_id'] ?: null,
                'order_id' => $this->caseForm['order_id'] ?: null,
                'response_due_at' => $this->caseForm['response_due_at'] ?: null,
                'sale_date' => $this->caseForm['sale_date'] ?: null,
                'service_start_date' => $this->caseForm['service_start_date'] ?: null,
                'customer_ip_address' => $this->caseForm['customer_ip_address'] ?: null,
                'internal_comments' => $this->caseForm['internal_comments'] ?: null,
                'status' => 'open',
                'created_by_user_id' => $user->id,
            ]);

            // Auto-create task for evidence gathering
            \App\Services\AutomaticTaskService::onChargebackCaseCreated(
                $cbCase->id, $cbCase->case_number, $active->id,
                $active->owner_name ?? 'Client', $user->id
            );

            $this->showCreateCase = false;
            $this->caseForm = [];
            $this->flash('Chargeback case created.');
        } catch (\Throwable $e) {
            report($e);
            $this->flash('Failed to create case: ' . $e->getMessage(), 'error');
        }
    }

    public function selectCase(int $id): void
    {
        $this->selectedCaseId = $this->selectedCaseId === $id ? null : $id;
    }

    public function uploadEvidence(): void
    {
        $user = auth()->user();
        $case = $this->selectedCaseId ? ChargebackCase::find($this->selectedCaseId) : null;
        if (!$user || !$case || !Gate::allows('upload', $case)) {
            $this->flash('Unauthorized.', 'error'); return;
        }

        if (!$this->uploadDocType || !$this->evidenceUpload) {
            $this->flash('Select document type and file.', 'error'); return;
        }

        $this->validate([
            'evidenceUpload' => 'file|max:10240|mimes:pdf,png,jpg,jpeg',
        ]);

        $file = $this->evidenceUpload;
        $storedName = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('chargeback-evidence/' . $case->id, $storedName, 'local');

        // Replace existing doc of same type
        ChargebackEvidence::where('chargeback_case_id', $case->id)
            ->where('document_type', $this->uploadDocType)
            ->delete();

        ChargebackEvidence::create([
            'chargeback_case_id' => $case->id,
            'document_type' => $this->uploadDocType,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
            'uploaded_by_user_id' => $user->id,
        ]);

        $this->evidenceUpload = null;
        $this->uploadDocType = '';
        $this->flash('Evidence uploaded.');
    }

    public function verifyEvidence(int $evidenceId): void
    {
        $user = auth()->user();
        $evidence = ChargebackEvidence::find($evidenceId);
        if (!$evidence || !$user) return;
        $case = ChargebackCase::find($evidence->chargeback_case_id);
        if (!$case || !Gate::allows('verify', $case)) return;

        $evidence->update([
            'status' => 'verified',
            'verified_by_user_id' => $user->id,
            'verified_at' => now(),
        ]);
        $this->flash('Evidence verified.');
    }

    public function updateCaseStatus(int $caseId, string $status): void
    {
        $user = auth()->user();
        $case = ChargebackCase::find($caseId);
        if (!$case || !$user || !Gate::allows('update', $case)) return;

        $data = ['status' => $status, 'updated_by_user_id' => $user->id];
        if ($status === 'submitted') $data['submitted_at'] = now();
        if (in_array($status, ['won', 'lost'])) $data['resolved_at'] = now();

        $case->update($data);
        $this->flash('Case status updated to ' . $status . '.');
    }

    // ── Send Case to Admin ─────────────────────────────────────

    public function openSendCaseToAdmin(): void
    {
        $user = auth()->user();
        $case = $this->selectedCaseId ? ChargebackCase::find($this->selectedCaseId) : null;
        if (!$user || !$case || !Gate::allows('sendToChat', $case)) {
            $this->flash('Unauthorized.', 'error'); return;
        }
        $this->showSendCaseToAdmin = true;
        $this->sendCaseAdminRecipientId = '';
        $this->sendCaseAdminMessage = '';
    }

    public function sendCaseToAdmin(): void
    {
        $user = auth()->user();
        $case = $this->selectedCaseId ? ChargebackCase::with('evidence')->find($this->selectedCaseId) : null;
        if (!$user || !$case || !Gate::allows('sendToChat', $case)) {
            $this->flash('Unauthorized.', 'error'); return;
        }

        if (!$this->sendCaseAdminRecipientId) {
            $this->flash('Select a recipient.', 'error'); return;
        }

        // HARD RULE: recipient must be admin or master_admin
        $recipient = User::where('id', (int) $this->sendCaseAdminRecipientId)
            ->whereIn('role', ['admin', 'master_admin'])
            ->first();

        if (!$recipient) {
            $this->flash('Recipient must be an Admin or Master Admin.', 'error');
            return;
        }

        try {
            $client = $case->client;
            $readiness = $case->readiness;

            // Build chat message
            $chat = \App\Models\Chat::where('type', 'dm')->get()->first(function ($c) use ($user, $recipient) {
                $m = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                $ids = array_map('intval', array_values($m));
                sort($ids);
                $t = [$user->id, $recipient->id];
                sort($t);
                return $ids === $t;
            });

            if (!$chat) {
                $chat = \App\Models\Chat::create([
                    'name' => $recipient->name ?? 'Direct Message',
                    'type' => 'dm',
                    'members' => [$user->id, $recipient->id],
                    'created_by' => $user->id,
                ]);
            }

            $text = "📋 Chargeback Case Sent\n";
            $text .= "Case: {$case->case_number}\n";
            $text .= "Client: " . ($client->owner_name ?? 'Unknown') . "\n";
            $text .= "Amount: \${$case->disputed_amount}\n";
            $text .= "Reason: {$case->reason_code} — {$case->reason_description}\n";
            $text .= "Deadline: " . ($case->response_due_at?->format('M j, Y') ?? 'None') . "\n";
            $text .= "Evidence: {$readiness['uploaded']}/{$readiness['total']} uploaded";
            if ($readiness['missing'] > 0) {
                $text .= " ({$readiness['missing']} missing)";
            }
            $text .= "\nSent by: {$user->name}";

            if (trim($this->sendCaseAdminMessage)) {
                $text .= "\n\nNote: " . trim($this->sendCaseAdminMessage);
            }

            \App\Models\Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $user->id,
                'message_type' => 'text',
                'text' => $text,
            ]);
            $chat->update(['updated_at' => now()]);

            $this->showSendCaseToAdmin = false;
            $this->sendCaseAdminRecipientId = '';
            $this->sendCaseAdminMessage = '';
            $this->flash('Case sent to ' . $recipient->name . '.');
        } catch (\Throwable $e) {
            report($e);
            $this->flash('Failed to send case.', 'error');
        }
    }

    public function cancelSendCaseToAdmin(): void
    {
        $this->showSendCaseToAdmin = false;
        $this->sendCaseAdminRecipientId = '';
        $this->sendCaseAdminMessage = '';
    }

    // ── Note methods ──────────────────────────────────────────

    public function addClientNote(): void
    {
        $user = auth()->user();
        if (!$user || !Gate::allows('create', CrmNote::class)) {
            $this->flash('Only ChristianDior Master Admin can add notes.', 'error');
            return;
        }
        $body = trim($this->clientNoteBody);
        if ($body === '') { $this->flash('Note cannot be empty.', 'error'); return; }

        $deal = $this->getActiveDeal();
        if (!$deal) return;

        CrmNoteService::createNote($deal, $user, $body);
        $this->clientNoteBody = '';
        $this->flash('Note added.');
    }

    public function startEditClientNote(int $noteId): void
    {
        $note = CrmNote::find($noteId);
        if (!$note || !Gate::allows('update', $note)) return;
        $this->clientEditingNoteId = $noteId;
        $this->clientEditingNoteBody = $note->body;
    }

    public function saveEditClientNote(): void
    {
        $user = auth()->user();
        $note = CrmNote::find($this->clientEditingNoteId);
        if (!$note || !$user || !Gate::allows('update', $note)) {
            $this->flash('Unauthorized.', 'error'); return;
        }
        $body = trim($this->clientEditingNoteBody);
        if ($body === '') { $this->flash('Note cannot be empty.', 'error'); return; }

        CrmNoteService::updateNote($note, $user, $body);
        $this->clientEditingNoteId = null;
        $this->clientEditingNoteBody = '';
        $this->flash('Note updated.');
    }

    public function cancelEditClientNote(): void
    {
        $this->clientEditingNoteId = null;
        $this->clientEditingNoteBody = '';
    }

    public function openSendClientNoteToChat(int $noteId): void
    {
        $note = CrmNote::find($noteId);
        if (!$note || !Gate::allows('sendToChat', $note)) return;
        $this->clientSendNoteId = $noteId;
        $this->clientSendNoteRecipientId = '';
        $this->clientSendNoteMessage = '';
    }

    public function sendClientNoteToChat(): void
    {
        $user = auth()->user();
        $note = CrmNote::find($this->clientSendNoteId);
        if (!$note || !$user || !Gate::allows('sendToChat', $note)) {
            $this->flash('Unauthorized.', 'error'); return;
        }
        if (!$this->clientSendNoteRecipientId) {
            $this->flash('Select a recipient.', 'error'); return;
        }
        $recipient = User::where('id', (int) $this->clientSendNoteRecipientId)
            ->whereIn('role', ['admin', 'master_admin', 'closer'])->first();
        if (!$recipient) { $this->flash('Invalid recipient.', 'error'); return; }

        try {
            CrmNoteService::sendNoteToDirectChat($note, $user, $recipient, trim($this->clientSendNoteMessage) ?: null);
            $this->clientSendNoteId = null;
            $this->clientSendNoteRecipientId = '';
            $this->clientSendNoteMessage = '';
            $this->flash('Note sent to chat.');
        } catch (\Throwable $e) {
            report($e);
            $this->flash('Failed to send note to chat.', 'error');
        }
    }

    public function cancelSendClientNoteToChat(): void
    {
        $this->clientSendNoteId = null;
        $this->clientSendNoteRecipientId = '';
        $this->clientSendNoteMessage = '';
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $this->flashMessage = $message;
        $this->flashType = $type;
    }

    // ── Render ──────────────────────────────────────────────────

    public function render()
    {
        $user = auth()->user();

        $query = Deal::whereIn('status', ['charged', 'chargeback', 'chargeback_won', 'chargeback_lost'])
            ->orderBy('id', 'desc');

        if ($this->statusTab !== 'all') {
            $query->where('status', $this->statusTab);
        }

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($q) => $q
                ->where('owner_name', 'like', "%{$s}%")
                ->orWhere('resort_name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
            );
        }

        // Agents/closers/fronters only see their own client records
        if (!$user->hasRole('master_admin', 'admin') && !$user->hasPerm('view_all_leads')) {
            $query->where(function ($q) use ($user) {
                $q->where('closer', $user->id)
                  ->orWhere('fronter', $user->id)
                  ->orWhere('assigned_admin', $user->id);
            });
        }

        $clients = $query->get();
        $totalRev = Deal::whereIn('status', ['charged', 'chargeback_won'])->sum('fee');
        $cbRev = Deal::whereIn('status', ['chargeback', 'chargeback_lost'])->sum('fee');
        $users = User::all()->keyBy('id');
        $active = $this->selectedClient ? Deal::find($this->selectedClient) : null;

        // Recompute permissions if active client changed
        if ($active && $user) {
            $this->computePermissions($user, $active);
        }

        // Load audit logs only when tab is active and permitted
        $auditLogs = collect();
        if ($active && $this->activeTab === 'audit' && $this->canViewAudit) {
            try {
                $auditLogs = ClientAuditLog::where('deal_id', $active->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get();
            } catch (\Throwable $e) {
                // Table may not exist yet if migration hasn't run
                $auditLogs = collect();
            }
        }

        // Load notes for selected client
        $clientNotes = collect();
        $canAddNote = false;
        $canSendNoteToChat = false;
        if ($active) {
            try {
                $clientNotes = CrmNote::where('noteable_type', Deal::class)
                    ->where('noteable_id', $active->id)
                    ->orderByDesc('created_at')
                    ->get();
            } catch (\Throwable $e) {}
            $canAddNote = Gate::allows('create', CrmNote::class);
            $canSendNoteToChat = $user->hasRole('master_admin', 'admin');
        }

        // Load chargeback cases for chargebacks tab
        $chargebackCases = collect();
        $selectedCase = null;
        $canManageCases = false;
        $caseReadiness = null;
        if ($active) {
            $canManageCases = Gate::allows('create', ChargebackCase::class);
            if ($this->activeTab === 'chargebacks') {
                try {
                    $chargebackCases = ChargebackCase::where('client_id', $active->id)
                        ->orderByDesc('created_at')->get();
                } catch (\Throwable $e) {}

                if ($this->selectedCaseId) {
                    $selectedCase = ChargebackCase::with('evidence')->find($this->selectedCaseId);
                    $caseReadiness = $selectedCase?->readiness;
                }
            }
        }

        return view('livewire.clients', compact(
            'clients', 'users', 'active', 'totalRev', 'cbRev', 'auditLogs',
            'clientNotes', 'canAddNote', 'canSendNoteToChat',
            'chargebackCases', 'selectedCase', 'canManageCases', 'caseReadiness'
        ));
    }
}
