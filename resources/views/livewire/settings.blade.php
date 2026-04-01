<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Settings</h2>
        <p class="text-xs text-crm-t3 mt-1">CRM configuration and profile controls</p>
    </div>

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
                'integrations' => 'Integrations',
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
                    <input wire:model.defer="companyName" type="text" placeholder="Company Name" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="companyLogo" type="text" placeholder="Logo URL" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="companyPhone" type="text" placeholder="Phone" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="companyEmail" type="email" placeholder="Email" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="companyAddress" type="text" placeholder="Address" class="md:col-span-2 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                </div>
                <div class="mt-3 text-right"><button wire:click="saveCompanyInfo" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Company Info</button></div>
            @endif

            @if($section === 'profile')
                <h3 class="text-sm font-semibold mb-3">User Profile</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input wire:model.defer="profileName" type="text" placeholder="Name" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="profileEmail" type="email" placeholder="Email" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="profileAvatar" type="text" placeholder="Avatar (2 letters)" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="profileColor" type="color" class="h-10 w-full bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="newPassword" type="password" placeholder="New password" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="newPasswordConfirm" type="password" placeholder="Confirm password" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
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
                        <div><label class="text-[10px] text-crm-t3">Closer %</label><input wire:model.defer="payrollRates.closer_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">Fronter %</label><input wire:model.defer="payrollRates.fronter_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">SNR %</label><input wire:model.defer="payrollRates.snr_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">VD %</label><input wire:model.defer="payrollRates.vd_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">Admin SNR %</label><input wire:model.defer="payrollRates.admin_snr_pct" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                        <div><label class="text-[10px] text-crm-t3">Hourly Rate</label><input wire:model.defer="payrollRates.hourly_rate" type="number" step="0.01" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                    </div>
                    <div class="mt-3 text-right"><button wire:click="savePayrollRules" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Payroll Rules</button></div>
                @endif
            @endif

            @if($section === 'leads')
                <h3 class="text-sm font-semibold mb-3">Lead Settings</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="leadAutoAssign"> Auto-assign rules enabled</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="leadRoundRobin"> Round-robin assignment</label>
                    <textarea wire:model.defer="leadCsvMapping" rows="4" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="CSV column mapping"></textarea>
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
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatSound"> Notification sounds</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatGifEnabled"> GIF enabled</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="chatFileUploads"> File uploads enabled</label>
                    <div><label class="text-[10px] text-crm-t3">Max file size (MB)</label><input wire:model.defer="chatMaxFileMb" type="number" min="1" class="w-28 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></div>
                </div>
                <div class="mt-3 text-right"><button wire:click="saveChatSettings" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Chat Settings</button></div>
            @endif

            @if($section === 'integrations')
                <h3 class="text-sm font-semibold mb-3">Integration Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input wire:model.defer="integrationApiKey" type="text" placeholder="API key" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <input wire:model.defer="integrationWebhookUrl" type="text" placeholder="Webhook URL" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <select wire:model.defer="integrationSipProtocol" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                        <option value="sip:">sip:</option>
                        <option value="tel:">tel:</option>
                        <option value="callto:">callto:</option>
                    </select>
                    <input wire:model.defer="integrationSipServer" type="text" placeholder="SIP server" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                </div>
                <div class="mt-3 text-right"><button wire:click="saveIntegrations" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg">Save Integrations</button></div>

                <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white border border-crm-border rounded-lg p-3">
                        <div class="text-xs font-semibold mb-2">Payment Processors</div>
                        @if($isMaster)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-3">
                                <input wire:model.defer="newProcessorName" type="text" placeholder="Processor name" class="sm:col-span-2 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                <input wire:model.defer="newProcessorType" type="text" placeholder="Type" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
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
                                <input wire:model.defer="newMerchantName" type="text" placeholder="Merchant account name" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                <input wire:model.defer="newMerchantMid" type="text" placeholder="MID (masked)" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                                <select wire:model.defer="newMerchantProcessorId" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
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
        </div>
    </div>
</div>
