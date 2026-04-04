<div class="p-5" x-data="{ open: @entangle('selectedId').live }">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-xl font-bold">Chargebacks</h2>
            <p class="text-xs text-crm-t3 mt-1">Dispute tracking, events, and outcomes</p>
        </div>
        <button wire:click="clearFilters" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-crm-border hover:bg-crm-hover transition">
            Clear Filters
        </button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-3 mb-4">
        <input id="fld-search" wire:model.live.debounce.300ms="search" type="text" placeholder="Search dispute #, reason..." class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" />
        <input id="fld-startDate" wire:model.live.debounce.500ms="startDate" type="date" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" />
        <input id="fld-endDate" wire:model.live.debounce.500ms="endDate" type="date" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" />

        <select id="fld-processorId" wire:model.live.debounce.200ms="processorId" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
            <option value="">All Processors</option>
            @foreach($processors as $p)
                <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
            @endforeach
        </select>

        <select id="fld-salesRepId" wire:model.live.debounce.200ms="salesRepId" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
            <option value="">All Reps</option>
            @foreach($salesReps as $rep)
                <option value="{{ $rep['id'] }}">{{ $rep['name'] }}</option>
            @endforeach
        </select>

        <select id="fld-merchantAccountId" wire:model.live.debounce.200ms="merchantAccountId" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
            <option value="">All MIDs</option>
            @foreach($merchantAccounts as $m)
                <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
            @endforeach
        </select>

        <select id="fld-status" wire:model.live.debounce.200ms="status" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
            <option value="">All Statuses</option>
            @foreach($statuses as $s)
                <option value="{{ $s }}">{{ ucfirst($s) }}</option>
            @endforeach
        </select>

        <select id="fld-reasonCode" wire:model.live.debounce.200ms="reasonCode" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
            <option value="">All Reason Codes</option>
            @foreach($reasonCodes as $rc)
                <option value="{{ $rc }}">{{ $rc }}</option>
            @endforeach
        </select>

        <select id="fld-cardBrand" wire:model.live.debounce.200ms="cardBrand" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
            <option value="">All Brands</option>
            @foreach($cardBrands as $cb)
                <option value="{{ $cb }}">{{ $cb }}</option>
            @endforeach
        </select>

        <select id="fld-paymentMethod" wire:model.live.debounce.200ms="paymentMethod" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
            <option value="">All Payment Methods</option>
            @foreach($paymentMethods as $pm)
                <option value="{{ $pm }}">{{ $pm }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Dispute Date</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Amount</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Reason</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Rep</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Processor / MID</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:click="openDetail({{ $row->id }})" class="border-b border-crm-border cursor-pointer transition {{ $selectedId === $row->id ? 'bg-blue-50' : 'hover:bg-crm-hover' }}">
                            <td class="px-4 py-2.5">{{ optional($row->dispute_date)->format('n/j/Y') ?: '--' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="text-[10px] px-2 py-1 rounded-full font-semibold {{ in_array($row->status, ['lost', 'chargeback']) ? 'bg-red-50 text-red-600' : (in_array($row->status, ['won', 'reversed']) ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-700') }}">
                                    {{ ucfirst($row->status ?? 'pending') }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 font-semibold">${{ number_format((float) $row->chargeback_amount, 2) }}</td>
                            <td class="px-4 py-2.5">{{ $row->reason_code ?: 'Unknown' }}</td>
                            <td class="px-4 py-2.5">{{ optional($row->salesRep)->name ?: '--' }}</td>
                            <td class="px-4 py-2.5">{{ optional($row->processor)->name ?: '--' }} / {{ optional($row->merchantAccount)->name ?: '--' }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $row->dispute_reference_number ?: '--' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-crm-t3">No chargebacks found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-crm-border bg-white">
            {{ $rows->links() }}
        </div>
    </div>

    @if($selected)
        <div class="fixed inset-0 z-40 bg-black/25" wire:click="closeDetail"></div>
        <div class="fixed right-0 top-0 h-full w-full max-w-xl bg-white border-l border-crm-border shadow-2xl z-50 overflow-y-auto">
            <div class="p-4 border-b border-crm-border flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">Chargeback #{{ $selected->id }}</div>
                    <div class="text-xs text-crm-t3 mt-1">{{ $selected->dispute_reference_number ?: 'No dispute reference' }}</div>
                </div>
                <button wire:click="closeDetail" class="text-crm-t3 hover:text-crm-t1 text-sm">Close</button>
            </div>

            <div class="p-4 space-y-4">
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="border border-crm-border rounded-lg p-2">
                        <div class="text-crm-t3">Amount</div>
                        <div class="font-semibold mt-1">${{ number_format((float) $selected->chargeback_amount, 2) }}</div>
                    </div>
                    <div class="border border-crm-border rounded-lg p-2">
                        <div class="text-crm-t3">Status</div>
                        <div class="font-semibold mt-1">{{ ucfirst($selected->status) }}</div>
                    </div>
                    <div class="border border-crm-border rounded-lg p-2">
                        <div class="text-crm-t3">Reason</div>
                        <div class="font-semibold mt-1">{{ $selected->reason_code ?: 'Unknown' }}</div>
                    </div>
                    <div class="border border-crm-border rounded-lg p-2">
                        <div class="text-crm-t3">Dispute Date</div>
                        <div class="font-semibold mt-1">{{ optional($selected->dispute_date)->format('n/j/Y') ?: '--' }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-xs font-semibold mb-2">Quick Status Update</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['pending', 'under_review', 'won', 'lost', 'refunded', 'prevented'] as $st)
                            <button wire:click="updateStatus('{{ $st }}')" class="px-2.5 py-1 text-[11px] rounded-full border border-crm-border {{ $selected->status === $st ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-crm-hover' }}">
                                {{ ucfirst(str_replace('_', ' ', $st)) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="text-xs font-semibold mb-2">Add Note</div>
                    <textarea id="fld-newNote" wire:model="newNote" rows="3" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg" placeholder="Add context, representment notes, deadlines, outcomes..."></textarea>
                    <button wire:click="addNote" class="mt-2 px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Save Note</button>
                </div>

                <div>
                    <div class="text-xs font-semibold mb-2">Event Timeline</div>
                    <div class="space-y-2">
                        @forelse($selected->events->sortByDesc('event_date') as $event)
                            <div class="border border-crm-border rounded-lg p-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</span>
                                    <span class="text-crm-t3">{{ optional($event->event_date)->format('n/j/Y g:i A') ?: '--' }}</span>
                                </div>
                                @if($event->old_status || $event->new_status)
                                    <div class="text-crm-t3 mt-1">{{ $event->old_status ?: '--' }} -> {{ $event->new_status ?: '--' }}</div>
                                @endif
                                @if($event->notes)
                                    <div class="mt-1">{{ $event->notes }}</div>
                                @endif
                                <div class="text-crm-t3 mt-1">By: {{ optional($event->performer)->name ?: 'System' }}</div>
                            </div>
                        @empty
                            <div class="text-xs text-crm-t3">No events recorded.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
