<?php
namespace App\Livewire;

use App\Models\MerchantAccount;
use App\Models\Processor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    public string $section = 'company';

    public string $companyName = 'PRIME CRM';
    public string $companyLogo = '';
    public string $companyAddress = '';
    public string $companyPhone = '';
    public string $companyEmail = '';

    public string $profileName = '';
    public string $profileEmail = '';
    public string $profileAvatar = '';
    public string $profileColor = '#3b82f6';
    public string $newPassword = '';
    public string $newPasswordConfirm = '';

    public bool $notifySound = true;
    public bool $notifyEmailAlerts = false;
    public bool $notifyMentionDing = true;
    public bool $notifyTransferDing = true;

    public array $payrollRates = [
        'closer_pct' => 50,
        'fronter_pct' => 10,
        'snr_pct' => 2,
        'vd_pct' => 3,
        'admin_snr_pct' => 2,
        'hourly_rate' => 19.50,
    ];

    public bool $leadAutoAssign = false;
    public bool $leadRoundRobin = false;
    public string $leadCsvMapping = 'Resort,Owner Name,Phone 1,Phone 2,City,State,Zip,Resort Location';

    public bool $dealRequirePhone = true;
    public bool $dealRequireEmail = false;
    public bool $dealRequireCardInfo = false;
    public bool $dealAutoStartVerification = false;

    public bool $chatSound = true;
    public bool $chatGifEnabled = true;
    public bool $chatFileUploads = true;
    public int $chatMaxFileMb = 10;

    public string $integrationApiKey = '';
    public string $integrationWebhookUrl = '';
    public string $integrationSipProtocol = 'sip:';
    public string $integrationSipServer = '';

    public array $chatModuleSettings = [
        'module_enabled' => true,
        'direct_messages_enabled' => true,
        'group_chats_enabled' => true,
        'channels_enabled' => true,
        'private_channels_enabled' => true,
        'public_channels_enabled' => true,
        'thread_replies_enabled' => true,
        'read_receipts_enabled' => true,
        'typing_indicators_enabled' => true,
        'online_status_enabled' => true,
        'reactions_enabled' => true,
        'file_attachments_enabled' => true,
        'image_attachments_enabled' => true,
        'voice_notes_enabled' => false,
        'edit_message_enabled' => true,
        'delete_message_enabled' => true,
        'pin_messages_enabled' => true,
        'search_enabled' => true,
        'mentions_enabled' => true,
        'notifications_enabled' => true,
        'desktop_notifications_enabled' => true,
        'mobile_notifications_enabled' => false,
        'max_upload_size' => 10,
        'allowed_file_types' => 'pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp,txt,zip',
        'retention_days' => 365,
        'admin_delete_any_message' => true,
        'manager_channel_moderation' => true,
        'default_permission' => 'team',
    ];

    public array $documentModuleSettings = [
        'module_enabled' => true,
        'creation_enabled' => true,
        'realtime_collaboration_enabled' => true,
        'autosave_enabled' => true,
        'autosave_interval_seconds' => 10,
        'version_history_enabled' => true,
        'comments_enabled' => true,
        'suggestions_enabled' => true,
        'share_permissions_enabled' => true,
        'folders_enabled' => true,
        'export_pdf_enabled' => true,
        'export_docx_enabled' => true,
        'default_permission' => 'team',
        'manager_manage_shared_enabled' => true,
        'admin_view_all_enabled' => true,
        'max_document_size' => 25,
        'templates_enabled' => true,
        'restore_version_enabled' => true,
        'activity_log_enabled' => true,
    ];

    public array $spreadsheetModuleSettings = [
        'module_enabled' => true,
        'creation_enabled' => true,
        'realtime_collaboration_enabled' => true,
        'autosave_enabled' => true,
        'autosave_interval_seconds' => 10,
        'csv_import_enabled' => true,
        'csv_export_enabled' => true,
        'excel_export_enabled' => true,
        'formulas_enabled' => true,
        'sorting_enabled' => true,
        'filtering_enabled' => true,
        'cell_formatting_enabled' => true,
        'multi_tab_enabled' => true,
        'default_permission' => 'team',
        'max_rows' => 50000,
        'max_columns' => 200,
        'manager_manage_shared_enabled' => true,
        'admin_view_all_enabled' => true,
        'activity_log_enabled' => true,
    ];

    public array $dialerSettings = [
        'enabled' => true,
        'click_action' => 'copy',
        'mode' => 'sip',
        'sip_domain' => '',
        'trunk_prefix' => '',
        'logging_enabled' => true,
        'require_outcome' => false,
        'show_call_buttons' => true,
        'show_copy_button' => true,
    ];

    public array $processors = [];
    public array $merchantAccounts = [];

    public string $newProcessorName = '';
    public string $newProcessorType = 'gateway';
    public bool $newProcessorActive = true;

    public string $newMerchantName = '';
    public string $newMerchantMid = '';
    public string $newMerchantProcessorId = '';
    public bool $newMerchantActive = true;

    public function mount()
    {
        $u = auth()->user();

        $this->companyName = $this->getSetting('company.name', 'PRIME CRM');
        $this->companyLogo = $this->getSetting('company.logo', '');
        $this->companyAddress = $this->getSetting('company.address', '');
        $this->companyPhone = $this->getSetting('company.phone', '');
        $this->companyEmail = $this->getSetting('company.email', '');

        $this->profileName = (string) ($u->name ?? '');
        $this->profileEmail = (string) ($u->email ?? '');
        $this->profileAvatar = (string) ($u->avatar ?? '');
        $this->profileColor = (string) ($u->color ?? '#3b82f6');

        $this->notifySound = (bool) $this->getSetting('notifications.sound', true);
        $this->notifyEmailAlerts = (bool) $this->getSetting('notifications.email_alerts', false);
        $this->notifyMentionDing = (bool) $this->getSetting('notifications.mention_ding', true);
        $this->notifyTransferDing = (bool) $this->getSetting('notifications.transfer_ding', true);

        $dbRates = DB::table('payroll_settings')->first();
        if ($dbRates) {
            $this->payrollRates = [
                'closer_pct' => (float) $dbRates->closer_pct,
                'fronter_pct' => (float) $dbRates->fronter_pct,
                'snr_pct' => (float) $dbRates->snr_pct,
                'vd_pct' => (float) $dbRates->vd_pct,
                'admin_snr_pct' => (float) $dbRates->admin_snr_pct,
                'hourly_rate' => (float) $dbRates->hourly_rate,
            ];
        }

        $this->leadAutoAssign = (bool) $this->getSetting('lead.auto_assign', false);
        $this->leadRoundRobin = (bool) $this->getSetting('lead.round_robin', false);
        $this->leadCsvMapping = (string) $this->getSetting('lead.csv_mapping', $this->leadCsvMapping);

        $this->dealRequirePhone = (bool) $this->getSetting('deal.require_phone', true);
        $this->dealRequireEmail = (bool) $this->getSetting('deal.require_email', false);
        $this->dealRequireCardInfo = (bool) $this->getSetting('deal.require_card', false);
        $this->dealAutoStartVerification = (bool) $this->getSetting('deal.auto_verification', false);

        $this->chatSound = (bool) $this->getSetting('chat.sound', true);
        $this->chatGifEnabled = (bool) $this->getSetting('chat.gif', true);
        $this->chatFileUploads = (bool) $this->getSetting('chat.file_uploads', true);
        $this->chatMaxFileMb = (int) $this->getSetting('chat.max_file_mb', 10);

        $this->integrationApiKey = (string) $this->getSetting('integration.api_key', '');
        $this->integrationWebhookUrl = (string) $this->getSetting('integration.webhook_url', '');
        $this->integrationSipProtocol = (string) $this->getSetting('integration.sip_protocol', 'sip:');
        $this->integrationSipServer = (string) $this->getSetting('integration.sip_server', '');

        $this->chatModuleSettings = $this->loadSettingsGroup('chat', $this->chatModuleSettings);
        $this->documentModuleSettings = $this->loadSettingsGroup('documents', $this->documentModuleSettings);
        $this->spreadsheetModuleSettings = $this->loadSettingsGroup('spreadsheets', $this->spreadsheetModuleSettings);
        $this->dialerSettings = $this->loadSettingsGroup('dialer', $this->dialerSettings);

        $this->loadMerchantIntegrationData();
    }

    private function getSetting($key, $default)
    {
        $row = DB::table('crm_settings')->where('key', $key)->first();
        return $row ? json_decode($row->value, true) : $default;
    }

    private function saveSetting($key, $value)
    {
        DB::table('crm_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
    }

    private function loadSettingsGroup(string $prefix, array $defaults): array
    {
        $loaded = $defaults;
        foreach ($defaults as $key => $default) {
            $loaded[$key] = $this->getSetting($prefix . '.' . $key, $default);
        }

        return $loaded;
    }

    private function saveSettingsGroup(string $prefix, array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->saveSetting($prefix . '.' . $key, $value);
        }
    }

    public function saveCompanyInfo(): void
    {
        $this->saveSetting('company.name', $this->companyName);
        $this->saveSetting('company.logo', $this->companyLogo);
        $this->saveSetting('company.address', $this->companyAddress);
        $this->saveSetting('company.phone', $this->companyPhone);
        $this->saveSetting('company.email', $this->companyEmail);
        session()->flash('success', 'Company settings saved.');
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $user->update([
            'name' => $this->profileName,
            'email' => $this->profileEmail,
            'avatar' => $this->profileAvatar,
            'color' => $this->profileColor,
        ]);

        if ($this->newPassword !== '' || $this->newPasswordConfirm !== '') {
            if ($this->newPassword !== $this->newPasswordConfirm) {
                $this->addError('newPassword', 'Passwords do not match.');
                return;
            }

            if (strlen($this->newPassword) < 8) {
                $this->addError('newPassword', 'Password must be at least 8 characters.');
                return;
            }

            $user->update(['password' => Hash::make($this->newPassword)]);
            $this->newPassword = '';
            $this->newPasswordConfirm = '';
        }

        session()->flash('success', 'Profile settings saved.');
    }

    public function saveNotifications(): void
    {
        $this->saveSetting('notifications.sound', $this->notifySound);
        $this->saveSetting('notifications.email_alerts', $this->notifyEmailAlerts);
        $this->saveSetting('notifications.mention_ding', $this->notifyMentionDing);
        $this->saveSetting('notifications.transfer_ding', $this->notifyTransferDing);
        session()->flash('success', 'Notification settings saved.');
    }

    public function savePayrollRules(): void
    {
        DB::table('payroll_settings')->updateOrInsert(
            ['id' => 1],
            [
                'closer_pct' => $this->payrollRates['closer_pct'] ?? 50,
                'fronter_pct' => $this->payrollRates['fronter_pct'] ?? 10,
                'snr_pct' => $this->payrollRates['snr_pct'] ?? 2,
                'vd_pct' => $this->payrollRates['vd_pct'] ?? 3,
                'admin_snr_pct' => $this->payrollRates['admin_snr_pct'] ?? 2,
                'hourly_rate' => $this->payrollRates['hourly_rate'] ?? 19.50,
                'updated_at' => now(),
                'updated_by' => (string) auth()->id(),
            ]
        );
        session()->flash('success', 'Payroll rules saved.');
    }

    public function saveLeadSettings(): void
    {
        $this->saveSetting('lead.auto_assign', $this->leadAutoAssign);
        $this->saveSetting('lead.round_robin', $this->leadRoundRobin);
        $this->saveSetting('lead.csv_mapping', $this->leadCsvMapping);
        session()->flash('success', 'Lead settings saved.');
    }

    public function saveDealSettings(): void
    {
        $this->saveSetting('deal.require_phone', $this->dealRequirePhone);
        $this->saveSetting('deal.require_email', $this->dealRequireEmail);
        $this->saveSetting('deal.require_card', $this->dealRequireCardInfo);
        $this->saveSetting('deal.auto_verification', $this->dealAutoStartVerification);
        session()->flash('success', 'Deal settings saved.');
    }

    public function saveChatSettings(): void
    {
        $this->saveSetting('chat.sound', $this->chatSound);
        $this->saveSetting('chat.gif', $this->chatGifEnabled);
        $this->saveSetting('chat.file_uploads', $this->chatFileUploads);
        $this->saveSetting('chat.max_file_mb', $this->chatMaxFileMb);
        session()->flash('success', 'Legacy chat settings saved.');
    }

    public function saveChatModuleSettings(): void
    {
        $this->validate([
            'chatModuleSettings.max_upload_size' => 'required|integer|min:1|max:1024',
            'chatModuleSettings.allowed_file_types' => 'nullable|string|max:500',
            'chatModuleSettings.retention_days' => 'required|integer|min:1|max:3650',
            'chatModuleSettings.default_permission' => 'required|string|max:50',
        ]);

        $this->saveSettingsGroup('chat', $this->chatModuleSettings);
        session()->flash('success', 'Chat module settings saved.');
    }

    public function saveDocumentModuleSettings(): void
    {
        $this->validate([
            'documentModuleSettings.autosave_interval_seconds' => 'required|integer|min:3|max:3600',
            'documentModuleSettings.max_document_size' => 'required|integer|min:1|max:1024',
            'documentModuleSettings.default_permission' => 'required|string|max:50',
        ]);

        $this->saveSettingsGroup('documents', $this->documentModuleSettings);
        session()->flash('success', 'Document settings saved.');
    }

    public function saveSpreadsheetModuleSettings(): void
    {
        $this->validate([
            'spreadsheetModuleSettings.autosave_interval_seconds' => 'required|integer|min:3|max:3600',
            'spreadsheetModuleSettings.max_rows' => 'required|integer|min:100|max:1000000',
            'spreadsheetModuleSettings.max_columns' => 'required|integer|min:10|max:10000',
            'spreadsheetModuleSettings.default_permission' => 'required|string|max:50',
        ]);

        $this->saveSettingsGroup('spreadsheets', $this->spreadsheetModuleSettings);
        session()->flash('success', 'Spreadsheet settings saved.');
    }

    public function saveDialerSettings(): void
    {
        $this->saveSettingsGroup('dialer', $this->dialerSettings);
        session()->flash('success', 'Dialer settings saved.');
    }

    public function saveIntegrations(): void
    {
        $this->saveSetting('integration.api_key', $this->integrationApiKey);
        $this->saveSetting('integration.webhook_url', $this->integrationWebhookUrl);
        $this->saveSetting('integration.sip_protocol', $this->integrationSipProtocol);
        $this->saveSetting('integration.sip_server', $this->integrationSipServer);
        session()->flash('success', 'Integration settings saved.');

        $this->loadMerchantIntegrationData();
    }

    private function loadMerchantIntegrationData(): void
    {
        $this->processors = Processor::query()
            ->orderBy('name')
            ->get(['id', 'name', 'provider_type', 'active'])
            ->toArray();

        $this->merchantAccounts = MerchantAccount::query()
            ->with('processor:id,name')
            ->orderBy('name')
            ->get(['id', 'processor_id', 'name', 'mid_masked', 'active'])
            ->map(function (MerchantAccount $account): array {
                return [
                    'id' => $account->id,
                    'processor_id' => $account->processor_id,
                    'processor_name' => $account->processor?->name ?? '--',
                    'name' => $account->name,
                    'mid_masked' => $account->mid_masked,
                    'active' => (bool) $account->active,
                ];
            })
            ->toArray();

        if ($this->newMerchantProcessorId === '' && !empty($this->processors)) {
            $this->newMerchantProcessorId = (string) $this->processors[0]['id'];
        }
    }

    public function addProcessor(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) {
            return;
        }

        $this->validate([
            'newProcessorName' => ['required', 'string', 'max:120'],
            'newProcessorType' => ['nullable', 'string', 'max:60'],
        ]);

        Processor::create([
            'name' => trim($this->newProcessorName),
            'provider_type' => trim($this->newProcessorType) !== '' ? trim($this->newProcessorType) : null,
            'active' => $this->newProcessorActive,
        ]);

        $this->newProcessorName = '';
        $this->newProcessorType = 'gateway';
        $this->newProcessorActive = true;

        $this->loadMerchantIntegrationData();
    }

    public function addMerchantAccount(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) {
            return;
        }

        $this->validate([
            'newMerchantName' => ['required', 'string', 'max:120'],
            'newMerchantProcessorId' => ['required', 'integer', 'exists:processors,id'],
            'newMerchantMid' => ['nullable', 'string', 'max:32'],
        ]);

        MerchantAccount::create([
            'name' => trim($this->newMerchantName),
            'processor_id' => (int) $this->newMerchantProcessorId,
            'mid_masked' => trim($this->newMerchantMid) !== '' ? trim($this->newMerchantMid) : null,
            'active' => $this->newMerchantActive,
        ]);

        $this->newMerchantName = '';
        $this->newMerchantMid = '';
        $this->newMerchantActive = true;

        $this->loadMerchantIntegrationData();
    }

    public function toggleProcessorActive(int $id): void
    {
        if (!auth()->user()?->hasRole('master_admin')) {
            return;
        }

        $row = Processor::find($id);
        if (!$row) {
            return;
        }

        $row->update(['active' => !$row->active]);
        $this->loadMerchantIntegrationData();
    }

    public function toggleMerchantAccountActive(int $id): void
    {
        if (!auth()->user()?->hasRole('master_admin')) {
            return;
        }

        $row = MerchantAccount::find($id);
        if (!$row) {
            return;
        }

        $row->update(['active' => !$row->active]);
        $this->loadMerchantIntegrationData();
    }

    public function render() { return view('livewire.settings'); }
}
