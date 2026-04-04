<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Transfers</h2>
        <p class="text-xs text-crm-t3 mt-1">Lead and deal transfers between team members</p>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-4">
        @foreach(['all' => 'All', 'fronter_closer' => 'Fronter → Closer', 'closer_admin' => 'Closer → Admin'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $filter === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="flex gap-4">
        {{-- Transfers Table --}}
        <div class="flex-1 bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Type</th>
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">From</th>
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">To</th>
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Lead / Deal</th>
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Amount</th>
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $transfer)
                            @php
                                $typeColor = match($transfer->type ?? 'lead') {
                                    'fronter_closer', 'lead' => 'bg-pink-50 text-pink-600',
                                    'closer_admin', 'deal' => 'bg-purple-50 text-purple-600',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                                $typeLabel = match($transfer->type ?? 'lead') {
                                    'fronter_closer' => 'F → C',
                                    'closer_admin' => 'C → A',
                                    'lead' => 'Lead',
                                    'deal' => 'Deal',
                                    default => ucfirst($transfer->type ?? 'Transfer'),
                                };
                                $from = $users->firstWhere('id', $transfer->from_user ?? $transfer->original_fronter ?? null);
                                $to = $users->firstWhere('id', $transfer->to_user ?? $transfer->transferred_to ?? $transfer->assigned_to ?? null);
                            @endphp
                            <tr wire:click="selectTransfer('{{ $transfer->ref_type }}', {{ $transfer->id }})" class="border-b border-crm-border cursor-pointer transition {{ (isset($selectedTransfer) && $selectedTransfer && $selectedTransfer->id === $transfer->id && $selectedTransfer->ref_type === $transfer->ref_type) ? 'bg-blue-50' : 'hover:bg-crm-hover' }}">
                                <td class="px-4 py-2.5">
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $typeColor }}">{{ $typeLabel }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($from)
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-[7px] font-bold text-white" style="background: {{ $from->color ?? '#6b7280' }}">{{ $from->avatar ?? substr($from->name, 0, 1) }}</div>
                                            <span class="text-xs">{{ $from->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-crm-t3 text-xs">--</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($to)
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-[7px] font-bold text-white" style="background: {{ $to->color ?? '#6b7280' }}">{{ $to->avatar ?? substr($to->name, 0, 1) }}</div>
                                            <span class="text-xs">{{ $to->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-crm-t3 text-xs">--</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 font-semibold text-xs">{{ $transfer->owner_name ?? $transfer->name ?? '--' }}</td>
                                <td class="px-4 py-2.5">
                                    @if(isset($transfer->fee) && $transfer->fee)
                                        <span class="font-mono font-bold text-emerald-500">${{ number_format($transfer->fee, 2) }}</span>
                                    @else
                                        <span class="text-crm-t3 text-xs">--</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-crm-t3 text-xs font-mono">{{ ($transfer->created_at ?? $transfer->timestamp)?->format('n/j/Y') ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-crm-t3 text-sm">No transfers found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Side Detail Panel --}}
        @if($selectedTransfer)
            <div class="w-80 flex-shrink-0 bg-crm-card border border-crm-border rounded-lg p-4 max-h-[70vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold">Transfer Detail</h4>
                    <button wire:click="$set('selectedId', null); $set('selectedType', null)" class="text-crm-t3 hover:text-crm-t1">&times;</button>
                </div>

                <div class="space-y-2">
                    @foreach([
                        'Name' => $selectedTransfer->owner_name ?? $selectedTransfer->name ?? '--',
                        'Type' => ucfirst(str_replace('_', ' ', $selectedTransfer->type ?? 'transfer')),
                        'Resort' => $selectedTransfer->resort_name ?? $selectedTransfer->resort ?? '--',
                    ] as $lbl => $val)
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">{{ $lbl }}</span>
                            <span class="font-semibold">{{ $val }}</span>
                        </div>
                    @endforeach

                    @if(isset($selectedTransfer->fee) && $selectedTransfer->fee)
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">Fee</span>
                            <span class="font-bold font-mono text-emerald-500">${{ number_format($selectedTransfer->fee, 2) }}</span>
                        </div>
                    @endif

                    @if(isset($selectedTransfer->phone1) && $selectedTransfer->phone1)
                        <div class="flex justify-between text-xs items-center" x-data="{ copied: false }">
                            <span class="text-crm-t3">Phone</span>
                            <span class="inline-flex items-center gap-1">
                                <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $selectedTransfer->phone1) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono hover:underline cursor-pointer" title="Click to copy">📞 {{ $selectedTransfer->phone1 }}</button>
                                <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                            </span>
                        </div>
                    @endif
                    @if(isset($selectedTransfer->primary_phone) && $selectedTransfer->primary_phone)
                        <div class="flex justify-between text-xs items-center" x-data="{ copied: false }">
                            <span class="text-crm-t3">Phone</span>
                            <span class="inline-flex items-center gap-1">
                                <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $selectedTransfer->primary_phone) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono hover:underline cursor-pointer" title="Click to copy">📞 {{ $selectedTransfer->primary_phone }}</button>
                                <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                            </span>
                        </div>
                    @endif

                    @php
                        $from = $users->firstWhere('id', $selectedTransfer->from_user ?? $selectedTransfer->original_fronter ?? null);
                        $to = $users->firstWhere('id', $selectedTransfer->to_user ?? $selectedTransfer->transferred_to ?? $selectedTransfer->assigned_to ?? null);
                    @endphp
                    <div class="border-t border-crm-border pt-2 mt-2">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Transfer Details</div>
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">From</span>
                            <span class="font-semibold">{{ $from->name ?? '--' }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">To</span>
                            <span class="font-semibold">{{ $to->name ?? '--' }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">Date</span>
                            <span class="font-mono">{{ ($selectedTransfer->created_at ?? $selectedTransfer->timestamp)?->format('n/j/Y g:i A') ?? '--' }}</span>
                        </div>
                    </div>

                    @if(isset($selectedTransfer->disposition) && $selectedTransfer->disposition)
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">Disposition</span>
                            <span class="font-semibold">{{ $selectedTransfer->disposition }}</span>
                        </div>
                    @endif
                    @if(isset($selectedTransfer->status) && $selectedTransfer->status)
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">Status</span>
                            <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $selectedTransfer->status)) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
