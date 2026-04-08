<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Payroll Deals</h2>
            <p class="text-xs text-crm-t3 mt-1">All deal financials with commission breakdowns</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/payroll-v2', 'label' => 'Dashboard'],
            ['href' => '/payroll-v2/deals', 'label' => 'Deals', 'active' => true],
            ['href' => '/payroll-v2/batches', 'label' => 'Batches'],
            ['href' => '/payroll-v2/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- ═══ FILTER BAR ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-3 mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="fld-statusFilter" class="text-[10px] font-semibold text-crm-t3 uppercase tracking-wider">Status</label>
                <select id="fld-statusFilter" wire:model.live="statusFilter" class="mt-0.5 block w-full px-3 py-1.5 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="calculated">Calculated</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                    <option value="disputed">Disputed</option>
                    <option value="void">Void</option>
                </select>
            </div>
            <div>
                <label for="fld-disputedFilter" class="text-[10px] font-semibold text-crm-t3 uppercase tracking-wider">Disputed</label>
                <select id="fld-disputedFilter" wire:model.live="disputedFilter" class="mt-0.5 block w-full px-3 py-1.5 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All</option>
                    <option value="disputed">Disputed Only</option>
                    <option value="reversed">Reversed Only</option>
                </select>
            </div>
            <div>
                <label for="fld-perPage" class="text-[10px] font-semibold text-crm-t3 uppercase tracking-wider">Per Page</label>
                <select id="fld-perPage" wire:model.live="perPage" class="mt-0.5 block w-full px-3 py-1.5 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ═══ DEALS TABLE ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deal ID</th>
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Client</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Gross</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fronter</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closer</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Admin</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Processing</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Reserve</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Marketing</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Company Net</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Net %</th>
                        <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Locked</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($financials ?? [] as $financial)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-2 py-2">
                                <a href="/payroll-v2/deals/{{ $financial->deal->id }}" class="text-blue-600 hover:underline font-semibold">#{{ $financial->deal->id }}</a>
                            </td>
                            <td class="px-2 py-2 font-semibold">{{ $financial->deal->owner_name ?? '--' }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($financial->gross_amount, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($financial->fronter_commission, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($financial->closer_commission, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($financial->admin_commission, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($financial->processing_fee, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($financial->reserve_fee, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($financial->marketing_cost, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono font-bold {{ $financial->company_net >= 0 ? 'text-emerald-600' : 'text-red-600' }}">${{ number_format($financial->company_net, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $financial->company_net >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($financial->company_net_percent, 1) }}%</td>
                            <td class="px-2 py-2 text-center">
                                @php $ps = $financial->deal->payroll_status ?? 'pending'; @endphp
                                <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full
                                    @if($ps === 'paid') bg-emerald-100 text-emerald-700
                                    @elseif($ps === 'approved') bg-blue-100 text-blue-700
                                    @elseif($ps === 'calculated') bg-blue-100 text-blue-700
                                    @elseif($ps === 'disputed') bg-red-100 text-red-700
                                    @elseif($ps === 'void') bg-red-100 text-red-700
                                    @else bg-amber-100 text-amber-700
                                    @endif
                                ">{{ ucfirst($ps) }}</span>
                            </td>
                            <td class="px-2 py-2 text-center">
                                @if($financial->is_locked)
                                    <svg class="w-4 h-4 text-purple-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-2 py-4 text-crm-t3 text-center">No deal financials found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(method_exists($financials, 'links'))
            <div class="px-4 py-3 border-t border-crm-border">
                {{ $financials->links() }}
            </div>
        @endif
    </div>
</div>
