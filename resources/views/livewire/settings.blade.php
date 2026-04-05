<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Settings</h2>
        <p class="text-xs text-crm-t3 mt-1">CRM configuration and profile controls</p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @php $isMaster = auth()->user()?->hasRole('master_admin'); @endphp

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-1 bg-crm-card border border-crm-border rounded-lg p-2 h-fit">
            @foreach([
                'company' => 'Company Info',
                'profile' => 'User Profile',
                'notifications' => 'Notifications',
                'payroll' => 'Payroll Rules',
                'leads' => 'Lead Settings',
                'deals' => 'Deal Settings',
                'chat' => 'Chat Settings',
                'documents' => 'Document Settings',
                'spreadsheets' => 'Spreadsheet Settings',
                'integrations' => 'Integrations',
                'calling' => 'Calling / Dialer',
                'task_settings' => 'Automatic Tasks',
                'transfers' => 'Transfers',
                'notes_settings' => 'Notes',
                'chargebacks_settings' => 'Chargebacks',
                'stats_settings' => 'Statistics & Dashboard',
            ] as $key => $label)
                <button wire:click="$set('section', '{{ $key }}')"
                    class="w-full text-left px-3 py-2 text-xs font-semibold rounded-md transition {{ $section === $key ? 'bg-blue-50 text-blue-600' : 'text-crm-t2 hover:bg-crm-hover' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="lg:col-span-4 bg-crm-card border border-crm-border rounded-lg p-4">
            @if($section === 'company')
                <h3 class="text-sm font-semibold mb-3">Company Info</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input id="fld-companyName" wire:model.defer="companyName" type="text" placeholder="Company Name" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-companyLogo" wire:model.defer="companyLogo" type="text" placeholder="Logo URL" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-companyPhone" wire:model.defer="companyPhone" type="text" placeholder="Phone" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-companyEmail" wire:model.defer="companyEmail" type="email" placeholder="Email" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-companyAddress" wire:model.defer="companyAddress" type="text" placeholder="Address" class="md:col-span-2 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                </div>
                <div class="mt-3 text-right"><button wire:click="saveCompanyInfo" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Company Info</button></div>
            @endif

            @if($section === 'profile')
                <h3 class="text-sm font-semibold mb-3">User Profile</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input id="fld-profileName" wire:model.defer="profileName" type="text" placeholder="Name" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-profileEmail" wire:model.defer="profileEmail" type="email" placeholder="Email" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-profileAvatar" wire:model.defer="profileAvatar" type="text" placeholder="Avatar (2 letters)" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-profileColor" wire:model.defer="profileColor" type="color" class="h-10 w-full bg-white border border-crm-border rounded-lg">
                    <input id="fld-newPassword" wire:model.defer="newPassword" type="password" placeholder="New password" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-newPasswordConfirm" wire:model.defer="newPasswordConfirm" type="password" placeholder="Confirm password" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                </div>
                @error('newPassword')<div class="text-xs text-red-600 mt-2">{{ $message }}</div>@enderror
                <div class="mt-3 text-right"><button wire:click="saveProfile" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Profile</button></div>
            @endif

            @if($section === 'notifications')
                <h3 class="text-sm font-semibold mb-3">Notification Preferences</h3>
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="notifySound"> Message sound on/off</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="notifyEmailAlerts"> Email alerts</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="notifyMentionDing"> @mention ding</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="notifyTransferDing"> Transfer ding</label>
                </div>
                <div class="mt-3 text-right"><button wire:click="saveNotifications" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Notifications</button></div>
            @endif

            @if($section === 'payroll')
                <h3 class="text-sm font-semibold mb-3">Commission Rates & Payroll Rules</h3>
                @if(!$isMaster)
                    <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Payroll rules can only be edited by master admin.</div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <div><label class="text-[10px] text-crm-t3">Closer %</label><input id="fld-payrollRates-closer_pct" wire:model.defer="payrollRates.closer_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">Fronter %</label><input id="fld-payrollRates-fronter_pct" wire:model.defer="payrollRates.fronter_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">SNR %</label><input id="fld-payrollRates-snr_pct" wire:model.defer="payrollRates.snr_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">VD %</label><input id="fld-payrollRates-vd_pct" wire:model.defer="payrollRates.vd_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">Admin SNR %</label><input id="fld-payrollRates-admin_snr_pct" wire:model.defer="payrollRates.admin_snr_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">Hourly Rate</label><input id="fld-payrollRates-hourly_rate" wire:model.defer="payrollRates.hourly_rate" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                    </div>
                    <div class="mt-3 text-right"><button wire:click="savePayrollRules" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Payroll Rules</button></div>
                @endif
            @endif

            @if($section === 'leads')
                <h3 class="text-sm font-semibold mb-3">Lead Settings</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="leadAutoAssign"> Auto-assign rules enabled</label>
                    <label for="fld-leadCsvMapping" class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="leadRoundRobin"> Round-robin assignment</label>
                                <textarea id="fld-leadCsvMapping" wire:model.defer="leadCsvMapping" rows="4" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="CSV column mapping"></textarea>
                </div>
                <div class="mt-3 text-right"><button wire:click="saveLeadSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Lead Settings</button></div>
            @endif

            @if($section === 'deals')
                <h3 class="text-sm font-semibold mb-3">Deal Settings</h3>
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="dealRequirePhone"> Require phone</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="dealRequireEmail"> Require email</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="dealRequireCardInfo"> Require card info</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="dealAutoStartVerification"> Auto-start verification</label>
                </div>
                <div class="mt-3 text-right"><button wire:click="saveDealSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Deal Settings</button></div>
            @endif

            @if($section === 'chat')
                <h3 class="text-sm font-semibold mb-3">Chat Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm mb-3">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.module_enabled"> Enable Chat Module</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.direct_messages_enabled"> Enable Direct Messages</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.group_chats_enabled"> Enable Group Chats</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.channels_enabled"> Enable Channels</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.private_channels_enabled"> Enable Private Channels</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.public_channels_enabled"> Enable Public Channels</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.thread_replies_enabled"> Enable Threaded Replies</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.read_receipts_enabled"> Enable Read Receipts</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.typing_indicators_enabled"> Enable Typing Indicators</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.online_status_enabled"> Enable Online Status</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.reactions_enabled"> Enable Emoji Reactions</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.file_attachments_enabled"> Enable File Attachments</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.image_attachments_enabled"> Enable Image Attachments</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.voice_notes_enabled"> Enable Voice Notes</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.edit_message_enabled"> Enable Edit Message</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.delete_message_enabled"> Enable Delete Message</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.pin_messages_enabled"> Enable Pin Messages</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.search_enabled"> Enable Message Search</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.mentions_enabled"> Enable Mentions</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.notifications_enabled"> Enable Notifications</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.desktop_notifications_enabled"> Enable Desktop Notifications</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.mobile_notifications_enabled"> Enable Mobile Notifications</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.admin_delete_any_message"> Allow Admin Delete Any Message</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatModuleSettings.manager_channel_moderation"> Allow Managers Moderate Channels</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label for="fld-chatModuleSettings-max_upload_size" class="text-[10px] text-crm-t3">Max File Upload Size (MB)</label>
                                <input id="fld-chatModuleSettings-max_upload_size" wire:model.defer="chatModuleSettings.max_upload_size" type="number" min="1" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="fld-chatModuleSettings-retention_days" class="text-[10px] text-crm-t3">Message Retention (days)</label>
                                <input id="fld-chatModuleSettings-retention_days" wire:model.defer="chatModuleSettings.retention_days" type="number" min="1" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div class="md:col-span-2">
                        <label for="fld-chatModuleSettings-allowed_file_types" class="text-[10px] text-crm-t3">Allowed File Types (comma-separated)</label>
                                <input id="fld-chatModuleSettings-allowed_file_types" wire:model.defer="chatModuleSettings.allowed_file_types" type="text" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="fld-chatModuleSettings-default_permission" class="text-[10px] text-crm-t3">Default Chat Permission Level</label>
                                <select id="fld-chatModuleSettings-default_permission" wire:model.defer="chatModuleSettings.default_permission" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            <option value="private">Private</option>
                            <option value="team">Team</option>
                            <option value="organization">Organization</option>
                        </select>
                    </div>
                </div>
                @error('chatModuleSettings.max_upload_size')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('chatModuleSettings.allowed_file_types')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('chatModuleSettings.retention_days')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('chatModuleSettings.default_permission')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror

                <div class="rounded-lg border border-crm-border bg-white p-3 mb-3">
                    <div class="text-[11px] font-semibold mb-1">Chat Role Permissions</div>
                    <div class="text-[11px] text-crm-t3">
                        view_chat, send_messages, edit_own_messages, delete_own_messages, delete_any_messages, create_channels, manage_channels, upload_chat_files, moderate_chat
                    </div>
                </div>

                <div class="mt-3 text-right"><button wire:click="saveChatModuleSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Chat Settings</button></div>
            @endif

            @if($section === 'documents')
                <h3 class="text-sm font-semibold mb-3">Document Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm mb-3">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.module_enabled"> Enable Document Module</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.creation_enabled"> Enable Document Creation</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.realtime_collaboration_enabled"> Enable Real-Time Collaboration</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.autosave_enabled"> Enable Auto Save</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.version_history_enabled"> Enable Version History</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.comments_enabled"> Enable Comments</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.suggestions_enabled"> Enable Suggestions Mode</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.share_permissions_enabled"> Enable Share Permissions</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.folders_enabled"> Enable Folder Organization</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.export_pdf_enabled"> Enable Export PDF</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.export_docx_enabled"> Enable Export DOCX</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.manager_manage_shared_enabled"> Managers Manage Shared Docs</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.admin_view_all_enabled"> Admin View All Documents</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.templates_enabled"> Enable Templates</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.restore_version_enabled"> Enable Restore Previous Version</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="documentModuleSettings.activity_log_enabled"> Enable Activity Log</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div>
                        <label for="fld-documentModuleSettings-autosave_interval_seconds" class="text-[10px] text-crm-t3">Auto Save Interval (seconds)</label>
                                <input id="fld-documentModuleSettings-autosave_interval_seconds" wire:model.defer="documentModuleSettings.autosave_interval_seconds" type="number" min="3" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="fld-documentModuleSettings-default_permission" class="text-[10px] text-crm-t3">Default Document Permission</label>
                                <select id="fld-documentModuleSettings-default_permission" wire:model.defer="documentModuleSettings.default_permission" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            <option value="private">Private</option>
                            <option value="team">Team</option>
                            <option value="organization">Organization</option>
                        </select>
                    </div>
                    <div>
                        <label for="fld-documentModuleSettings-max_document_size" class="text-[10px] text-crm-t3">Max Document Size (MB)</label>
                                <input id="fld-documentModuleSettings-max_document_size" wire:model.defer="documentModuleSettings.max_document_size" type="number" min="1" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                </div>
                @error('documentModuleSettings.autosave_interval_seconds')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('documentModuleSettings.default_permission')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('documentModuleSettings.max_document_size')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror

                <div class="rounded-lg border border-crm-border bg-white p-3 mb-3">
                    <div class="text-[11px] font-semibold mb-1">Document Role Permissions</div>
                    <div class="text-[11px] text-crm-t3">view_documents, create_documents, edit_documents, comment_documents, share_documents, export_documents, restore_document_versions, manage_all_documents</div>
                </div>

                <div class="mt-3 text-right"><button wire:click="saveDocumentModuleSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Document Settings</button></div>
            @endif

            @if($section === 'spreadsheets')
                <h3 class="text-sm font-semibold mb-3">Spreadsheet Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm mb-3">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.module_enabled"> Enable Spreadsheet Module</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.creation_enabled"> Enable Spreadsheet Creation</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.realtime_collaboration_enabled"> Enable Real-Time Collaboration</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.autosave_enabled"> Enable Auto Save</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.csv_import_enabled"> Enable CSV Import</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.csv_export_enabled"> Enable CSV Export</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.excel_export_enabled"> Enable Excel Export</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.formulas_enabled"> Enable Formula Support</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.sorting_enabled"> Enable Sorting</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.filtering_enabled"> Enable Filtering</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.cell_formatting_enabled"> Enable Cell Formatting</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.multi_tab_enabled"> Enable Multiple Sheet Tabs</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.manager_manage_shared_enabled"> Managers Manage Shared Sheets</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.admin_view_all_enabled"> Admin View All Sheets</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="spreadsheetModuleSettings.activity_log_enabled"> Enable Activity Log</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                    <div>
                        <label for="fld-spreadsheetModuleSettings-autosave_interval_seconds" class="text-[10px] text-crm-t3">Auto Save Interval (seconds)</label>
                                <input id="fld-spreadsheetModuleSettings-autosave_interval_seconds" wire:model.defer="spreadsheetModuleSettings.autosave_interval_seconds" type="number" min="3" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="fld-spreadsheetModuleSettings-default_permission" class="text-[10px] text-crm-t3">Default Spreadsheet Permission</label>
                                <select id="fld-spreadsheetModuleSettings-default_permission" wire:model.defer="spreadsheetModuleSettings.default_permission" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            <option value="private">Private</option>
                            <option value="team">Team</option>
                            <option value="organization">Organization</option>
                        </select>
                    </div>
                    <div>
                        <label for="fld-spreadsheetModuleSettings-max_rows" class="text-[10px] text-crm-t3">Max Rows</label>
                                <input id="fld-spreadsheetModuleSettings-max_rows" wire:model.defer="spreadsheetModuleSettings.max_rows" type="number" min="100" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="fld-spreadsheetModuleSettings-max_columns" class="text-[10px] text-crm-t3">Max Columns</label>
                                <input id="fld-spreadsheetModuleSettings-max_columns" wire:model.defer="spreadsheetModuleSettings.max_columns" type="number" min="10" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                </div>
                @error('spreadsheetModuleSettings.autosave_interval_seconds')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('spreadsheetModuleSettings.default_permission')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('spreadsheetModuleSettings.max_rows')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror
                @error('spreadsheetModuleSettings.max_columns')<div class="text-xs text-red-600 mb-1">{{ $message }}</div>@enderror

                <div class="rounded-lg border border-crm-border bg-white p-3 mb-3">
                    <div class="text-[11px] font-semibold mb-1">Spreadsheet Role Permissions</div>
                    <div class="text-[11px] text-crm-t3">view_spreadsheets, create_spreadsheets, edit_spreadsheets, import_spreadsheets, export_spreadsheets, share_spreadsheets, manage_all_spreadsheets</div>
                </div>

                <div class="mt-3 text-right"><button wire:click="saveSpreadsheetModuleSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Spreadsheet Settings</button></div>
            @endif

            @if($section === 'integrations')
                <h3 class="text-sm font-semibold mb-3">Integration Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input id="fld-integrationApiKey" wire:model.defer="integrationApiKey" type="text" placeholder="API key" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input id="fld-integrationWebhookUrl" wire:model.defer="integrationWebhookUrl" type="text" placeholder="Webhook URL" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <select id="fld-integrationSipProtocol" wire:model.defer="integrationSipProtocol" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                        <option value="sip:">sip:</option>
                        <option value="tel:">tel:</option>
                        <option value="callto:">callto:</option>
                    </select>
                    <input id="fld-integrationSipServer" wire:model.defer="integrationSipServer" type="text" placeholder="SIP server" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                </div>
                <div class="mt-3 text-right"><button wire:click="saveIntegrations" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Integrations</button></div>

                <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white border border-crm-border rounded-lg p-3">
                        <div class="text-xs font-semibold mb-2">Payment Processors</div>
                        @if($isMaster)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-3">
                                <input id="fld-newProcessorName" wire:model.defer="newProcessorName" type="text" placeholder="Processor name" class="sm:col-span-2 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                <input id="fld-newProcessorType" wire:model.defer="newProcessorType" type="text" placeholder="Type" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            </div>
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-xs flex items-center gap-2"><input type="checkbox" wire:model="newProcessorActive"> Active</label>
                                <button wire:click="addProcessor" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg">Add Processor</button>
                            </div>
                        @endif

                        <div class="space-y-2 max-h-56 overflow-auto pr-1">
                            @forelse($processors as $p)
                                <div class="flex items-center justify-between border border-crm-border rounded-lg px-2.5 py-2 text-xs">
                                    <div>
                                        <div class="font-semibold">{{ $p['name'] }}</div>
                                        <div class="text-crm-t3">{{ $p['provider_type'] ?: 'gateway' }}</div>
                                    </div>
                                    <button wire:click="toggleProcessorActive({{ $p['id'] }})" class="px-2 py-1 rounded {{ $p['active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $p['active'] ? 'Active' : 'Inactive' }}
                                    </button>
                                </div>
                            @empty
                                <div class="text-xs text-crm-t3">No processors added yet.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="bg-white border border-crm-border rounded-lg p-3">
                        <div class="text-xs font-semibold mb-2">Merchant Accounts</div>
                        @if($isMaster)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                                <input id="fld-newMerchantName" wire:model.defer="newMerchantName" type="text" placeholder="Merchant account name" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                <input id="fld-newMerchantMid" wire:model.defer="newMerchantMid" type="text" placeholder="MID (masked)" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                                <select id="fld-newMerchantProcessorId" wire:model.defer="newMerchantProcessorId" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                    <option value="">Select processor</option>
                                    @foreach($processors as $p)
                                        <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                                    @endforeach
                                </select>
                                <div class="flex items-center justify-between">
                                    <label class="text-xs flex items-center gap-2"><input type="checkbox" wire:model="newMerchantActive"> Active</label>
                                    <button wire:click="addMerchantAccount" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg">Add Account</button>
                                </div>
                            </div>
                        @endif

                        <div class="space-y-2 max-h-56 overflow-auto pr-1">
                            @forelse($merchantAccounts as $m)
                                <div class="flex items-center justify-between border border-crm-border rounded-lg px-2.5 py-2 text-xs">
                                    <div>
                                        <div class="font-semibold">{{ $m['name'] }}</div>
                                        <div class="text-crm-t3">{{ $m['processor_name'] }} · {{ $m['mid_masked'] ?: 'No MID' }}</div>
                                    </div>
                                    <button wire:click="toggleMerchantAccountActive({{ $m['id'] }})" class="px-2 py-1 rounded {{ $m['active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $m['active'] ? 'Active' : 'Inactive' }}
                                    </button>
                                </div>
                            @empty
                                <div class="text-xs text-crm-t3">No merchant accounts added yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            {{-- Calling / Dialer Settings --}}
            @if($section === 'calling')
                <div class="space-y-4">
                    <h3 class="text-lg font-bold mb-2">Calling / Dialer Settings</h3>
                    <p class="text-xs text-crm-t3 mb-4">Configure click-to-call behavior for MicroSIP integration. Phone numbers across the CRM will launch calls through your local softphone.</p>

                    {{-- General --}}
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                        <div class="text-sm font-semibold mb-3">General</div>
                        <div class="space-y-3">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="dialerSettings.enabled"> Enable Click-to-Call</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="dialerSettings.show_call_buttons"> Show Call Buttons on Phone Numbers</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="dialerSettings.show_copy_button"> Show Copy Number Button</label>
                        </div>
                    </div>

                    {{-- Click Action --}}
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                        <div class="text-sm font-semibold mb-3">Click Action</div>
                        <div>
                            <label for="ds-action" class="text-[10px] text-crm-t3 uppercase">What happens when a phone number is clicked</label>
                            <select id="ds-action" wire:model="dialerSettings.click_action" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                                <option value="copy">Copy to Clipboard (temporary — no dialing)</option>
                                <option value="tel">Launch TEL: Protocol (MicroSIP/softphone)</option>
                                <option value="sip">Launch SIP: Protocol</option>
                                <option value="sip_with_domain">Launch SIP: with Domain</option>
                            </select>
                            <p class="text-[10px] text-crm-t3 mt-1">Set to "Copy to Clipboard" until MicroSIP is configured on all workstations.</p>
                        </div>
                    </div>

                    {{-- Dialer Mode --}}
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                        <div class="text-sm font-semibold mb-3">Dialer Protocol (when using TEL/SIP mode)</div>
                        <div class="space-y-3">
                            <div>
                                <label for="ds-mode" class="text-[10px] text-crm-t3 uppercase">Dial Mode</label>
                                <select id="ds-mode" wire:model="dialerSettings.mode" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                                    <option value="tel">TEL (tel:+1234567890)</option>
                                    <option value="sip">SIP (sip:1234567890)</option>
                                    <option value="sip_with_domain">SIP with Domain (sip:1234567890@domain.com)</option>
                                </select>
                            </div>
                            <div>
                                <label for="ds-domain" class="text-[10px] text-crm-t3 uppercase">SIP Domain (for SIP with Domain mode)</label>
                                <input id="ds-domain" wire:model="dialerSettings.sip_domain" type="text" placeholder="sip.yourprovider.com" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                            </div>
                            <div>
                                <label for="ds-prefix" class="text-[10px] text-crm-t3 uppercase">Trunk Prefix (optional, e.g. 9 for outside line)</label>
                                <input id="ds-prefix" wire:model="dialerSettings.trunk_prefix" type="text" placeholder="" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                            </div>
                        </div>
                    </div>

                    {{-- Logging --}}
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                        <div class="text-sm font-semibold mb-3">Call Logging</div>
                        <div class="space-y-3">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="dialerSettings.logging_enabled"> Log All Dial Attempts</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="dialerSettings.require_outcome"> Require Call Outcome After Dial</label>
                        </div>
                    </div>

                    {{-- Workstation Setup --}}
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                        <div class="text-sm font-semibold mb-3">Workstation Setup Instructions</div>
                        <div class="text-xs text-crm-t2 space-y-2">
                            <div class="flex items-start gap-2"><span class="font-bold text-blue-600">1.</span> Install MicroSIP on each Windows workstation</div>
                            <div class="flex items-start gap-2"><span class="font-bold text-blue-600">2.</span> Configure your SIP account in MicroSIP (server, username, password)</div>
                            <div class="flex items-start gap-2"><span class="font-bold text-blue-600">3.</span> Ensure the SIP account shows "Online" status in MicroSIP</div>
                            <div class="flex items-start gap-2"><span class="font-bold text-blue-600">4.</span> Set MicroSIP as the default handler for TEL: protocol in Windows Settings → Default Apps</div>
                            <div class="flex items-start gap-2"><span class="font-bold text-blue-600">5.</span> If using SIP mode, also set MicroSIP as the default SIP: handler</div>
                            <div class="flex items-start gap-2"><span class="font-bold text-blue-600">6.</span> When the browser asks "Allow this site to open MicroSIP?", click Allow and check "Always allow"</div>
                            <div class="flex items-start gap-2"><span class="font-bold text-blue-600">7.</span> Click any 📞 phone number in the CRM to test — MicroSIP should open and begin dialing</div>
                        </div>
                    </div>

                    <button wire:click="saveDialerSettings" class="px-5 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow transition">Save Dialer Settings</button>
                </div>
            @endif

            {{-- ═══ AUTOMATIC TASK SETTINGS ═══ --}}
            @if($section === 'task_settings' && $isMaster)
                <h3 class="text-sm font-semibold mb-3">Automatic Task List Settings</h3>
                <p class="text-xs text-crm-t3 mb-4">Control how automatic tasks are created from CRM workflow events.</p>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input id="fld-task-enabled" type="checkbox" wire:model="taskSettings.task_list_enabled"> Enable Automatic Task List</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-task-sidebar" type="checkbox" wire:model="taskSettings.show_in_sidebar"> Show in Sidebar Navigation</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-task-widget" type="checkbox" wire:model="taskSettings.show_dashboard_widget"> Show Task Widget on Dashboard</label>
                    <div class="border-t border-crm-border pt-3 mt-3">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Auto-Create Tasks When</div>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm"><input id="fld-task-transfer" type="checkbox" wire:model="taskSettings.auto_create_on_transfer"> Lead transferred to closer</label>
                            <label class="flex items-center gap-2 text-sm"><input id="fld-task-verif" type="checkbox" wire:model="taskSettings.auto_create_on_verification"> Deal sent to verification</label>
                            <label class="flex items-center gap-2 text-sm"><input id="fld-task-cb" type="checkbox" wire:model="taskSettings.auto_create_on_chargeback"> Chargeback case created</label>
                            <label class="flex items-center gap-2 text-sm"><input id="fld-task-note" type="checkbox" wire:model="taskSettings.auto_create_on_note_share"> Note shared for urgent follow-up</label>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Default Due Date (days from now)</label>
                        <input id="fld-task-duedays" type="number" wire:model="taskSettings.default_due_days" min="0" max="90" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-task-manual" type="checkbox" wire:model="taskSettings.allow_manual_create"> Allow Manual Task Creation</label>
                    <div class="border-t border-crm-border pt-3 mt-3">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Admin-Only Task Assignment</div>
                        <div class="space-y-2">
                            <div>
                                <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Verified Task Assignee</label>
                                <select id="fld-task-verif-mode" wire:model="taskSettings.verified_task_assignee_mode" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                    <option value="admin_only">Admin Only</option>
                                </select>
                                <p class="text-[9px] text-crm-t3 mt-0.5">Tasks for Verified status changes are assigned to admin only.</p>
                            </div>
                            <div>
                                <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged Green Task Assignee</label>
                                <select id="fld-task-green-mode" wire:model="taskSettings.charged_green_task_assignee_mode" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                    <option value="admin_only">Admin Only</option>
                                </select>
                                <p class="text-[9px] text-crm-t3 mt-0.5">Tasks for Charged Green status changes are assigned to admin only.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 text-right"><button wire:click="saveTaskSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Task Settings</button></div>
            @endif

            {{-- ═══ TRANSFER SETTINGS ═══ --}}
            @if($section === 'transfers' && $isMaster)
                <h3 class="text-sm font-semibold mb-3">Transfer Settings</h3>
                <p class="text-xs text-crm-t3 mb-4">Control closer-to-closer and lead transfer behavior.</p>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input id="fld-tr-c2c" type="checkbox" wire:model="transferSettings.closer_to_closer_enabled"> Enable Closer-to-Closer Transfer</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-tr-reqnote" type="checkbox" wire:model="transferSettings.require_transfer_note"> Require Transfer Note</label>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Transfer Note Min Length</label>
                        <input id="fld-tr-minlen" type="number" wire:model="transferSettings.transfer_note_min_length" min="0" max="500" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-tr-chat" type="checkbox" wire:model="transferSettings.send_transfer_to_chat"> Send Transfer Note to Chat</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-tr-log" type="checkbox" wire:model="transferSettings.log_transfer_history"> Log Transfer History</label>
                </div>
                <div class="mt-4 text-right"><button wire:click="saveTransferSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Transfer Settings</button></div>
            @endif

            {{-- ═══ NOTES SETTINGS ═══ --}}
            @if($section === 'notes_settings' && $isMaster)
                <h3 class="text-sm font-semibold mb-3">Notes Settings</h3>
                <p class="text-xs text-crm-t3 mb-4">Control notes creation, editing, and sharing across Clients and Deals.</p>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input id="fld-n-clients" type="checkbox" wire:model="notesSettings.notes_on_clients_enabled"> Enable Notes on Clients</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-n-deals" type="checkbox" wire:model="notesSettings.notes_on_deals_enabled"> Enable Notes on Deals</label>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Note Creator Username (only this master admin can add/edit notes)</label>
                        <input id="fld-n-creator" type="text" wire:model="notesSettings.note_creator_username" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="e.g. christiandior">
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-n-chat" type="checkbox" wire:model="notesSettings.send_note_to_chat_enabled"> Allow Send Note to Chat</label>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Allowed Recipient Roles (comma-separated)</label>
                        <input id="fld-n-roles" type="text" wire:model="notesSettings.note_recipient_roles" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="admin,master_admin">
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-n-edited" type="checkbox" wire:model="notesSettings.show_edited_badge"> Show Edited Badge</label>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Note Ordering</label>
                        <select id="fld-n-order" wire:model="notesSettings.note_ordering" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            <option value="newest_first">Newest First</option>
                            <option value="oldest_first">Oldest First</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 text-right"><button wire:click="saveNotesSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Notes Settings</button></div>
            @endif

            {{-- ═══ CHARGEBACK SETTINGS ═══ --}}
            @if($section === 'chargebacks_settings' && $isMaster)
                <h3 class="text-sm font-semibold mb-3">Chargeback Settings</h3>
                <p class="text-xs text-crm-t3 mb-4">Configure chargeback case management, evidence requirements, and permissions.</p>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input id="fld-cb-enabled" type="checkbox" wire:model="chargebackSettings.chargeback_tab_enabled"> Enable Chargeback Tab on Clients</label>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Case Creator Username</label>
                        <input id="fld-cb-creator" type="text" wire:model="chargebackSettings.case_creator_username" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="e.g. christiandior">
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Evidence Uploader Username</label>
                        <input id="fld-cb-uploader" type="text" wire:model="chargebackSettings.evidence_uploader_username" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Send Case Recipient Roles (comma-separated)</label>
                        <input id="fld-cb-roles" type="text" wire:model="chargebackSettings.send_case_recipient_roles" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="admin,master_admin">
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Allowed Upload File Types (comma-separated)</label>
                        <input id="fld-cb-filetypes" type="text" wire:model="chargebackSettings.allowed_upload_types" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="pdf,png,jpg,jpeg">
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Max Upload Size (MB)</label>
                        <input id="fld-cb-maxsize" type="number" wire:model="chargebackSettings.max_upload_size_mb" min="1" max="100" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Required Evidence Types (comma-separated keys)</label>
                        <textarea id="fld-cb-evtypes" wire:model="chargebackSettings.required_evidence_types" rows="3" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">{{ $chargebackSettings['required_evidence_types'] ?? '' }}</textarea>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Readiness Threshold %</label>
                        <input id="fld-cb-threshold" type="number" wire:model="chargebackSettings.readiness_threshold_pct" min="0" max="100" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Card Brands (comma-separated)</label>
                        <input id="fld-cb-brands" type="text" wire:model="chargebackSettings.card_brands" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="Visa,Mastercard,Amex,Discover">
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Case Statuses (comma-separated)</label>
                        <input id="fld-cb-statuses" type="text" wire:model="chargebackSettings.default_case_statuses" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="open,submitted,won,lost">
                    </div>
                </div>
                <div class="mt-4 text-right"><button wire:click="saveChargebackSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Chargeback Settings</button></div>
            @endif

            {{-- ═══ STATISTICS & DASHBOARD SETTINGS ═══ --}}
            @if($section === 'stats_settings' && $isMaster)
                <h3 class="text-sm font-semibold mb-3">Statistics & Dashboard Settings</h3>
                <p class="text-xs text-crm-t3 mb-4">Control how pipeline statistics and dashboard data appear for each role.</p>
                <div class="space-y-3">
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Default Stats Range</label>
                        <select id="fld-st-range" wire:model="statsSettings.default_stats_range" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            <option value="live">Live</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-st-fronter" type="checkbox" wire:model="statsSettings.enable_fronter_filter"> Enable Fronter Agent Filter</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-st-closer" type="checkbox" wire:model="statsSettings.enable_closer_filter"> Enable Closer Agent Filter</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-st-admin" type="checkbox" wire:model="statsSettings.enable_admin_filter"> Enable Admin Agent Filter</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-st-pct" type="checkbox" wire:model="statsSettings.show_percentages"> Show Percentages in Stats Tables</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-st-personal" type="checkbox" wire:model="statsSettings.personal_stats_only_for_non_admin"> Non-admin Users See Only Personal Stats on Dashboard</label>
                    <label class="flex items-center gap-2 text-sm"><input id="fld-st-master" type="checkbox" wire:model="statsSettings.master_admin_sees_all"> Master Admin Sees All-Company Stats</label>
                </div>
                <div class="mt-4 text-right"><button wire:click="saveStatsSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Statistics Settings</button></div>
            @endif
        </div>
    </div>
</div>
