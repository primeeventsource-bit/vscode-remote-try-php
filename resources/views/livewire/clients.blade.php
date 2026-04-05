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
                            'audit' => 'Audit Log',
                        ];
                    @endphp
                    @foreach($tabs as $tabKey => $tabLabel)
                        @php
                            $tabVisible = match($tabKey) {
                                'deal_sheet' => $canViewDealSheet,
                                'banking' => $canViewBanking,
                                'payment' => $canViewPayment,
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

                </div>
            </div>
        @endif
    </div>
</div>
