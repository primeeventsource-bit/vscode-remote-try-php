<div class="p-5">
    @if(session('deal_success'))
        <div class="mb-3 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('deal_success') }}</div>
    @endif
    @if(session('deal_error'))
        <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm font-medium text-red-700">{{ session('deal_error') }}</div>
    @endif
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Deals</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ $deals->total() }} deals · Page {{ $deals->currentPage() }} of {{ $deals->lastPage() }}</p>
        </div>
        @if($isAdmin)
            <button wire:click="$set('showNewDeal', true)" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">+ New Deal</button>
        @endif
    </div>

    {{-- Status Filter --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-4">
        @foreach(['all' => 'All', 'pending' => 'Pending', 'charged' => 'Charged', 'chargeback' => 'Chargeback', 'cancelled' => 'Cancelled'] as $key => $label)
            <button wire:click="$set('statusFilter', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $statusFilter === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }}
            </button>
        @endforeach
        @if(isset($dealStatuses) && count($dealStatuses))
            @foreach($dealStatuses as $ds)
                <button wire:click="$set('statusFilter', '{{ $ds['value'] ?? $ds }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $statusFilter === ($ds['value'] ?? $ds) ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                    {{ $ds['label'] ?? ucfirst($ds) }}
                </button>
            @endforeach
        @endif
    </div>

    {{-- Deals Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Resort</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fee</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fronter</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closer</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Charged</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deals as $deal)
                        <tr wire:click="selectDeal({{ $deal->id }})" class="border-b border-crm-border cursor-pointer transition {{ (isset($active) && $active && $active->id === $deal->id) ? 'bg-blue-50 border-l-2 border-l-blue-500' : 'hover:bg-crm-hover' }}">
                            <td class="px-4 py-2.5 font-semibold">{{ $deal->owner_name }}</td>
                            <td class="px-4 py-2.5 text-crm-t2">{{ $deal->resort_name }}</td>
                            <td class="px-4 py-2.5 font-mono font-bold text-emerald-500">${{ number_format($deal->fee, 2) }}</td>
                            <td class="px-4 py-2.5 text-crm-t2">{{ $users->firstWhere('id', $deal->fronter)?->name ?? '--' }}</td>
                            <td class="px-4 py-2.5 text-crm-t2">{{ $users->firstWhere('id', $deal->closer)?->name ?? '--' }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $sColor = match(true) {
                                        $deal->charged_back === 'yes' => 'bg-red-50 text-red-500',
                                        $deal->charged === 'yes' => 'bg-emerald-50 text-emerald-600',
                                        $deal->status === 'cancelled' => 'bg-gray-100 text-gray-500',
                                        default => 'bg-amber-50 text-amber-600',
                                    };
                                    $sLabel = match(true) {
                                        $deal->charged_back === 'yes' => 'Chargeback',
                                        $deal->charged === 'yes' => 'Charged',
                                        $deal->status === 'cancelled' => 'Cancelled',
                                        default => ucfirst(str_replace('_', ' ', $deal->status ?? 'pending')),
                                    };
                                @endphp
                                <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $sColor }}">{{ $sLabel }}</span>
                                @if(($deal->disposition_status ?? '') === 'callback')
                                    <span class="text-[7px] font-semibold px-1 py-0.5 rounded bg-amber-100 text-amber-700 ml-0.5">CB {{ $deal->callback_date?->format('n/j') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-crm-t2">
                                @if($deal->charged === 'yes')
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600">Yes</span>
                                @else
                                    <span class="text-crm-t3 text-xs">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-crm-t3 text-xs font-mono">{{ $deal->timestamp?->format('n/j/Y') ?? '--' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-crm-t3 text-sm">No deals found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xs text-crm-t3">Show</span>
                <select id="fld-perPage" wire:model.live="perPage" class="px-2 py-1 text-xs bg-white border border-crm-border rounded-lg focus:outline-none">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-xs text-crm-t3">per page</span>
            </div>
            <div>{{ $deals->links() }}</div>
        </div>
    </div>

    {{-- Deal Detail Panel --}}
    @if($active)
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-bold">{{ $active->owner_name }} - Deal Detail</h3>
                    {{-- Disposition Badge --}}
                    @if(($active->disposition_status ?? '') === 'charged')
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700">Charged</span>
                    @elseif(($active->disposition_status ?? '') === 'callback')
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-700">Callback {{ $active->callback_date?->format('n/j g:i A') }}</span>
                    @elseif(($active->disposition_status ?? '') === 'declined')
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-700">Declined</span>
                    @endif
                    @if($active->is_locked ?? false)
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-gray-200 text-gray-600">Locked</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if($isAdmin)
                        <button wire:click="editDeal({{ $active->id }})" class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Edit Deal</button>
                    @endif
                    <button wire:click="selectDeal({{ $active->id }})" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>
            </div>

            {{-- Closing Date / Charged Date --}}
            <div class="flex gap-4 mb-3 text-xs">
                @if(!empty($active->closing_date ?? null))
                    <div><span class="text-crm-t3">Closing Date:</span> <span class="font-semibold">{{ $active->closing_date ?? null instanceof \Carbon\Carbon ? $active->closing_date?->format('M j, Y') : $active->closing_date ?? null }}</span></div>
                @endif
                @if(!empty($active->charged_date ?? null))
                    <div><span class="text-crm-t3">Charged Date:</span> <span class="font-semibold text-emerald-600">{{ $active->charged_date ?? null instanceof \Carbon\Carbon ? $active->charged_date?->format('M j, Y') : $active->charged_date ?? null }}</span></div>
                @endif
                @if(!empty($active->last_edited_by ?? null))
                    <div><span class="text-crm-t3">Last edit:</span> <span class="font-semibold">{{ $users->get($active->last_edited_by ?? null)?->name ?? '?' }} {{ $active->last_edited_at?->diffForHumans() }}</span></div>
                @endif
            </div>

            {{-- Admin Disposition Controls --}}
            @if($isAdmin && !($active->is_locked ?? false))
                <div class="mb-4 p-3 bg-crm-surface rounded-lg border border-crm-border">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Set Disposition</div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button wire:click="setDealDisposition({{ $active->id }}, 'charged', null, '{{ now()->format('Y-m-d') }}')" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition">Charged</button>
                        <div class="flex items-center gap-1">
                            <input id="dispo-cb-date" wire:model="dispoCallbackDate" type="datetime-local" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <button wire:click="setDealDisposition({{ $active->id }}, 'callback', $dispoCallbackDate)" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-amber-400 text-white hover:bg-amber-500 transition">Callback</button>
                        </div>
                        <button wire:click="setDealDisposition({{ $active->id }}, 'declined')" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-red-500 text-white hover:bg-red-600 transition">Declined</button>
                    </div>
                </div>
            @elseif($active->is_locked ?? false)
                <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600">This deal is locked. Only Master Admin can edit.</span>
                        @if(auth()->user()?->hasRole('master_admin'))
                            <button wire:click="reopenDeal({{ $active->id }})" class="px-3 py-1 text-[10px] font-semibold bg-blue-600 text-white rounded hover:bg-blue-700 transition">Reopen for Editing</button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Closer-to-Closer Transfer --}}
            @if(auth()->user()?->hasRole('closer', 'master_admin', 'admin') && !($active->is_locked ?? false) && $active->status !== 'charged')
                <div class="mb-4 p-3 bg-indigo-50 rounded-lg border border-indigo-200">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[10px] text-indigo-700 uppercase tracking-wider font-semibold">Transfer to Another Closer</div>
                        @if(!$showTransferModal)
                            <button wire:click="openTransferModal" class="px-3 py-1 text-[10px] font-semibold bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">Transfer</button>
                        @endif
                    </div>
                    @if($showTransferModal)
                        <div class="space-y-2">
                            <div>
                                <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Target Closer</label>
                                <select id="fld-transferToCloserId" wire:model="transferToCloserId" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-indigo-400">
                                    <option value="">Select closer...</option>
                                    @foreach($users->filter(fn($u) => $u->role === 'closer' && $u->id !== auth()->id()) as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Transfer Note <span class="text-red-500">*</span></label>
                                <textarea id="fld-transferNote" wire:model="transferNote" rows="2" placeholder="Why are you transferring this deal?" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-indigo-400"></textarea>
                            </div>
                            <div class="flex items-center gap-2" x-data="{ get canTransfer() { return $wire.transferToCloserId && ($wire.transferNote || '').trim().length > 0 } }">
                                <button
                                    wire:click="transferDealToCloser"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                    :disabled="!canTransfer"
                                    :class="canTransfer ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                                    class="px-4 py-1.5 text-xs font-semibold text-white rounded-lg transition">
                                    <span wire:loading.remove wire:target="transferDealToCloser">Transfer Deal</span>
                                    <span wire:loading wire:target="transferDealToCloser">Transferring...</span>
                                </button>
                                <button wire:click="$set('showTransferModal', false)" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-white border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Multi-Closer (Admin only, charged deals only) --}}
            @if($isAdmin && $active->charged === 'yes')
                @php
                    try { $dealClosers = \App\Models\DealCloser::where('deal_id', $active->id)->get(); } catch (\Throwable $e) { $dealClosers = collect(); }
                @endphp
                <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="text-[10px] text-blue-700 uppercase tracking-wider font-semibold mb-2">Closers on Deal ({{ $dealClosers->count() ?: 1 }} / {{ \App\Services\CommissionCalculator::MAX_CLOSERS }})</div>
                    <div class="space-y-1.5 mb-2">
                        @if($dealClosers->isEmpty())
                            <div class="flex items-center justify-between text-xs bg-white rounded p-2 border border-blue-100">
                                <span class="font-semibold">{{ $users->get($active->closer)?->name ?? 'Original Closer' }}</span>
                                <span class="text-[9px] text-blue-500">Original · ${{ number_format($active->closer_net_pay ?? 0, 2) }}</span>
                            </div>
                        @else
                            @foreach($dealClosers as $dc)
                                <div class="flex items-center justify-between text-xs bg-white rounded p-2 border border-blue-100">
                                    <span class="font-semibold">{{ $users->get($dc->user_id)?->name ?? 'Closer' }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] text-blue-500">{{ $dc->is_original ? 'Original' : 'Added' }} · {{ $dc->comm_pct }}% · ${{ number_format($dc->net_pay, 2) }}</span>
                                        @if(!$dc->is_original)
                                            <button wire:click="removeCloserFromDeal({{ $active->id }}, {{ $dc->user_id }})" class="text-[9px] text-red-500 hover:text-red-600 font-semibold">Remove</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    @if(($dealClosers->count() ?: 1) < \App\Services\CommissionCalculator::MAX_CLOSERS)
                        <div class="flex items-center gap-2">
                            <select id="add-closer-{{ $active->id }}" wire:model="addCloserId" class="flex-1 px-2 py-1 text-xs border border-blue-200 rounded">
                                <option value="">Add Closer...</option>
                                @foreach($users->filter(fn($u) => $u->role === 'closer' && $u->id !== $active->closer && !$dealClosers->contains('user_id', $u->id)) as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <button wire:click="addCloserToDeal({{ $active->id }})" class="px-3 py-1 text-[10px] font-semibold bg-blue-600 text-white rounded hover:bg-blue-700 transition">Add</button>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Owner & Contact --}}
            <div class="mb-4">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Owner & Contact Info</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach([
                        'Owner Name' => $active->owner_name,
                        'Mailing Address' => $active->mailing_address,
                        'City/State/Zip' => $active->city_state_zip,
                        'Email' => $active->email,
                    ] as $lbl => $val)
                        <div>
                            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">{{ $lbl }}</div>
                            <div class="text-sm font-semibold mt-0.5">{{ $val ?: '--' }}</div>
                        </div>
                    @endforeach
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Primary Phone</div>
                        <div class="mt-0.5">
                            @if($active->primary_phone)
                                <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                                    <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->primary_phone) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">📞 {{ $active->primary_phone }}</button>
                                    <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                                </span>
                            @else <span class="text-sm text-crm-t3">--</span> @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Secondary Phone</div>
                        <div class="mt-0.5">
                            @if($active->secondary_phone)
                                <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                                    <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->secondary_phone) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">📞 {{ $active->secondary_phone }}</button>
                                    <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                                </span>
                            @else <span class="text-sm text-crm-t3">--</span> @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Resort & Timeshare --}}
            <div class="mb-4 border-t border-crm-border pt-4">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Resort & Timeshare Info</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach([
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
                        'Was VD' => $active->was_vd,
                    ] as $lbl => $val)
                        <div>
                            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">{{ $lbl }}</div>
                            <div class="text-sm font-semibold mt-0.5">{{ $val ?: '--' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Deal & Financial --}}
            <div class="mb-4 border-t border-crm-border pt-4">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Deal & Financial</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Fee</div>
                        <div class="text-sm font-bold font-mono text-emerald-500 mt-0.5">${{ number_format($active->fee, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Fronter</div>
                        <div class="text-sm font-semibold mt-0.5">{{ $users->firstWhere('id', $active->fronter)?->name ?? '--' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Closer</div>
                        <div class="text-sm font-semibold mt-0.5">{{ $users->firstWhere('id', $active->closer)?->name ?? '--' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Admin</div>
                        <div class="text-sm font-semibold mt-0.5">{{ $users->firstWhere('id', $active->assigned_admin)?->name ?? '--' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Status</div>
                        <div class="text-sm font-semibold mt-0.5">{{ ucfirst(str_replace('_', ' ', $active->status ?? 'pending')) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged</div>
                        <div class="text-sm font-semibold mt-0.5">{{ $active->charged === 'yes' ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged Back</div>
                        <div class="text-sm font-semibold mt-0.5 {{ $active->charged_back === 'yes' ? 'text-red-500' : '' }}">{{ $active->charged_back === 'yes' ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deal Date</div>
                        <div class="text-sm font-semibold font-mono mt-0.5">{{ $active->timestamp?->format('n/j/Y') ?? '--' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged Date</div>
                        <div class="text-sm font-semibold font-mono mt-0.5">{{ $active->charged_date?->format('n/j/Y') ?? '--' }}</div>
                    </div>
                </div>
            </div>

            {{-- Card Info (admin only) --}}
            @if($isAdmin)
                <div class="mb-4 border-t border-crm-border pt-4">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Card Information</div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        @foreach([
                            'Name on Card' => $active->name_on_card,
                            'Card Type' => $active->card_type,
                            'Bank' => $active->bank,
                            'Card Number' => $active->card_number,
                            'Exp Date' => $active->exp_date,
                            'CV2' => $active->cv2,
                            'Billing Address' => $active->billing_address,
                            'Bank 2' => $active->bank2,
                            'Card Number 2' => $active->card_number2,
                            'Exp Date 2' => $active->exp_date2,
                            'CV2 2' => $active->cv2_2,
                        ] as $lbl => $val)
                            <div>
                                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">{{ $lbl }}</div>
                                <div class="text-sm font-mono mt-0.5">{{ $val ?: '--' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Additional Info --}}
            <div class="border-t border-crm-border pt-4">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Additional Info</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach([
                        'Verification #' => $active->verification_num,
                        'SNR' => $active->snr,
                        'Login' => $active->login,
                        'Login Info' => $active->login_info,
                        'Merchant' => $active->merchant,
                        'App Login' => $active->app_login,
                    ] as $lbl => $val)
                        <div>
                            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">{{ $lbl }}</div>
                            <div class="text-sm font-mono mt-0.5">{{ $val ?: '--' }}</div>
                        </div>
                    @endforeach
                </div>
                @if($active->notes)
                    <div class="mt-3">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Notes</div>
                        <div class="text-sm mt-0.5 whitespace-pre-wrap bg-white border border-crm-border rounded p-2">{{ $active->notes }}</div>
                    </div>
                @endif
            </div>

            {{-- ═══ NOTES SECTION ═══ --}}
            <div class="border-t border-crm-border pt-4 mt-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Notes Timeline</div>
                    <span class="text-[10px] text-crm-t3">{{ $dealNotes->count() }} {{ Str::plural('note', $dealNotes->count()) }}</span>
                </div>

                {{-- Add Note Form (ChristianDior only) --}}
                @if($canAddNote)
                    <div class="mb-3 p-3 bg-indigo-50 rounded-lg border border-indigo-200">
                        <label for="fld-deal-noteBody" class="text-[10px] text-indigo-700 uppercase tracking-wider font-semibold">Add Note</label>
                        <textarea id="fld-deal-noteBody" wire:model="noteBody" rows="2" placeholder="Type your note..." class="w-full mt-1 px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-indigo-400"></textarea>
                        <div class="flex justify-end mt-1.5">
                            <button wire:click="addDealNote" wire:loading.attr="disabled"
                                class="px-4 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="addDealNote">Save Note</span>
                                <span wire:loading wire:target="addDealNote">Saving...</span>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Notes list --}}
                @forelse($dealNotes as $note)
                    <div class="mb-2 p-3 bg-white border border-crm-border rounded-lg">
                        {{-- Note header --}}
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="text-[10px] text-crm-t3">
                                <span class="font-semibold text-crm-t1">{{ $users->get($note->created_by_user_id)?->name ?? 'Unknown' }}</span>
                                &middot; {{ $note->created_at->format('M j, Y g:i A') }}
                                @if($note->updated_by_user_id && $note->updated_at->gt($note->created_at->addSeconds(2)))
                                    <span class="text-amber-600">&middot; Edited {{ $note->updated_at->format('M j, Y g:i A') }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                @if($canAddNote && $editingNoteId !== $note->id)
                                    <button wire:click="startEditNote({{ $note->id }})" class="text-[9px] text-blue-600 hover:text-blue-700 font-semibold">Edit</button>
                                @endif
                                @if($canSendNoteToChat && $sendNoteToChatNoteId !== $note->id)
                                    <button wire:click="openSendNoteToChat({{ $note->id }})" class="text-[9px] text-purple-600 hover:text-purple-700 font-semibold">Send to Chat</button>
                                @endif
                            </div>
                        </div>

                        {{-- Note body or edit form --}}
                        @if($editingNoteId === $note->id)
                            <textarea id="fld-edit-note-{{ $note->id }}" wire:model="editingNoteBody" rows="2" class="w-full px-3 py-1.5 text-sm bg-white border border-blue-300 rounded-lg focus:outline-none focus:border-blue-400"></textarea>
                            <div class="flex gap-1 mt-1">
                                <button wire:click="saveEditNote" class="px-3 py-1 text-[10px] font-semibold text-white bg-blue-600 rounded hover:bg-blue-700 transition">
                                    <span wire:loading.remove wire:target="saveEditNote">Save</span>
                                    <span wire:loading wire:target="saveEditNote">Saving...</span>
                                </button>
                                <button wire:click="cancelEditNote" class="px-3 py-1 text-[10px] font-semibold text-crm-t2 bg-gray-100 rounded hover:bg-gray-200 transition">Cancel</button>
                            </div>
                        @else
                            <div class="text-sm whitespace-pre-wrap">{{ $note->body }}</div>
                        @endif

                        {{-- Send to Chat inline --}}
                        @if($sendNoteToChatNoteId === $note->id)
                            <div class="mt-2 p-2 bg-purple-50 rounded border border-purple-200">
                                <div class="text-[10px] text-purple-700 uppercase tracking-wider font-semibold mb-1">Send to Chat</div>
                                <select id="fld-sendNoteRecipient-{{ $note->id }}" wire:model="sendNoteToChatRecipientId" class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded-lg mb-1">
                                    <option value="">Select recipient...</option>
                                    @foreach($users->filter(fn($u) => in_array($u->role, ['admin', 'master_admin', 'closer'])) as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                                    @endforeach
                                </select>
                                <input id="fld-sendNoteMsg-{{ $note->id }}" wire:model="sendNoteToChatMessage" type="text" placeholder="Additional context (optional)..." class="w-full px-2 py-1 text-xs bg-white border border-crm-border rounded-lg mb-1">
                                <div class="flex gap-1">
                                    <button wire:click="sendNoteToChat" class="px-3 py-1 text-[10px] font-semibold text-white bg-purple-600 rounded hover:bg-purple-700 transition">
                                        <span wire:loading.remove wire:target="sendNoteToChat">Send</span>
                                        <span wire:loading wire:target="sendNoteToChat">Sending...</span>
                                    </button>
                                    <button wire:click="cancelSendNoteToChat" class="px-3 py-1 text-[10px] font-semibold text-crm-t2 bg-gray-100 rounded hover:bg-gray-200 transition">Cancel</button>
                                </div>
                            </div>
                        @endif

                        {{-- Sent to chat indicator --}}
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
    @endif

    {{-- New Deal Modal --}}
    @if(isset($showNewDeal) && $showNewDeal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showNewDeal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 mx-4 overflow-y-auto" style="max-height:80vh">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">New Deal</h3>
                    <button wire:click="$set('showNewDeal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach(['owner_name' => 'Owner Name', 'resort_name' => 'Resort Name', 'fee' => 'Fee ($)', 'primary_phone' => 'Primary Phone', 'secondary_phone' => 'Secondary Phone', 'email' => 'Email', 'mailing_address' => 'Mailing Address', 'city_state_zip' => 'City/State/Zip', 'resort_city_state' => 'Resort City/State', 'weeks' => 'Weeks', 'bed_bath' => 'Bed/Bath', 'usage' => 'Usage'] as $field => $label)
                        <div>
                            <label for="fld-newDeal-{{ $field }}" class="text-[10px] text-crm-t3 uppercase tracking-wider">{{ $label }}</label>
                                <input id="fld-newDeal-{{ $field }}" wire:model="newDeal.{{ $field }}" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    @endforeach
                    <div>
                        <label for="fld-newDeal-fronter" class="text-[10px] text-crm-t3 uppercase tracking-wider">Fronter</label>
                                <select id="fld-newDeal-fronter" wire:model="newDeal.fronter" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                            <option value="">Select...</option>
                            @foreach($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-newDeal-closer" class="text-[10px] text-crm-t3 uppercase tracking-wider">Closer</label>
                                <select id="fld-newDeal-closer" wire:model="newDeal.closer" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                            <option value="">Select...</option>
                            @foreach($users->whereIn('role', ['closer']) as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="fld-newDeal-notes" class="text-[10px] text-crm-t3 uppercase tracking-wider">Notes</label>
                                <textarea id="fld-newDeal-notes" wire:model="newDeal.notes" rows="3" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400"></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showNewDeal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="saveDeal" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Create Deal</button>
                </div>
            </div>
        </div>
    @endif

    {{-- AI Trainer Coach Panel --}}
    @if($active)
        <div class="mb-4">
            @livewire('ai-trainer-panel', ['module' => 'deals', 'entityId' => $selectedDeal], key('ai-trainer-deal-' . $selectedDeal))
        </div>
    @endif

    {{-- Edit Deal Modal --}}
    @if($showModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl mx-4 flex flex-col" style="max-height:90vh">
                {{-- Header (fixed) --}}
                <div class="flex items-center justify-between p-5 pb-3 border-b border-crm-border flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold">{{ !empty($dealForm['id']) ? 'Edit Deal #' . $dealForm['id'] : 'New Deal' }}</h3>
                        @if(!empty($dealForm['is_locked']))
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-gray-200 text-gray-600">Locked</span>
                        @else
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-green-100 text-green-700">Editable</span>
                        @endif
                    </div>
                    <button wire:click="$set('showModal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>

                @if(session('deal_error'))
                    <div class="mx-5 mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-600">{{ session('deal_error') }}</div>
                @endif

                {{-- Scrollable body --}}
                <div class="px-5 py-4 space-y-4" style="flex:1;overflow-y:auto;min-height:0">
                    {{-- Customer --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Customer Information</div>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(['owner_name' => 'Owner Name *', 'email' => 'Email', 'primary_phone' => 'Primary Phone', 'secondary_phone' => 'Secondary Phone', 'mailing_address' => 'Mailing Address', 'city_state_zip' => 'City/State/Zip'] as $field => $label)
                            <div>
                                <label for="edf-{{ $field }}" class="text-[10px] text-crm-t3 uppercase">{{ $label }}</label>
                                <input id="edf-{{ $field }}" wire:model="dealForm.{{ $field }}" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                            </div>
                        @endforeach
                    </div>

                    {{-- Property --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Property Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(['resort_name' => 'Resort Name', 'resort_city_state' => 'Resort Location', 'weeks' => 'Weeks', 'bed_bath' => 'Bed/Bath', 'usage' => 'Usage', 'exchange_group' => 'Exchange Group'] as $field => $label)
                            <div>
                                <label for="edf-{{ $field }}" class="text-[10px] text-crm-t3 uppercase">{{ $label }}</label>
                                <input id="edf-{{ $field }}" wire:model="dealForm.{{ $field }}" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                            </div>
                        @endforeach
                    </div>

                    {{-- Pricing --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Pricing</div>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach(['fee' => 'Fee ($) *', 'asking_rental' => 'Asking Rental', 'asking_sale_price' => 'Sale Price'] as $field => $label)
                            <div>
                                <label for="edf-{{ $field }}" class="text-[10px] text-crm-t3 uppercase">{{ $label }}</label>
                                <input id="edf-{{ $field }}" wire:model="dealForm.{{ $field }}" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                            </div>
                        @endforeach
                    </div>

                    {{-- Payment --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Payment Information</div>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(['name_on_card' => 'Name on Card', 'card_type' => 'Card Type', 'bank' => 'Bank', 'card_number' => 'Card Number', 'exp_date' => 'Exp Date', 'cv2' => 'CVV', 'billing_address' => 'Billing Address'] as $field => $label)
                            <div>
                                <label for="edf-{{ $field }}" class="text-[10px] text-crm-t3 uppercase">{{ $label }}</label>
                                <input id="edf-{{ $field }}" wire:model="dealForm.{{ $field }}" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                            </div>
                        @endforeach
                    </div>

                    {{-- Dates & Verification --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Dates & Verification</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="edf-closing_date" class="text-[10px] text-crm-t3 uppercase">Closing Date</label>
                                <input id="edf-closing_date" wire:model="dealForm.closing_date" type="date" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="edf-charged_date" class="text-[10px] text-crm-t3 uppercase">Charged Date</label>
                                <input id="edf-charged_date" wire:model="dealForm.charged_date" type="date" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="edf-verification_num" class="text-[10px] text-crm-t3 uppercase">Verification #</label>
                                <input id="edf-verification_num" wire:model="dealForm.verification_num" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                    </div>

                    {{-- Assignment --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edf-assigned_admin" class="text-[10px] text-crm-t3 uppercase">Assigned Admin</label>
                                <select id="edf-assigned_admin" wire:model="dealForm.assigned_admin" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                                <option value="">None</option>
                                @foreach($users->filter(fn($u) => $u->hasRole('master_admin', 'admin')) as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="edf-status" class="text-[10px] text-crm-t3 uppercase">Status</label>
                                <select id="edf-status" wire:model="dealForm.status" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                                @foreach($dealStatuses as $ds)
                                    <option value="{{ $ds['value'] }}">{{ $ds['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label for="edf-notes" class="text-[10px] text-crm-t3 uppercase">Notes</label>
                                <textarea id="edf-notes" wire:model="dealForm.notes" rows="3" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label for="edf-login_info" class="text-[10px] text-crm-t3 uppercase">Login Info</label>
                                <textarea id="edf-login_info" wire:model="dealForm.login_info" rows="2" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg"></textarea>
                    </div>

                </div>

                {{-- Sticky footer --}}
                <div class="flex items-center justify-between p-5 pt-3 border-t border-crm-border bg-white rounded-b-xl flex-shrink-0">
                    <button wire:click="$set('showModal', false)" class="px-5 py-2.5 text-sm font-semibold text-crm-t2 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <div class="flex gap-2">
                        <button wire:click="saveDeal" class="px-6 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-800 shadow-lg transition">Save Deal</button>
                        @if(auth()->user()?->hasRole('master_admin', 'admin') && !empty($dealForm['id']))
                            <button wire:click="saveAndLockDeal" class="px-5 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow transition">Save & Lock</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
