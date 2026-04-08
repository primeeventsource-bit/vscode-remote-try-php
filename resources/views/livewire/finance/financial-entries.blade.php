<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Financial Entries</h2>
            <p class="text-xs text-crm-t3 mt-1">Fees, reserves, payouts, deposits, and adjustments across all accounts</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/finance', 'label' => 'Dashboard'],
            ['href' => '/finance/accounts', 'label' => 'Merchant Accounts'],
            ['href' => '/finance/statements', 'label' => 'Statements'],
            ['href' => '/finance/transactions', 'label' => 'Transactions'],
            ['href' => '/finance/chargebacks', 'label' => 'Chargebacks'],
            ['href' => '/finance/entries', 'label' => 'Financial Entries', 'active' => true],
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
                <label class="block text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Type</label>
                <select wire:model.live="typeFilter" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Types</option>
                    <option value="fee">Fee</option>
                    <option value="reserve_hold">Reserve Hold</option>
                    <option value="reserve_release">Reserve Release</option>
                    <option value="payout">Payout</option>
                    <option value="deposit">Deposit</option>
                    <option value="adjustment">Adjustment</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Totals Summary Cards --}}
    @if(!empty($totals))
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
        @php
            $typeConfig = [
                'fee' => ['label' => 'Fees', 'color' => 'orange', 'prefix' => '-$'],
                'reserve_hold' => ['label' => 'Reserve Holds', 'color' => 'purple', 'prefix' => '-$'],
                'reserve_release' => ['label' => 'Reserve Releases', 'color' => 'emerald', 'prefix' => '+$'],
                'payout' => ['label' => 'Payouts', 'color' => 'blue', 'prefix' => '$'],
                'deposit' => ['label' => 'Deposits', 'color' => 'cyan', 'prefix' => '$'],
                'adjustment' => ['label' => 'Adjustments', 'color' => 'amber', 'prefix' => '$'],
            ];
        @endphp
        @foreach($typeConfig as $type => $config)
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-{{ $config['color'] }}-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">{{ $config['label'] }}</div>
            <div class="text-lg font-extrabold text-{{ $config['color'] }}-500 mt-1">
                {{ $config['prefix'] }}{{ number_format($totals[$type] ?? 0, 2) }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Entries Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-crm-surface">
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Date</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">MID</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Type</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Category</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Description</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Amount</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Reference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-crm-border">
                @forelse($entries as $entry)
                <tr class="hover:bg-crm-hover transition">
                    <td class="px-3 py-2 text-xs">{{ $entry->entry_date ?? ($entry->created_at?->format('M j, Y') ?? '-') }}</td>
                    <td class="px-3 py-2 text-xs">{{ $entry->merchantAccount->account_name ?? '-' }}</td>
                    <td class="px-3 py-2 text-center">
                        @php $type = $entry->entry_type ?? $entry->type ?? 'unknown'; @endphp
                        <span class="px-2 py-0.5 text-[9px] font-bold rounded-full
                            {{ $type === 'fee' ? 'bg-orange-100 text-orange-700' : '' }}
                            {{ $type === 'reserve_hold' ? 'bg-purple-100 text-purple-700' : '' }}
                            {{ $type === 'reserve_release' ? 'bg-emerald-100 text-emerald-700' : '' }}
                            {{ $type === 'payout' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $type === 'deposit' ? 'bg-cyan-100 text-cyan-700' : '' }}
                            {{ $type === 'adjustment' ? 'bg-amber-100 text-amber-700' : '' }}
                        ">{{ ucfirst(str_replace('_', ' ', $type)) }}</span>
                    </td>
                    <td class="px-3 py-2 text-xs">{{ $entry->category ?? '-' }}</td>
                    <td class="px-3 py-2 text-xs">{{ $entry->description ?? '-' }}</td>
                    <td class="px-3 py-2 text-right text-xs font-semibold
                        {{ in_array($type, ['fee', 'reserve_hold']) ? 'text-red-600' : '' }}
                        {{ in_array($type, ['reserve_release', 'deposit']) ? 'text-emerald-600' : '' }}
                        {{ $type === 'payout' ? 'text-blue-600' : '' }}
                        {{ $type === 'adjustment' ? 'text-amber-600' : '' }}
                    ">
                        {{ in_array($type, ['fee', 'reserve_hold']) ? '-' : '' }}${{ number_format(abs($entry->amount ?? 0), 2) }}
                    </td>
                    <td class="px-3 py-2 text-xs font-mono text-crm-t3">{{ $entry->reference ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-3 py-8 text-center text-sm text-crm-t3">No financial entries found matching your filters.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(method_exists($entries, 'links'))
    <div class="mt-4">
        {{ $entries->links() }}
    </div>
    @endif
</div>
