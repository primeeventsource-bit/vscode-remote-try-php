<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Transactions</h2>
            <p class="text-xs text-crm-t3 mt-1">Browse and filter all processed transactions across merchant accounts</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/finance', 'label' => 'Dashboard'],
            ['href' => '/finance/accounts', 'label' => 'Merchant Accounts'],
            ['href' => '/finance/statements', 'label' => 'Statements'],
            ['href' => '/finance/transactions', 'label' => 'Transactions', 'active' => true],
            ['href' => '/finance/chargebacks', 'label' => 'Chargebacks'],
            ['href' => '/finance/entries', 'label' => 'Financial Entries'],
            ['href' => '/finance/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
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
                    <option value="approved">Approved</option>
                    <option value="declined">Declined</option>
                    <option value="settled">Settled</option>
                    <option value="refunded">Refunded</option>
                    <option value="reversed">Reversed</option>
                    <option value="chargeback">Chargeback</option>
                </select>
            </div>
            <div>
                <label class="block text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Card Brand</label>
                <select wire:model.live="cardBrandFilter" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Brands</option>
                    <option value="Visa">Visa</option>
                    <option value="Mastercard">Mastercard</option>
                    <option value="Amex">Amex</option>
                    <option value="Discover">Discover</option>
                </select>
            </div>
            <div>
                <label class="block text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Per Page</label>
                <select wire:model.live="perPage" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Transactions Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-crm-surface">
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Date</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Reference</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Customer</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Card</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Amount</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Status</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">MID</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Source</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-crm-border">
                @forelse($transactions as $txn)
                <tr class="hover:bg-crm-hover transition">
                    <td class="px-3 py-2 text-xs">{{ $txn->transaction_date ?? ($txn->created_at?->format('M j, Y') ?? '-') }}</td>
                    <td class="px-3 py-2 text-xs font-mono text-crm-t3">{{ $txn->reference_number ?? $txn->id }}</td>
                    <td class="px-3 py-2 text-xs font-semibold">{{ $txn->customer_name ?? '-' }}</td>
                    <td class="px-3 py-2 text-xs">
                        @if($txn->card_brand)
                            <span class="font-semibold">{{ $txn->card_brand }}</span>
                            @if($txn->card_last4)
                                <span class="text-crm-t3">****{{ $txn->card_last4 }}</span>
                            @endif
                        @else
                            <span class="text-crm-t3">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right text-xs font-semibold">${{ number_format($txn->amount ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-center">
                        @php $status = $txn->status ?? 'unknown'; @endphp
                        <span class="px-2 py-0.5 text-[9px] font-bold rounded-full
                            {{ $status === 'approved' ? 'bg-emerald-100 text-emerald-700' : '' }}
                            {{ $status === 'settled' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $status === 'declined' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $status === 'refunded' ? 'bg-purple-100 text-purple-700' : '' }}
                            {{ $status === 'reversed' ? 'bg-orange-100 text-orange-700' : '' }}
                            {{ $status === 'chargeback' ? 'bg-red-100 text-red-700' : '' }}
                        ">{{ ucfirst($status) }}</span>
                    </td>
                    <td class="px-3 py-2 text-xs">{{ $txn->merchantAccount->account_name ?? '-' }}</td>
                    <td class="px-3 py-2 text-xs text-crm-t3">{{ $txn->source ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-3 py-8 text-center text-sm text-crm-t3">No transactions found matching your filters.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(method_exists($transactions, 'links'))
    <div class="mt-4">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
