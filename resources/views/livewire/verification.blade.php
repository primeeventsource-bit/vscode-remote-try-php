<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Verification</h2>
        <p class="text-xs text-crm-t3 mt-1">Manage deal verification and charging</p>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-4">
        @foreach([
            'pending' => 'Pending',
            'verifying' => 'Verifying',
            'charged' => 'Charged',
            'cb' => 'CB',
            'cancelled' => 'Cancelled',
            'all' => 'All',
        ] as $key => $label)
            @php
                $count = match($key) {
                    'pending' => $counts['pending'] ?? 0,
                    'verifying' => $counts['verifying'] ?? 0,
                    'charged' => $counts['charged'] ?? 0,
                    'cb' => $counts['chargeback'] ?? 0,
                    'cancelled' => $counts['cancelled'] ?? 0,
                    'all' => $counts['all'] ?? 0,
                };
            @endphp
            <button wire:click="$set('tab', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }} <span class="ml-1 text-[10px] opacity-70">({{ $count }})</span>
            </button>
        @endforeach
    </div>

    <div class="flex gap-4">
        {{-- Table --}}
        <div class="flex-1 bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Resort</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fee</th>
                            @if($isAdmin)
                                <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Card Info</th>
                            @endif
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closer</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Admin</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Date</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deals as $deal)
                            <tr wire:click="selectDeal({{ $deal->id }})" class="border-b border-crm-border cursor-pointer transition {{ ($selectedDeal && $selectedDeal === $deal->id) ? 'bg-blue-50' : 'hover:bg-crm-hover' }}">
                                <td class="px-3 py-2.5">
                                    @php
                                        $sColor = match(true) {
                                            $deal->charged_back === 'yes' => 'bg-red-50 text-red-500',
                                            $deal->charged === 'yes' => 'bg-emerald-50 text-emerald-600',
                                            $deal->status === 'in_verification' => 'bg-blue-50 text-blue-600',
                                            $deal->status === 'cancelled' => 'bg-gray-100 text-gray-500',
                                            default => 'bg-amber-50 text-amber-600',
                                        };
                                        $sLabel = match(true) {
                                            $deal->charged_back === 'yes' => 'CB',
                                            $deal->charged === 'yes' => 'Charged',
                                            $deal->status === 'in_verification' => 'Verifying',
                                            $deal->status === 'cancelled' => 'Cancelled',
                                            default => 'Pending',
                                        };
                                    @endphp
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $sColor }}">{{ $sLabel }}</span>
                                </td>
                                <td class="px-3 py-2.5 font-semibold">{{ $deal->owner_name }}</td>
                                <td class="px-3 py-2.5 text-crm-t2">{{ $deal->resort_name }}</td>
                                <td class="px-3 py-2.5 font-mono font-bold text-emerald-500">${{ number_format($deal->fee, 2) }}</td>
                                @if($isAdmin)
                                    <td class="px-3 py-2.5 font-mono text-xs text-crm-t2">
                                        {{ $deal->masked_card ?? '--' }}
                                    </td>
                                @endif
                                <td class="px-3 py-2.5 text-crm-t2">{{ $users->firstWhere('id', $deal->closer)?->name ?? '--' }}</td>
                                <td class="px-3 py-2.5 text-crm-t2">{{ $users->firstWhere('id', $deal->assigned_admin)?->name ?? '--' }}</td>
                                <td class="px-3 py-2.5 text-crm-t3 text-xs font-mono">{{ $deal->timestamp?->format('n/j/Y') ?? '--' }}</td>
                                <td class="px-3 py-2.5" wire:click.stop>
                                    <div class="flex flex-wrap gap-1">
                                        @if(!$deal->status || $deal->status === 'pending_admin')
                                            <button wire:click="startVerification({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition">Start</button>
                                        @endif
                                        @if($deal->status === 'in_verification')
                                            <button wire:click="chargeDeal({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition">Charge</button>
                                            <button wire:click="cancelDeal({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-500 hover:bg-red-100 transition">Cancel</button>
                                            <button wire:click="markCallback({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-600 hover:bg-amber-100 transition">CB</button>
                                        @endif
                                        @if($deal->charged === 'yes' && $deal->charged_back !== 'yes')
                                            <button wire:click="markChargeback({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-500 hover:bg-red-100 transition">Mark CB</button>
                                        @endif
                                        @if($deal->charged_back === 'yes')
                                            <button wire:click="reverseChargeback({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition">Reverse CB</button>
                                        @endif
                                        @if($deal->status === 'cancelled')
                                            <button wire:click="reactivateDeal({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition">Reactivate</button>
                                        @endif
                                        @if($deal->status === 'callback')
                                            <button wire:click="startVerification({{ $deal->id }})" class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition">Back</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 9 : 8 }}" class="px-4 py-8 text-center text-crm-t3 text-sm">No deals in this tab</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-3 flex items-center justify-between px-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-crm-t3">Show</span>
                    <select wire:model.live="perPage" class="px-2 py-1 text-xs bg-white border border-crm-border rounded-lg focus:outline-none">
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

        {{-- Detail Panel (right side) --}}
        @if($activeDeal)
            <div class="w-80 flex-shrink-0 bg-crm-card border border-crm-border rounded-lg p-4 max-h-[70vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold">{{ $activeDeal->owner_name }}</h4>
                    <button wire:click="$set('selectedDeal', null)" class="text-crm-t3 hover:text-crm-t1">&times;</button>
                </div>

                <div class="space-y-2 mb-4">
                    @foreach([
                        'Resort' => $activeDeal->resort_name,
                        'Fee' => '$'.number_format($activeDeal->fee, 2),
                        'Closer' => $users->firstWhere('id', $activeDeal->closer)?->name ?? '--',
                        'Fronter' => $users->firstWhere('id', $activeDeal->fronter)?->name ?? '--',
                        'Status' => ucfirst(str_replace('_', ' ', $activeDeal->status ?? 'pending')),
                        'Phone' => $activeDeal->primary_phone,
                        'Email' => $activeDeal->email,
                        'Date' => $activeDeal->timestamp?->format('n/j/Y'),
                    ] as $lbl => $val)
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">{{ $lbl }}</span>
                            <span class="font-semibold text-right">{{ $val ?: '--' }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Notes --}}
                <div class="border-t border-crm-border pt-3">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Notes</div>
                    @if($activeDeal->notes)
                        <div class="text-xs whitespace-pre-wrap bg-white border border-crm-border rounded p-2 mb-2 max-h-40 overflow-y-auto">{{ $activeDeal->notes }}</div>
                    @else
                        <p class="text-xs text-crm-t3 mb-2">No notes yet</p>
                    @endif
                    <div class="flex gap-1">
                        <input id="fld-noteInput" wire:model="noteInput" type="text" placeholder="Add a note..." class="flex-1 px-2 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        <button wire:click="addNote({{ $activeDeal->id }})" class="px-2 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Add</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
