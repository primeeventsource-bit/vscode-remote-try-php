<?php
namespace App\Livewire;

use App\Models\MerchantAccount;
use App\Models\Processor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    use WithFileUploads;

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
    public string $profileEmoji = '';
    public $profilePhotoUpload = null;
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

    // ── AI Engine Settings ─────────────────────────────────
    public array $aiSettings = [
        'enable_ai_engine' => false,
        'ai_model' => 'gpt-4o-mini',
        'ai_timeout_seconds' => 15,
        'ai_max_tokens_default' => 500,
        'ai_temperature_default' => 0.7,
        'enable_ai_objection_detection' => true,
        'enable_ai_next_line_suggestions' => true,
        'enable_ai_rebuttal_rewrite' => true,
        'enable_ai_follow_up_questions' => true,
        'allow_agents_to_use_ai_assist' => true,
        'aggressive_ai_mode_enabled' => false,
        'ai_log_all_outputs' => true,
        'ai_redact_sensitive_data' => true,
    ];

    // ── Transfer Settings ─────────────────────────────────────
    public array $transferSettings = [
        'closer_to_closer_enabled' => true,
        'require_transfer_note' => true,
        'transfer_note_min_length' => 3,
        'send_transfer_to_chat' => true,
        'log_transfer_history' => true,
    ];

    // ── Notes Settings ──────────────────────────────────────
    public array $notesSettings = [
        'notes_on_clients_enabled' => true,
        'notes_on_deals_enabled' => true,
        'note_creator_username' => 'christiandior',
        'send_note_to_chat_enabled' => true,
        'note_recipient_roles' => 'admin,master_admin',
        'show_edited_badge' => true,
        'note_ordering' => 'newest_first',
    ];

    // ── Chargeback Settings ─────────────────────────────────
    public array $chargebackSettings = [
        'chargeback_tab_enabled' => true,
        'case_creator_username' => 'christiandior',
        'evidence_uploader_username' => 'christiandior',
        'send_case_recipient_roles' => 'admin,master_admin',
        'allowed_upload_types' => 'pdf,png,jpg,jpeg',
        'max_upload_size_mb' => 10,
        'required_evidence_types' => 'owners_agreement,card_authorization,invoice_copy,terms_dispute_waiver,transaction_receipt,advertisement_screenshot,client_login_report,welcome_email_confirmation,customer_summary_information',
        'readiness_threshold_pct' => 100,
        'default_case_statuses' => 'open,submitted,won,lost',
        'card_brands' => 'Visa,Mastercard,Amex,Discover',
    ];

    // ── Statistics / Dashboard Settings ──────────────────────
    public array $statsSettings = [
        'default_stats_range' => 'live',
        'enable_fronter_filter' => true,
        'enable_closer_filter' => true,
        'enable_admin_filter' => true,
        'show_percentages' => true,
        'personal_stats_only_for_non_admin' => true,
        'master_admin_sees_all' => true,
    ];

    // ── Task Settings ───────────────────────────────────────
    public array $taskSettings = [
        'task_list_enabled' => true,
        'show_in_sidebar' => true,
        'show_dashboard_widget' => true,
        'auto_create_on_transfer' => true,
        'auto_create_on_verification' => true,
        'auto_create_on_chargeback' => true,
        'auto_create_on_note_share' => true,
        'default_due_days' => 1,
        'allow_manual_create' => true,
        'verified_task_assignee_mode' => 'admin_only',
        'charged_green_task_assignee_mode' => 'admin_only',
    ];

    // ── Sales Training Settings ─────────────────────────────
    public array $salesTrainingSettings = [
        'live_assist_enabled' => true,
        'objection_library_enabled' => true,
        'analytics_enabled' => true,
        'aggressive_mode_enabled' => true,
        'agents_can_view_library' => true,
        'agents_can_use_assist' => true,
        'admin_can_manage_rebuttals' => true,
    ];

    // ── Onboarding Settings ─────────────────────────────────
    public array $onboardingSettings = [
        'onboarding_enabled' => true,
        'show_on_first_login' => true,
        'show_in_sidebar' => true,
        'allow_skip_steps' => true,
        'admin_can_view_progress' => true,
        'master_admin_can_reset' => true,
    ];

    // ── Presence Settings ────────────────────────────────────
    public array $presenceSettings = [
        'enable_user_presence_tracking' => true,
        'idle_threshold_seconds' => 300,
        'offline_timeout_seconds' => 90,
        'heartbeat_interval_seconds' => 30,
        'show_idle_time_in_ui' => true,
        'show_presence_badges_in_chat' => true,
        'show_presence_badges_in_agent_directory' => true,
        'show_presence_badges_in_group_call_picker' => true,
    ];

    // ── Video Call Settings ─────────────────────────────────
    public array $videoCallSettings = [
        'enable_group_video_calls' => true,
        'only_admin_can_create_group_calls' => true,
        'allow_master_admin_create_group_calls' => true,
        'allow_admin_create_group_calls' => true,
        'allow_agents_create_group_calls' => false,
        'group_call_add_all_agents_enabled' => true,
        'group_call_search_agents_enabled' => true,
        'exclude_busy_agents_from_add_all' => false,
        'exclude_offline_agents_from_add_all' => false,
        'max_group_call_participants' => 20,
        'show_agent_status_in_picker' => true,
        'allow_admin_force_end_group_call' => true,
        'enable_direct_video_calls' => true,
        'allow_agents_start_direct_video_calls' => true,
    ];

    // ── Avatar Settings ─────────────────────────────────────
    public array $avatarSettings = [
        'user_avatar_uploads_enabled' => true,
        'emoji_avatars_enabled' => true,
        'allowed_avatar_types' => 'jpg,jpeg,png,webp',
        'max_avatar_size_mb' => 2,
        'group_chat_avatars_enabled' => true,
        'group_avatar_master_admin_only' => true,
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
        $this->profileEmoji = (string) ($u->avatar_emoji ?? '');

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
        $this->aiSettings = $this->loadSettingsGroup('ai', $this->aiSettings);
        $this->salesTrainingSettings = $this->loadSettingsGroup('sales_training', $this->salesTrainingSettings);
        $this->onboardingSettings = $this->loadSettingsGroup('onboarding', $this->onboardingSettings);
        $this->presenceSettings = $this->loadSettingsGroup('presence', $this->presenceSettings);
        $this->videoCallSettings = $this->loadSettingsGroup('video_call', $this->videoCallSettings);
        $this->avatarSettings = $this->loadSettingsGroup('avatar', $this->avatarSettings);
        $this->taskSettings = $this->loadSettingsGroup('tasks', $this->taskSettings);
        $this->transferSettings = $this->loadSettingsGroup('transfer', $this->transferSettings);
        $this->notesSettings = $this->loadSettingsGroup('notes', $this->notesSettings);
        $this->chargebackSettings = $this->loadSettingsGroup('chargeback', $this->chargebackSettings);
        $this->statsSettings = $this->loadSettingsGroup('stats', $this->statsSettings);

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
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
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

        $updateData = [
            'name' => $this->profileName,
            'email' => $this->profileEmail,
            'avatar' => $this->profileAvatar,
            'color' => $this->profileColor,
            'avatar_emoji' => $this->profileEmoji ?: null,
        ];

        // Handle photo upload — uses central storage resolver
        if ($this->profilePhotoUpload) {
            $this->validate(['profilePhotoUpload' => 'image|max:5120|mimes:jpg,jpeg,png,webp,gif,bmp,tiff,tif']);
            $resolver = app(\App\Services\Storage\ActiveStorageResolver::class);
            if ($user->avatar_path) { try { $resolver->delete($user->avatar_path); } catch (\Throwable $e) {} }
            $path = $resolver->storeUpload($this->profilePhotoUpload, 'avatars');
            $updateData['avatar_path'] = $path;
            $updateData['avatar_emoji'] = null;
            $this->profilePhotoUpload = null;
            $this->profileEmoji = '';
        }

        // If emoji is set, clear photo
        if ($this->profileEmoji) {
            if ($user->avatar_path) { try { app(\App\Services\Storage\ActiveStorageResolver::class)->delete($user->avatar_path); } catch (\Throwable $e) {} }
            $updateData['avatar_path'] = null;
        }

        $user->update($updateData);

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

    public function removeProfilePhoto(): void
    {
        $user = auth()->user();
        if ($user->avatar_path) {
            try { app(\App\Services\Storage\ActiveStorageResolver::class)->delete($user->avatar_path); } catch (\Throwable $e) {}
            $user->update(['avatar_path' => null]);
        }
        session()->flash('success', 'Photo removed.');
    }

    public function setProfileEmoji(string $emoji): void
    {
        $this->profileEmoji = $emoji;
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
        if (!auth()->user()?->hasRole('master_admin')) return;
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
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        $this->saveSetting('lead.auto_assign', $this->leadAutoAssign);
        $this->saveSetting('lead.round_robin', $this->leadRoundRobin);
        $this->saveSetting('lead.csv_mapping', $this->leadCsvMapping);
        session()->flash('success', 'Lead settings saved.');
    }

    public function saveDealSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        $this->saveSetting('deal.require_phone', $this->dealRequirePhone);
        $this->saveSetting('deal.require_email', $this->dealRequireEmail);
        $this->saveSetting('deal.require_card', $this->dealRequireCardInfo);
        $this->saveSetting('deal.auto_verification', $this->dealAutoStartVerification);
        session()->flash('success', 'Deal settings saved.');
    }

    public function saveChatSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        $this->saveSetting('chat.sound', $this->chatSound);
        $this->saveSetting('chat.gif', $this->chatGifEnabled);
        $this->saveSetting('chat.file_uploads', $this->chatFileUploads);
        $this->saveSetting('chat.max_file_mb', $this->chatMaxFileMb);
        session()->flash('success', 'Legacy chat settings saved.');
    }

    public function saveChatModuleSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
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
        if (!auth()->user()?->hasRole('master_admin')) return;
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
        if (!auth()->user()?->hasRole('master_admin')) return;
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
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('dialer', $this->dialerSettings);
        session()->flash('success', 'Dialer settings saved.');
    }

    public function saveIntegrations(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
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

    public function saveAiSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('ai', $this->aiSettings);
        session()->flash('success', 'AI engine settings saved.');
    }

    public function saveSalesTrainingSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('sales_training', $this->salesTrainingSettings);
        session()->flash('success', 'Sales training settings saved.');
    }

    public function saveOnboardingSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('onboarding', $this->onboardingSettings);
        session()->flash('success', 'Onboarding settings saved.');
    }

    public function savePresenceSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('presence', $this->presenceSettings);
        session()->flash('success', 'Presence settings saved.');
    }

    public function saveVideoCallSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('video_call', $this->videoCallSettings);
        session()->flash('success', 'Video call settings saved.');
    }

    public function saveAvatarSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('avatar', $this->avatarSettings);
        session()->flash('success', 'Avatar settings saved.');
    }

    public function saveTaskSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('tasks', $this->taskSettings);
        session()->flash('success', 'Task settings saved.');
    }

    public function saveTransferSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('transfer', $this->transferSettings);
        session()->flash('success', 'Transfer settings saved.');
    }

    public function saveNotesSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('notes', $this->notesSettings);
        session()->flash('success', 'Notes settings saved.');
    }

    public function saveChargebackSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('chargeback', $this->chargebackSettings);
        session()->flash('success', 'Chargeback settings saved.');
    }

    public function saveStatsSettings(): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->saveSettingsGroup('stats', $this->statsSettings);
        session()->flash('success', 'Statistics & Dashboard settings saved.');
    }

    // ── Script Management ──────────────────────────────────

    public function setDefaultScript(int $scriptId): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;

        $script = \App\Models\SalesScript::find($scriptId);
        if (!$script) return;

        $script->makeDefault();
        session()->flash('success', "{$script->name} is now the default for {$script->stage} stage.");
    }

    public function toggleScriptActive(int $scriptId): void
    {
        if (!auth()->user()?->hasRole('master_admin')) return;

        $script = \App\Models\SalesScript::find($scriptId);
        if (!$script) return;

        // Don't deactivate the default — must set another default first
        if ($script->is_default && $script->is_active) {
            session()->flash('error', 'Cannot deactivate the default script. Set another default first.');
            return;
        }

        $script->update(['is_active' => !$script->is_active]);
    }

    public function render()
    {
        $viewData = [];

        // Load scripts for management section
        if ($this->section === 'scripts' && auth()->user()?->hasRole('master_admin')) {
            try {
                $viewData['allScripts'] = \App\Models\SalesScript::orderByDefault()
                    ->orderBy('stage')
                    ->orderBy('order_index')
                    ->get();
            } catch (\Throwable $e) {
                $viewData['allScripts'] = \App\Models\SalesScript::orderBy('stage')
                    ->orderBy('order_index')
                    ->get();
            }
        }

        return view('livewire.settings', $viewData);
    }
}
