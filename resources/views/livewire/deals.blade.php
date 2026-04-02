<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Deals</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ $deals->count() }} deals</p>
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
                                @if($deal->disposition_status === 'callback')
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
    </div>

    {{-- Deal Detail Panel --}}
    @if($active)
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-bold">{{ $active->owner_name }} - Deal Detail</h3>
                    {{-- Disposition Badge --}}
                    @if($active->disposition_status === 'charged')
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700">Charged</span>
                    @elseif($active->disposition_status === 'callback')
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-700">Callback {{ $active->callback_date?->format('n/j g:i A') }}</span>
                    @elseif($active->disposition_status === 'declined')
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-700">Declined</span>
                    @endif
                    @if($active->is_locked)
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
                @if($active->closing_date)
                    <div><span class="text-crm-t3">Closing Date:</span> <span class="font-semibold">{{ $active->closing_date->format('M j, Y') }}</span></div>
                @endif
                @if($active->charged_date)
                    <div><span class="text-crm-t3">Charged Date:</span> <span class="font-semibold text-emerald-600">{{ $active->charged_date->format('M j, Y') }}</span></div>
                @endif
                @if($active->last_edited_by)
                    <div><span class="text-crm-t3">Last edit:</span> <span class="font-semibold">{{ $users->get($active->last_edited_by)?->name ?? '?' }} {{ $active->last_edited_at?->diffForHumans() }}</span></div>
                @endif
            </div>

            {{-- Admin Disposition Controls --}}
            @if($isAdmin && !$active->is_locked)
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
            @elseif($isAdmin && $active->is_locked)
                <div class="mb-4 p-3 bg-emerald-50 rounded-lg border border-emerald-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-emerald-700">Deal is locked (charged)</span>
                        <button wire:click="unlockDeal({{ $active->id }})" class="px-2 py-1 text-[10px] font-semibold bg-white border border-crm-border rounded hover:bg-crm-hover transition">Unlock</button>
                    </div>
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
                                <a href="sip:{{ $active->primary_phone }}" class="text-blue-600 font-semibold font-mono text-sm">{{ $active->primary_phone }}</a>
                            @else <span class="text-sm text-crm-t3">--</span> @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Secondary Phone</div>
                        <div class="mt-0.5">
                            @if($active->secondary_phone)
                                <a href="sip:{{ $active->secondary_phone }}" class="text-blue-600 font-semibold font-mono text-sm">{{ $active->secondary_phone }}</a>
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
        </div>
    @endif

    {{-- New Deal Modal --}}
    @if(isset($showNewDeal) && $showNewDeal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showNewDeal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 mx-4 max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">New Deal</h3>
                    <button wire:click="$set('showNewDeal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach(['owner_name' => 'Owner Name', 'resort_name' => 'Resort Name', 'fee' => 'Fee ($)', 'primary_phone' => 'Primary Phone', 'secondary_phone' => 'Secondary Phone', 'email' => 'Email', 'mailing_address' => 'Mailing Address', 'city_state_zip' => 'City/State/Zip', 'resort_city_state' => 'Resort City/State', 'weeks' => 'Weeks', 'bed_bath' => 'Bed/Bath', 'usage' => 'Usage'] as $field => $label)
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">{{ $label }}</label>
                            <input id="fld-newDeal-{{ $field }}" wire:model="newDeal.{{ $field }}" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    @endforeach
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Fronter</label>
                        <select id="fld-newDeal-fronter" wire:model="newDeal.fronter" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                            <option value="">Select...</option>
                            @foreach($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Closer</label>
                        <select id="fld-newDeal-closer" wire:model="newDeal.closer" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                            <option value="">Select...</option>
                            @foreach($users->whereIn('role', ['closer']) as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Notes</label>
                    <textarea id="fld-newDeal-notes" wire:model="newDeal.notes" rows="3" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400"></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showNewDeal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="saveDeal" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Create Deal</button>
                </div>
            </div>
        </div>
    @endif
</div>
