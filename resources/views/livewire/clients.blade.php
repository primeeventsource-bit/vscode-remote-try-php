<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Clients</h2>
        <p class="text-xs text-crm-t3 mt-1">Charged deals and client management</p>
    </div>

    {{-- Flash Messages --}}
    @if($flashMessage)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm font-semibold {{ $flashType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
            {{ $flashMessage }}
        </div>
    @endif

    {{-- Search + Status Tabs --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input id="fld-search" wire:model.live.debounce.300ms="search" type="text" placeholder="Search clients..." class="flex-1 min-w-[200px] px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
        <div class="flex items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5">
            @foreach(['all' => 'All', 'charged' => 'Charged', 'chargeback' => 'CB', 'chargeback_won' => 'Won', 'chargeback_lost' => 'Lost'] as $key => $label)
                <button wire:click="$set('statusTab', '{{ $key }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $statusTab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Revenue Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        @php
            $chargedWon = $clients->filter(fn($c) => $c->charged === 'yes' && $c->charged_back !== 'yes');
            $cbLost = $clients->filter(fn($c) => $c->charged_back === 'yes' || in_array($c->status, ['chargeback', 'chargeback_lost']));
            $chargedTotal = $chargedWon->sum('fee');
            $cbTotal = $cbLost->sum('fee');
        @endphp
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged + Won</div>
            <div class="text-2xl font-extrabold text-emerald-500 mt-1">${{ number_format($chargedTotal) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $chargedWon->count() }} clients</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">CB + Lost</div>
            <div class="text-2xl font-extrabold text-red-500 mt-1">${{ number_format($cbTotal) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $cbLost->count() }} clients</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Net Revenue</div>
            <div class="text-2xl font-extrabold text-blue-500 mt-1">${{ number_format($chargedTotal - $cbTotal) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $clients->count() }} total</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Avg Deal Size</div>
            <div class="text-2xl font-extrabold text-purple-500 mt-1">${{ $chargedWon->count() > 0 ? number_format($chargedTotal / $chargedWon->count()) : 0 }}</div>
        </div>
    </div>

    <div class="flex gap-4">
        {{-- Client List --}}
        <div class="flex-1">
            <div class="space-y-2">
                @forelse($clients as $client)
                    @php
                        $statusColor = match(true) {
                            $client->charged_back === 'yes' => ['bg-red-50 text-red-500', 'CB'],
                            $client->charged === 'yes' => ['bg-emerald-50 text-emerald-600', 'Charged'],
                            $client->status === 'chargeback_won' => ['bg-blue-50 text-blue-600', 'Won'],
                            $client->status === 'chargeback_lost' => ['bg-gray-100 text-gray-500', 'Lost'],
                            default => ['bg-amber-50 text-amber-600', 'Pending'],
                        };
                        $closer = $users->firstWhere('id', $client->closer);
                    @endphp
                    <div wire:click="selectClient({{ $client->id }})"
                         class="bg-crm-card border border-crm-border rounded-lg p-3 cursor-pointer transition {{ (isset($active) && $active && $active->id === $client->id) ? 'border-blue-400 bg-blue-50/50' : 'hover:bg-crm-hover' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0"
                                 style="background: {{ $closer->color ?? '#6b7280' }}">
                                {{ strtoupper(substr($client->owner_name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold truncate">{{ $client->owner_name }}</span>
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $statusColor[0] }}">{{ $statusColor[1] }}</span>
                                </div>
                                <div class="text-[11px] text-crm-t3 truncate">{{ $client->resort_name }}</div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-bold font-mono text-emerald-500">${{ number_format($client->fee, 2) }}</div>
                                <div class="text-[10px] text-crm-t3">{{ $closer->name ?? '--' }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                        <p class="text-sm text-crm-t3">No clients found</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             DETAIL PANEL - Tabbed sections with edit forms
             ═══════════════════════════════════════════════════════════ --}}
        @if($active)
            <div class="w-[440px] flex-shrink-0 bg-crm-card border border-crm-border rounded-lg max-h-[80vh] overflow-y-auto">
                {{-- Header --}}
                <div class="flex items-center justify-between p-4 border-b border-crm-border sticky top-0 bg-crm-card z-10">
                    <h4 class="text-sm font-bold truncate">{{ $active->owner_name }}</h4>
                    <button wire:click="selectClient({{ $active->id }})" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>

                {{-- Tab Navigation --}}
                <div class="flex border-b border-crm-border px-2 sticky top-[53px] bg-crm-card z-10">
                    @php
                        $tabs = [
                            'info' => 'Client Info',
                            'deal_sheet' => 'Deal Sheet',
                            'banking' => 'Banking',
                            'payment' => 'Payment',
                            'chargebacks' => 'Chargebacks',
                            'audit' => 'Audit Log',
                        ];
                    @endphp
                    @foreach($tabs as $tabKey => $tabLabel)
                        @php
                            $tabVisible = match($tabKey) {
                                'deal_sheet' => $canViewDealSheet,
                                'banking' => $canViewBanking,
                                'payment' => $canViewPayment,
                                'chargebacks' => auth()->user()?->hasRole('master_admin', 'admin'),
                                'audit' => $canViewAudit,
                                default => true,
                            };
                        @endphp
                        @if($tabVisible)
                            <button wire:click="setTab('{{ $tabKey }}')"
                                class="px-3 py-2.5 text-[11px] font-semibold border-b-2 transition whitespace-nowrap
                                {{ $activeTab === $tabKey ? 'border-blue-500 text-blue-600' : 'border-transparent text-crm-t3 hover:text-crm-t1' }}">
                                {{ $tabLabel }}
                            </button>
                        @endif
                    @endforeach
                </div>

                <div class="p-4">

                {{-- ╔══════════════════════════════════════════════╗
                     ║  TAB: CLIENT INFO                           ║
                     ╚══════════════════════════════════════════════╝ --}}
                @if($activeTab === 'info')
                    @if(!$editing)
                        {{-- READ-ONLY VIEW --}}
                        <div class="space-y-2 mb-4">
                            @foreach([
                                'Owner Name' => $active->owner_name,
                                'Email' => $active->email,
                                'Mailing Address' => $active->mailing_address,
                                'City/State/Zip' => $active->city_state_zip,
                                'Status' => ucfirst(str_replace('_', ' ', $active->status ?? 'pending')),
                                'Charged' => $active->charged === 'yes' ? 'Yes' : 'No',
                                'Charged Back' => $active->charged_back === 'yes' ? 'Yes' : 'No',
                                'Deal Date' => $active->timestamp?->format('n/j/Y') ?? '--',
                                'Charged Date' => $active->charged_date?->format('n/j/Y') ?? '--',
                                'Closer' => $users->firstWhere('id', $active->closer)?->name ?? '--',
                                'Fronter' => $users->firstWhere('id', $active->fronter)?->name ?? '--',
                                'Admin' => $users->firstWhere('id', $active->assigned_admin)?->name ?? '--',
                            ] as $lbl => $val)
                                <div class="flex justify-between text-xs">
                                    <span class="text-crm-t3">{{ $lbl }}</span>
                                    <span class="font-semibold text-right max-w-[200px] truncate">{{ $val ?: '--' }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Phones --}}
                        <div class="border-t border-crm-border pt-3 mb-4">
                            <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Phone Numbers</div>
                            @if($active->primary_phone)
                                <div class="mb-1" x-data="{ copied: false }">
                                    <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->primary_phone) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">{{ $active->primary_phone }}</button>
                                    <span class="text-[10px] text-crm-t3">Primary</span>
                                    <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                                </div>
                            @endif
                            @if($active->secondary_phone)
                                <div x-data="{ copied: false }">
                                    <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->secondary_phone) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">{{ $active->secondary_phone }}</button>
                                    <span class="text-[10px] text-crm-t3">Secondary</span>
                                    <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                                </div>
                            @endif
                        </div>

                        {{-- Notes --}}
                        @if($active->notes)
                            <div class="border-t border-crm-border pt-3 mb-4">
                                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Notes</div>
                                <div class="text-xs whitespace-pre-wrap bg-white border border-crm-border rounded p-2">{{ $active->notes }}</div>
                            </div>
                        @endif

                        {{-- Files --}}
                        <div class="border-t border-crm-border pt-3 mb-4">
                            <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Files</div>
                            @if($active->files && count($active->files))
                                <div class="space-y-1 mb-2">
                                    @foreach($active->files as $file)
                                        <div class="flex items-center gap-2 text-xs bg-white border border-crm-border rounded p-1.5">
                                            <svg class="w-3.5 h-3.5 text-crm-t3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            <span class="truncate">{{ is_string($file) ? $file : ($file['name'] ?? 'File') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Edit Button --}}
                        @if($canEdit)
                            <button wire:click="startEditing"
                                class="w-full py-2 px-4 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                                Edit Client Info
                            </button>
                        @endif

                    @else
                        {{-- EDIT FORM: Client Info --}}
                        <form wire:submit.prevent="saveSection">
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Owner Name *</label>
                                    <input id="fld-client-owner_name" type="text" wire:model="clientForm.owner_name" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Email</label>
                                    <input id="fld-client-email" type="email" wire:model="clientForm.email" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Primary Phone</label>
                                        <input id="fld-client-primary_phone" type="text" wire:model="clientForm.primary_phone" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Secondary Phone</label>
                                        <input id="fld-client-secondary_phone" type="text" wire:model="clientForm.secondary_phone" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Mailing Address</label>
                                    <input id="fld-client-mailing_address" type="text" wire:model="clientForm.mailing_address" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">City / State / Zip</label>
                                    <input id="fld-client-city_state_zip" type="text" wire:model="clientForm.city_state_zip" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Status</label>
                                    <select id="fld-client-status" wire:model="clientForm.status" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                        <option value="charged">Charged</option>
                                        <option value="chargeback">Chargeback</option>
                                        <option value="chargeback_won">Chargeback Won</option>
                                        <option value="chargeback_lost">Chargeback Lost</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Assigned Admin</label>
                                    <select id="fld-client-assigned_admin" wire:model="clientForm.assigned_admin" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                        <option value="">-- None --</option>
                                        @foreach($users->filter(fn($u) => in_array($u->role, ['master_admin', 'admin'])) as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Notes</label>
                                    <textarea id="fld-client-notes" wire:model="clientForm.notes" rows="3" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400"></textarea>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-4">
                                <button type="submit" class="flex-1 py-2 px-4 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition">
                                    Save Changes
                                </button>
                                <button type="button" wire:click="cancelEditing" class="py-2 px-4 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    @endif

                {{-- ╔══════════════════════════════════════════════╗
                     ║  TAB: DEAL SHEET                            ║
                     ╚══════════════════════════════════════════════╝ --}}
                @elseif($activeTab === 'deal_sheet' && $canViewDealSheet)
                    @if(!$editing)
                        <div class="space-y-2 mb-4">
                            @foreach([
                                'Fee' => '$' . number_format($active->fee, 2),
                                'Resort Name' => $active->resort_name,
                                'Resort City/State' => $active->resort_city_state,
                                'Weeks' => $active->weeks,
                                'Bed/Bath' => $active->bed_bath,
                                'Usage' => $active->usage,
                                'Exchange Group' => $active->exchange_group,
                                'Asking Rental' => $active->asking_rental,
                                'Asking Sale Price' => $active->asking_sale_price,
                                'Using Timeshare' => $active->using_timeshare,
                                'Looking to Get Out' => $active->looking_to_get_out,
                                'Verification #' => $active->verification_num,
                                'Was VD' => $active->was_vd,
                                'SNR' => $active->snr,
                                'Merchant' => $active->merchant,
                                'Fronter' => $users->firstWhere('id', $active->fronter)?->name ?? '--',
                                'Closer' => $users->firstWhere('id', $active->closer)?->name ?? '--',
                            ] as $lbl => $val)
                                <div class="flex justify-between text-xs">
                                    <span class="text-crm-t3">{{ $lbl }}</span>
                                    <span class="font-semibold text-right max-w-[200px] truncate">{{ $val ?: '--' }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if($canEditDealSheet)
                            <button wire:click="startEditing"
                                class="w-full py-2 px-4 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                                Edit Deal Sheet
                            </button>
                        @endif
                    @else
                        {{-- EDIT FORM: Deal Sheet --}}
                        <form wire:submit.prevent="saveSection">
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Fee ($)</label>
                                    <input id="fld-ds-fee" type="number" step="0.01" wire:model="dealSheetForm.fee" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Resort Name</label>
                                    <input id="fld-ds-resort_name" type="text" wire:model="dealSheetForm.resort_name" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Resort City/State</label>
                                    <input id="fld-ds-resort_city_state" type="text" wire:model="dealSheetForm.resort_city_state" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Weeks</label>
                                        <input id="fld-ds-weeks" type="text" wire:model="dealSheetForm.weeks" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Bed/Bath</label>
                                        <input id="fld-ds-bed_bath" type="text" wire:model="dealSheetForm.bed_bath" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Usage</label>
                                        <input id="fld-ds-usage" type="text" wire:model="dealSheetForm.usage" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Exchange Group</label>
                                        <input id="fld-ds-exchange_group" type="text" wire:model="dealSheetForm.exchange_group" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Asking Rental</label>
                                        <input id="fld-ds-asking_rental" type="text" wire:model="dealSheetForm.asking_rental" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Asking Sale Price</label>
                                        <input id="fld-ds-asking_sale_price" type="text" wire:model="dealSheetForm.asking_sale_price" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Using Timeshare</label>
                                    <input id="fld-ds-using_timeshare" type="text" wire:model="dealSheetForm.using_timeshare" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Looking to Get Out</label>
                                    <input id="fld-ds-looking_to_get_out" type="text" wire:model="dealSheetForm.looking_to_get_out" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Verification #</label>
                                    <input id="fld-ds-verification_num" type="text" wire:model="dealSheetForm.verification_num" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Was VD</label>
                                        <select id="fld-ds-was_vd" wire:model="dealSheetForm.was_vd" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">SNR</label>
                                        <input id="fld-ds-snr" type="text" wire:model="dealSheetForm.snr" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Merchant</label>
                                    <input id="fld-ds-merchant" type="text" wire:model="dealSheetForm.merchant" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Fronter</label>
                                        <select id="fld-ds-fronter" wire:model="dealSheetForm.fronter" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                            <option value="">-- None --</option>
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Closer</label>
                                        <select id="fld-ds-closer" wire:model="dealSheetForm.closer" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                            <option value="">-- None --</option>
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-4">
                                <button type="submit" class="flex-1 py-2 px-4 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition">
                                    Save Deal Sheet
                                </button>
                                <button type="button" wire:click="cancelEditing" class="py-2 px-4 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    @endif

                {{-- ╔══════════════════════════════════════════════╗
                     ║  TAB: BANKING INFO                          ║
                     ╚══════════════════════════════════════════════╝ --}}
                @elseif($activeTab === 'banking' && $canViewBanking)
                    @if(!$editing)
                        <div class="space-y-2 mb-4">
                            @foreach([
                                'Bank Name' => $active->bank,
                                'Bank 2' => $active->bank2,
                                'Billing Address' => $active->billing_address,
                            ] as $lbl => $val)
                                <div class="flex justify-between text-xs">
                                    <span class="text-crm-t3">{{ $lbl }}</span>
                                    <span class="font-semibold text-right max-w-[200px] truncate">{{ $val ?: '--' }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if($canEditBanking)
                            <button wire:click="startEditing"
                                class="w-full py-2 px-4 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                                Edit Banking Info
                            </button>
                        @endif
                    @else
                        <form wire:submit.prevent="saveSection">
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Bank Name</label>
                                    <input id="fld-bank-bank" type="text" wire:model="bankingForm.bank" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Bank 2</label>
                                    <input id="fld-bank-bank2" type="text" wire:model="bankingForm.bank2" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Billing Address</label>
                                    <input id="fld-bank-billing_address" type="text" wire:model="bankingForm.billing_address" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                            </div>

                            <div class="flex gap-2 mt-4">
                                <button type="submit" class="flex-1 py-2 px-4 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition">
                                    Save Banking Info
                                </button>
                                <button type="button" wire:click="cancelEditing" class="py-2 px-4 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    @endif

                {{-- ╔══════════════════════════════════════════════╗
                     ║  TAB: PAYMENT PROFILE                       ║
                     ╚══════════════════════════════════════════════╝ --}}
                @elseif($activeTab === 'payment' && $canViewPayment)
                    @if(!$editing)
                        <div class="space-y-2 mb-4">
                            {{-- Always show masked card info --}}
                            <div class="flex justify-between text-xs">
                                <span class="text-crm-t3">Primary Card</span>
                                <span class="font-semibold font-mono">{{ $active->masked_card }}</span>
                            </div>
                            @if($active->card_last4_2)
                                <div class="flex justify-between text-xs">
                                    <span class="text-crm-t3">Secondary Card</span>
                                    <span class="font-semibold font-mono">{{ $active->masked_card2 }}</span>
                                </div>
                            @endif

                            @foreach([
                                'Name on Card' => $active->name_on_card,
                                'Card Type' => $active->card_type,
                                'Card Brand' => $active->card_brand,
                                'Billing Address' => $active->billing_address,
                            ] as $lbl => $val)
                                <div class="flex justify-between text-xs">
                                    <span class="text-crm-t3">{{ $lbl }}</span>
                                    <span class="font-semibold text-right max-w-[200px] truncate">{{ $val ?: '--' }}</span>
                                </div>
                            @endforeach

                            {{-- Expiration dates only for users with sensitive financial permission --}}
                            @if($canViewSensitiveFinancial)
                                <div class="flex justify-between text-xs">
                                    <span class="text-crm-t3">Exp Date</span>
                                    <span class="font-semibold">{{ $active->exp_date ?: '--' }}</span>
                                </div>
                                @if($active->exp_date2)
                                    <div class="flex justify-between text-xs">
                                        <span class="text-crm-t3">Exp Date 2</span>
                                        <span class="font-semibold">{{ $active->exp_date2 ?: '--' }}</span>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 mb-4">
                            <p class="text-[10px] text-amber-700 font-semibold">Full card numbers are encrypted at rest. CVV is never stored or displayed. Only the last 4 digits and card brand are shown.</p>
                        </div>

                        @if($canEditPayment)
                            <button wire:click="startEditing"
                                class="w-full py-2 px-4 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                                Edit Payment Profile
                            </button>
                        @endif
                    @else
                        <form wire:submit.prevent="saveSection">
                            <div class="space-y-3">
                                {{-- Display masked card (not editable through this form) --}}
                                <div class="bg-gray-50 border border-crm-border rounded-lg p-2.5">
                                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Primary Card (read-only)</div>
                                    <div class="text-sm font-mono font-semibold">{{ $active->masked_card }}</div>
                                </div>
                                @if($active->card_last4_2)
                                    <div class="bg-gray-50 border border-crm-border rounded-lg p-2.5">
                                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Secondary Card (read-only)</div>
                                        <div class="text-sm font-mono font-semibold">{{ $active->masked_card2 }}</div>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Name on Card</label>
                                    <input id="fld-pay-name_on_card" type="text" wire:model="paymentForm.name_on_card" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Card Type</label>
                                        <input id="fld-pay-card_type" type="text" wire:model="paymentForm.card_type" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Card Brand</label>
                                        <input id="fld-pay-card_brand" type="text" wire:model="paymentForm.card_brand" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Exp Date</label>
                                        <input id="fld-pay-exp_date" type="text" wire:model="paymentForm.exp_date" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="MM/YY">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Exp Date 2</label>
                                        <input id="fld-pay-exp_date2" type="text" wire:model="paymentForm.exp_date2" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="MM/YY">
                                    </div>
                                </div>
                            </div>

                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 mt-3">
                                <p class="text-[10px] text-amber-700 font-semibold">Card numbers cannot be edited here. CVV is never stored. Only billing profile fields are editable.</p>
                            </div>

                            <div class="flex gap-2 mt-4">
                                <button type="submit" class="flex-1 py-2 px-4 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition">
                                    Save Payment Profile
                                </button>
                                <button type="button" wire:click="cancelEditing" class="py-2 px-4 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    @endif

                {{-- ╔══════════════════════════════════════════════╗
                     ║  TAB: AUDIT HISTORY                         ║
                     ╚══════════════════════════════════════════════╝ --}}
                {{-- ╔══════════════════════════════════════════════╗
                     ║  TAB: CHARGEBACKS                            ║
                     ╚══════════════════════════════════════════════╝ --}}
                @elseif($activeTab === 'chargebacks')
                    {{-- Case list or case detail --}}
                    @if($selectedCase)
                        {{-- ═══ CASE DETAIL VIEW ═══ --}}
                        <div class="mb-3">
                            <button wire:click="selectCase({{ $selectedCase->id }})" class="text-xs text-blue-600 hover:underline">&larr; Back to cases</button>
                        </div>

                        {{-- Case header --}}
                        <div class="bg-white border border-crm-border rounded-lg p-3 mb-3">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <div class="text-sm font-bold">Case {{ $selectedCase->case_number }}</div>
                                    <div class="text-[10px] text-crm-t3">{{ $selectedCase->reason_code }} — {{ $selectedCase->reason_description ?: 'No reason' }}</div>
                                </div>
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded {{ match($selectedCase->status) {
                                    'won' => 'bg-emerald-50 text-emerald-600',
                                    'lost' => 'bg-red-50 text-red-500',
                                    'submitted' => 'bg-blue-50 text-blue-600',
                                    default => 'bg-amber-50 text-amber-600',
                                } }}">{{ ucfirst($selectedCase->status) }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[10px]">
                                <div><span class="text-crm-t3">Amount:</span> <span class="font-bold">${{ number_format($selectedCase->transaction_amount ?? 0, 2) }}</span></div>
                                <div><span class="text-crm-t3">Disputed:</span> <span class="font-bold text-red-500">${{ number_format($selectedCase->disputed_amount ?? 0, 2) }}</span></div>
                                <div><span class="text-crm-t3">Card:</span> <span class="font-semibold">{{ $selectedCase->card_brand ?: '--' }}</span></div>
                                <div><span class="text-crm-t3">Processor:</span> <span class="font-semibold">{{ $selectedCase->processor_name ?: '--' }}</span></div>
                                <div><span class="text-crm-t3">Sale Date:</span> <span class="font-semibold">{{ $selectedCase->sale_date?->format('M j, Y') ?? '--' }}</span></div>
                                <div><span class="text-crm-t3">Deadline:</span> <span class="font-bold {{ $selectedCase->response_due_at && $selectedCase->response_due_at->isPast() ? 'text-red-600' : 'text-amber-600' }}">{{ $selectedCase->response_due_at?->format('M j, Y') ?? '--' }}</span></div>
                            </div>
                        </div>

                        {{-- Readiness tracker --}}
                        @if($caseReadiness)
                            <div class="bg-white border border-crm-border rounded-lg p-3 mb-3">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Evidence Readiness</div>
                                    <span class="text-xs font-bold {{ $caseReadiness['ready'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $caseReadiness['uploaded'] }} / {{ $caseReadiness['total'] }} — {{ $caseReadiness['pct'] }}%</span>
                                </div>
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-2">
                                    <div class="h-full rounded-full transition-all {{ $caseReadiness['pct'] >= 100 ? 'bg-emerald-500' : ($caseReadiness['pct'] >= 50 ? 'bg-amber-500' : 'bg-red-400') }}"
                                         style="width: {{ $caseReadiness['pct'] }}%"></div>
                                </div>
                                @if(!empty($caseReadiness['missing_types']))
                                    <div class="text-[9px] text-red-500">Missing: {{ implode(', ', array_map(fn($t) => \App\Models\ChargebackCase::DOCUMENT_TYPES[$t] ?? $t, $caseReadiness['missing_types'])) }}</div>
                                @endif
                            </div>
                        @endif

                        {{-- Evidence checklist --}}
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Required Evidence</div>
                        <div class="space-y-1.5 mb-3">
                            @foreach(\App\Models\ChargebackCase::DOCUMENT_TYPES as $docType => $docLabel)
                                @php
                                    $doc = $selectedCase->evidence->firstWhere('document_type', $docType);
                                @endphp
                                <div class="bg-white border border-crm-border rounded-lg p-2.5 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold">{{ $docLabel }}</div>
                                        @if($doc)
                                            <div class="text-[9px] text-crm-t3 truncate">{{ $doc->original_filename }} &middot; {{ $users->get($doc->uploaded_by_user_id)?->name ?? '?' }} &middot; {{ $doc->created_at->format('M j g:iA') }}</div>
                                        @else
                                            <div class="text-[9px] text-red-500">No file uploaded</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        @if($doc)
                                            <span class="text-[8px] font-bold px-1.5 py-0.5 rounded {{ $doc->status === 'verified' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">{{ ucfirst($doc->status) }}</span>
                                            @if($canManageCases && $doc->status !== 'verified')
                                                <button wire:click="verifyEvidence({{ $doc->id }})" class="text-[8px] text-blue-600 font-semibold hover:underline">Verify</button>
                                            @endif
                                        @else
                                            <span class="text-[8px] font-bold px-1.5 py-0.5 rounded bg-red-50 text-red-500">Missing</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Upload form (ChristianDior only) --}}
                        @if($canManageCases)
                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-3">
                                <div class="text-[10px] text-indigo-700 uppercase tracking-wider font-semibold mb-2">Upload Evidence</div>
                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="flex-1 min-w-[120px]">
                                        <select id="fld-cb-doctype" wire:model="uploadDocType" class="w-full px-2 py-1.5 text-xs bg-white border border-crm-border rounded-lg">
                                            <option value="">Document type...</option>
                                            @foreach(\App\Models\ChargebackCase::DOCUMENT_TYPES as $dt => $dl)
                                                <option value="{{ $dt }}">{{ $dl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex-1 min-w-[120px]">
                                        <input id="fld-cb-file" type="file" wire:model="evidenceUpload" accept=".pdf,.png,.jpg,.jpeg" class="w-full text-xs">
                                    </div>
                                    <button wire:click="uploadEvidence" wire:loading.attr="disabled"
                                        class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                                        <span wire:loading.remove wire:target="uploadEvidence">Upload</span>
                                        <span wire:loading wire:target="uploadEvidence">Uploading...</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Status actions --}}
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                @foreach(['open' => 'Open', 'submitted' => 'Submitted', 'won' => 'Won', 'lost' => 'Lost'] as $st => $stLabel)
                                    @if($selectedCase->status !== $st)
                                        <button wire:click="updateCaseStatus({{ $selectedCase->id }}, '{{ $st }}')"
                                            class="px-2.5 py-1 text-[10px] font-semibold rounded {{ match($st) {
                                                'won' => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200',
                                                'lost' => 'bg-red-100 text-red-700 hover:bg-red-200',
                                                'submitted' => 'bg-blue-100 text-blue-700 hover:bg-blue-200',
                                                default => 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                            } }} transition">{{ $stLabel }}</button>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        {{-- Send Case to Admin --}}
                        @if(auth()->user()?->hasRole('master_admin', 'admin'))
                            @if(!$showSendCaseToAdmin)
                                <button wire:click="openSendCaseToAdmin"
                                    class="w-full py-2 text-xs font-semibold text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition">
                                    Send Case to Admin
                                </button>
                            @else
                                <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                                    <div class="text-[10px] text-purple-700 uppercase tracking-wider font-semibold mb-2">Send Case to Admin</div>
                                    <select id="fld-sendcase-recipient" wire:model="sendCaseAdminRecipientId" class="w-full px-2 py-1.5 text-xs bg-white border border-crm-border rounded-lg mb-2">
                                        <option value="">Select Admin / Master Admin...</option>
                                        @foreach($users->filter(fn($u) => in_array($u->role, ['admin', 'master_admin'])) as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }} ({{ ucfirst(str_replace('_', ' ', $u->role)) }})</option>
                                        @endforeach
                                    </select>
                                    <input id="fld-sendcase-msg" wire:model="sendCaseAdminMessage" type="text" placeholder="Additional note (optional)..." class="w-full px-2 py-1.5 text-xs bg-white border border-crm-border rounded-lg mb-2">
                                    <div class="flex gap-1.5">
                                        <button wire:click="sendCaseToAdmin" class="px-4 py-1.5 text-xs font-semibold text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition">
                                            <span wire:loading.remove wire:target="sendCaseToAdmin">Send to Admin</span>
                                            <span wire:loading wire:target="sendCaseToAdmin">Sending...</span>
                                        </button>
                                        <button wire:click="cancelSendCaseToAdmin" class="px-3 py-1.5 text-xs text-crm-t2 bg-white border border-crm-border rounded-lg hover:bg-gray-50 transition">Cancel</button>
                                    </div>
                                </div>
                            @endif
                        @endif

                    @else
                        {{-- ═══ CASE LIST VIEW ═══ --}}
                        @if($canManageCases)
                            <button wire:click="openCreateCase"
                                class="w-full mb-3 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                                + New Chargeback Case
                            </button>
                        @endif

                        {{-- Create case form --}}
                        @if($showCreateCase)
                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-3">
                                <div class="text-xs font-bold mb-2">New Chargeback Case</div>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach([
                                        'case_number' => 'Case Number',
                                        'card_brand' => 'Card Brand',
                                        'processor_name' => 'Processor',
                                        'reason_code' => 'Reason Code',
                                        'transaction_amount' => 'Transaction Amount',
                                        'disputed_amount' => 'Disputed Amount',
                                        'order_id' => 'Order/Verification ID',
                                        'transaction_id' => 'Transaction ID',
                                    ] as $field => $label)
                                        <div>
                                            <label class="text-[9px] text-crm-t3 uppercase">{{ $label }}</label>
                                            <input id="fld-cb-{{ $field }}" type="text" wire:model="caseForm.{{ $field }}" class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded">
                                        </div>
                                    @endforeach
                                    <div>
                                        <label class="text-[9px] text-crm-t3 uppercase">Response Deadline</label>
                                        <input id="fld-cb-deadline" type="date" wire:model="caseForm.response_due_at" class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded">
                                    </div>
                                    <div>
                                        <label class="text-[9px] text-crm-t3 uppercase">Sale Date</label>
                                        <input id="fld-cb-saledate" type="date" wire:model="caseForm.sale_date" class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded">
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="text-[9px] text-crm-t3 uppercase">Reason Description</label>
                                    <input id="fld-cb-reason-desc" type="text" wire:model="caseForm.reason_description" class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded">
                                </div>
                                <div class="flex gap-2 mt-2">
                                    <button wire:click="saveChargebackCase" class="px-4 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded hover:bg-indigo-700 transition">Create Case</button>
                                    <button wire:click="$set('showCreateCase', false)" class="px-3 py-1.5 text-xs text-crm-t2 bg-gray-100 rounded hover:bg-gray-200 transition">Cancel</button>
                                </div>
                            </div>
                        @endif

                        {{-- Existing cases list --}}
                        @forelse($chargebackCases as $case)
                            <div wire:click="selectCase({{ $case->id }})"
                                 class="bg-white border border-crm-border rounded-lg p-3 mb-2 cursor-pointer hover:bg-crm-hover transition">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-xs font-bold">{{ $case->case_number }}</div>
                                        <div class="text-[10px] text-crm-t3">{{ $case->reason_code }} &middot; {{ $case->card_brand ?: '--' }} &middot; ${{ number_format($case->disputed_amount ?? 0, 2) }}</div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[8px] font-bold px-1.5 py-0.5 rounded {{ match($case->status) {
                                            'won' => 'bg-emerald-50 text-emerald-600',
                                            'lost' => 'bg-red-50 text-red-500',
                                            'submitted' => 'bg-blue-50 text-blue-600',
                                            default => 'bg-amber-50 text-amber-600',
                                        } }}">{{ ucfirst($case->status) }}</span>
                                        @if($case->response_due_at)
                                            <div class="text-[9px] text-crm-t3 mt-0.5">Due: {{ $case->response_due_at->format('M j') }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6">
                                <p class="text-xs text-crm-t3">No chargeback cases for this client</p>
                            </div>
                        @endforelse
                    @endif

                @elseif($activeTab === 'audit' && $canViewAudit)
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-3 font-semibold">Audit History</div>

                    @if(count($auditLogs) > 0)
                        <div class="space-y-2">
                            @foreach($auditLogs as $log)
                                <div class="bg-white border border-crm-border rounded-lg p-2.5">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-semibold">
                                            {{ $users->firstWhere('id', $log->user_id)?->name ?? 'Unknown' }}
                                            <span class="text-crm-t3 font-normal">({{ $log->user_role }})</span>
                                        </span>
                                        <span class="text-[10px] text-crm-t3">{{ $log->created_at->format('n/j/Y g:ia') }}</span>
                                    </div>
                                    <div class="text-xs">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-semibold
                                            {{ str_starts_with($log->action, 'edited') ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600' }}">
                                            {{ str_replace('_', ' ', $log->action) }}
                                        </span>
                                        @if($log->section)
                                            <span class="text-crm-t3 ml-1">{{ str_replace('_', ' ', $log->section) }}</span>
                                        @endif
                                    </div>
                                    @if($log->changed_fields)
                                        <div class="mt-1.5 text-[10px] text-crm-t3">
                                            <span class="font-semibold">Changed:</span>
                                            {{ implode(', ', $log->changed_fields) }}
                                        </div>
                                    @endif
                                    @if($log->before_values && $log->after_values)
                                        <div class="mt-1 space-y-0.5">
                                            @foreach($log->changed_fields ?? [] as $field)
                                                @if(isset($log->before_values[$field]))
                                                    <div class="text-[10px]">
                                                        <span class="text-crm-t3">{{ $field }}:</span>
                                                        <span class="text-red-500 line-through">{{ $log->before_values[$field] ?? '' }}</span>
                                                        <span class="text-crm-t3">&rarr;</span>
                                                        <span class="text-emerald-600">{{ $log->after_values[$field] ?? '' }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($log->ip_address)
                                        <div class="text-[9px] text-crm-t3 mt-1">IP: {{ $log->ip_address }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-sm text-crm-t3">No audit history for this client.</p>
                        </div>
                    @endif
                @endif

                {{-- ═══ NOTES SECTION (always visible on detail panel) ═══ --}}
                <div class="border-t border-crm-border pt-4 mt-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Notes</div>
                        <span class="text-[10px] text-crm-t3">{{ $clientNotes->count() }} {{ Str::plural('note', $clientNotes->count()) }}</span>
                    </div>

                    @if($canAddNote)
                        <div class="mb-3 p-3 bg-indigo-50 rounded-lg border border-indigo-200">
                            <label for="fld-client-noteBody" class="text-[10px] text-indigo-700 uppercase tracking-wider font-semibold">Add Note</label>
                            <textarea id="fld-client-noteBody" wire:model="clientNoteBody" rows="2" placeholder="Type your note..." class="w-full mt-1 px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-indigo-400"></textarea>
                            <div class="flex justify-end mt-1.5">
                                <button wire:click="addClientNote" wire:loading.attr="disabled"
                                    class="px-4 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                                    <span wire:loading.remove wire:target="addClientNote">Save Note</span>
                                    <span wire:loading wire:target="addClientNote">Saving...</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    @forelse($clientNotes as $note)
                        <div class="mb-2 p-3 bg-white border border-crm-border rounded-lg">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="text-[10px] text-crm-t3">
                                    <span class="font-semibold text-crm-t1">{{ $users->get($note->created_by_user_id)?->name ?? 'Unknown' }}</span>
                                    &middot; {{ $note->created_at->format('M j, Y g:i A') }}
                                    @if($note->updated_by_user_id && $note->updated_at->gt($note->created_at->addSeconds(2)))
                                        <span class="text-amber-600">&middot; Edited {{ $note->updated_at->format('M j, Y g:i A') }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    @if($canAddNote && $clientEditingNoteId !== $note->id)
                                        <button wire:click="startEditClientNote({{ $note->id }})" class="text-[9px] text-blue-600 hover:text-blue-700 font-semibold">Edit</button>
                                    @endif
                                    @if($canSendNoteToChat && $clientSendNoteId !== $note->id)
                                        <button wire:click="openSendClientNoteToChat({{ $note->id }})" class="text-[9px] text-purple-600 hover:text-purple-700 font-semibold">Send to Chat</button>
                                    @endif
                                </div>
                            </div>

                            @if($clientEditingNoteId === $note->id)
                                <textarea id="fld-edit-cnote-{{ $note->id }}" wire:model="clientEditingNoteBody" rows="2" class="w-full px-3 py-1.5 text-sm bg-white border border-blue-300 rounded-lg focus:outline-none focus:border-blue-400"></textarea>
                                <div class="flex gap-1 mt-1">
                                    <button wire:click="saveEditClientNote" class="px-3 py-1 text-[10px] font-semibold text-white bg-blue-600 rounded hover:bg-blue-700 transition">Save</button>
                                    <button wire:click="cancelEditClientNote" class="px-3 py-1 text-[10px] font-semibold text-crm-t2 bg-gray-100 rounded hover:bg-gray-200 transition">Cancel</button>
                                </div>
                            @else
                                <div class="text-sm whitespace-pre-wrap">{{ $note->body }}</div>
                            @endif

                            @if($clientSendNoteId === $note->id)
                                <div class="mt-2 p-2 bg-purple-50 rounded border border-purple-200">
                                    <div class="text-[10px] text-purple-700 uppercase tracking-wider font-semibold mb-1">Send to Chat</div>
                                    <select id="fld-sendCNoteRecip-{{ $note->id }}" wire:model="clientSendNoteRecipientId" class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded-lg mb-1">
                                        <option value="">Select recipient...</option>
                                        @foreach($users->filter(fn($u) => in_array($u->role, ['admin', 'master_admin', 'closer'])) as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                                        @endforeach
                                    </select>
                                    <input id="fld-sendCNoteMsg-{{ $note->id }}" wire:model="clientSendNoteMessage" type="text" placeholder="Additional context (optional)..." class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded-lg mb-1">
                                    <div class="flex gap-1">
                                        <button wire:click="sendClientNoteToChat" class="px-3 py-1 text-[10px] font-semibold text-white bg-purple-600 rounded hover:bg-purple-700 transition">Send</button>
                                        <button wire:click="cancelSendClientNoteToChat" class="px-3 py-1 text-[10px] font-semibold text-crm-t2 bg-gray-100 rounded hover:bg-gray-200 transition">Cancel</button>
                                    </div>
                                </div>
                            @endif

                            @if($note->sent_to_chat_at)
                                <div class="mt-1 text-[9px] text-purple-500">Sent to chat {{ $note->sent_to_chat_at->format('M j, g:i A') }} by {{ $users->get($note->sent_to_chat_by_user_id)?->name ?? '?' }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <p class="text-xs text-crm-t3">No notes yet</p>
                        </div>
                    @endforelse
                </div>

                </div>
            </div>
        @endif
    </div>
</div>
