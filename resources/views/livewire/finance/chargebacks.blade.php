<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Chargebacks</h2>
            <p class="text-xs text-crm-t3 mt-1">Track, manage, and resolve chargeback cases across all merchant accounts</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/finance', 'label' => 'Dashboard'],
            ['href' => '/finance/accounts', 'label' => 'Merchant Accounts'],
            ['href' => '/finance/statements', 'label' => 'Statements'],
            ['href' => '/finance/transactions', 'label' => 'Transactions'],
            ['href' => '/finance/chargebacks', 'label' => 'Chargebacks', 'active' => true],
            ['href' => '/finance/entries', 'label' => 'Financial Entries'],
            ['href' => '/finance/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- Tab Buttons --}}
    <div class="flex flex-wrap gap-2 mb-5">
        <button wire:click="$set('tab', 'all')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'all' ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">
            All <span class="ml-1 px-1.5 py-0.5 text-[9px] rounded-full {{ $tab === 'all' ? 'bg-blue-500' : 'bg-crm-surface' }}">{{ $counts['all'] ?? 0 }}</span>
        </button>
        <button wire:click="$set('tab', 'open')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'open' ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">
            Open <span class="ml-1 px-1.5 py-0.5 text-[9px] rounded-full {{ $tab === 'open' ? 'bg-blue-500' : 'bg-crm-surface' }}">{{ $counts['open'] ?? 0 }}</span>
        </button>
        <button wire:click="$set('tab', 'due_soon')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'due_soon' ? 'bg-amber-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">
            Due Soon <span class="ml-1 px-1.5 py-0.5 text-[9px] rounded-full {{ $tab === 'due_soon' ? 'bg-amber-500' : 'bg-crm-surface' }}">{{ $counts['due_soon'] ?? 0 }}</span>
        </button>
        <button wire:click="$set('tab', 'won')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'won' ? 'bg-emerald-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">
            Won <span class="ml-1 px-1.5 py-0.5 text-[9px] rounded-full {{ $tab === 'won' ? 'bg-emerald-500' : 'bg-crm-surface' }}">{{ $counts['won'] ?? 0 }}</span>
        </button>
        <button wire:click="$set('tab', 'lost')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'lost' ? 'bg-red-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">
            Lost <span class="ml-1 px-1.5 py-0.5 text-[9px] rounded-full {{ $tab === 'lost' ? 'bg-red-500' : 'bg-crm-surface' }}">{{ $counts['lost'] ?? 0 }}</span>
        </button>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-3 mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">MID</label>
                <select wire:model.live="midFilter" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All MIDs</option>
                    @foreach($mids as $mid)
                        <option value="{{ $mid->id }}">{{ $mid->account_name }} ({{ $mid->mid_number }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Status</label>
                <select wire:model.live="statusFilter" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="under_review">Under Review</option>
                    <option value="won">Won</option>
                    <option value="lost">Lost</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Chargebacks Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-crm-surface">
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">CB ID</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">MID</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Amount</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Card Brand</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Reason Code</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Status</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Opened</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Due Date</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Outcome</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-crm-border">
                @forelse($chargebacks as $cb)
                <tr class="hover:bg-crm-hover transition">
                    <td class="px-3 py-2 text-xs font-mono font-semibold">{{ $cb->case_number ?? $cb->id }}</td>
                    <td class="px-3 py-2 text-xs">{{ $cb->merchantAccount->account_name ?? '-' }}</td>
                    <td class="px-3 py-2 text-right text-xs font-semibold text-red-600">${{ number_format($cb->amount ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-xs">{{ $cb->card_brand ?? '-' }}</td>
                    <td class="px-3 py-2 text-xs font-mono">{{ $cb->reason_code ?? '-' }}</td>
                    <td class="px-3 py-2 text-center">
                        @php $status = $cb->status ?? 'open'; @endphp
                        <span class="px-2 py-0.5 text-[9px] font-bold rounded-full
                            {{ $status === 'open' ? 'bg-amber-100 text-amber-700' : '' }}
                            {{ $status === 'under_review' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $status === 'won' ? 'bg-emerald-100 text-emerald-700' : '' }}
                            {{ $status === 'lost' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $status === 'closed' ? 'bg-gray-100 text-gray-700' : '' }}
                        ">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                    </td>
                    <td class="px-3 py-2 text-xs">{{ $cb->opened_date ?? ($cb->created_at?->format('M j, Y') ?? '-') }}</td>
                    <td class="px-3 py-2 text-xs">
                        @if($cb->due_date)
                            @php $isOverdue = \Carbon\Carbon::parse($cb->due_date)->isPast() && !in_array($cb->status, ['won', 'lost', 'closed']); @endphp
                            <span class="{{ $isOverdue ? 'px-2 py-0.5 bg-red-100 text-red-700 rounded-full font-bold text-[9px]' : '' }}">
                                {{ \Carbon\Carbon::parse($cb->due_date)->format('M j, Y') }}
                                @if($isOverdue) (OVERDUE) @endif
                            </span>
                        @else
                            <span class="text-crm-t3">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if($cb->outcome)
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $cb->outcome === 'won' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($cb->outcome) }}</span>
                        @else
                            <span class="text-[9px] text-crm-t3">Pending</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if(!in_array($cb->status, ['won', 'lost', 'closed']))
                        <div class="flex items-center justify-center gap-1">
                            <button wire:click="updateStatus({{ $cb->id }}, 'closed', 'won')" class="px-2 py-0.5 text-[9px] font-semibold bg-emerald-50 text-emerald-600 rounded hover:bg-emerald-100 transition">Mark Won</button>
                            <button wire:click="updateStatus({{ $cb->id }}, 'closed', 'lost')" class="px-2 py-0.5 text-[9px] font-semibold bg-red-50 text-red-600 rounded hover:bg-red-100 transition">Mark Lost</button>
                        </div>
                        @else
                            <span class="text-[9px] text-crm-t3">Resolved</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-3 py-8 text-center text-sm text-crm-t3">No chargebacks found matching your filters.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(method_exists($chargebacks, 'links'))
    <div class="mt-4">
        {{ $chargebacks->links() }}
    </div>
    @endif
</div>
