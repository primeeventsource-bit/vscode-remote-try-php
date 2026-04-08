<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Payroll Finance Dashboard</h2>
            <p class="text-xs text-crm-t3 mt-1">Company profitability overview and payroll summary</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/payroll-v2', 'label' => 'Dashboard', 'active' => true],
            ['href' => '/payroll-v2/deals', 'label' => 'Deals'],
            ['href' => '/payroll-v2/batches', 'label' => 'Batches'],
            ['href' => '/payroll-v2/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- ═══ SUMMARY CARDS — ROW 1 ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
        {{-- Gross This Week --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-blue-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Gross This Week</div>
            <div class="text-lg font-extrabold text-blue-600 mt-1">${{ number_format($cards['gross_week'] ?? 0, 2) }}</div>
        </div>
        {{-- Gross This Month --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-blue-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Gross This Month</div>
            <div class="text-lg font-extrabold text-blue-600 mt-1">${{ number_format($cards['gross_month'] ?? 0, 2) }}</div>
        </div>
        {{-- Total Payroll This Week --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-orange-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Total Payroll This Week</div>
            <div class="text-lg font-extrabold text-orange-600 mt-1">${{ number_format($cards['payroll_week'] ?? 0, 2) }}</div>
        </div>
        {{-- Total Payroll This Month --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-orange-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Total Payroll This Month</div>
            <div class="text-lg font-extrabold text-orange-600 mt-1">${{ number_format($cards['payroll_month'] ?? 0, 2) }}</div>
        </div>
    </div>

    {{-- ═══ SUMMARY CARDS — ROW 2 ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        {{-- Company Net This Week --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-emerald-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Company Net This Week</div>
            <div class="text-lg font-extrabold text-emerald-600 mt-1">${{ number_format($cards['net_week'] ?? 0, 2) }}</div>
        </div>
        {{-- Company Net This Month --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-emerald-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Company Net This Month</div>
            <div class="text-lg font-extrabold text-emerald-600 mt-1">${{ number_format($cards['net_month'] ?? 0, 2) }}</div>
        </div>
        {{-- Chargebacks This Week --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-red-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Chargebacks This Week</div>
            <div class="text-lg font-extrabold text-red-600 mt-1">${{ number_format($cards['cb_week'] ?? 0, 2) }}</div>
        </div>
        {{-- Refunds This Week --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-red-500">
            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Refunds This Week</div>
            <div class="text-lg font-extrabold text-red-600 mt-1">${{ number_format($cards['refund_week'] ?? 0, 2) }}</div>
        </div>
    </div>

    {{-- ═══ PROFITABLE DEALS TABLE ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-6">
        <div class="text-sm font-bold mb-3">Profitable Deals Table</div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border">
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner</th>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse($profitableDeals ?? [] as $pf)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-2 py-2 font-semibold">{{ $pf->deal->owner_name ?? '--' }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($pf->gross_amount, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($pf->fronter_commission, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($pf->closer_commission, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($pf->admin_commission, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($pf->processing_fee, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($pf->reserve_fee, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($pf->marketing_cost, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono font-bold {{ $pf->company_net >= 0 ? 'text-emerald-600' : 'text-red-600' }}">${{ number_format($pf->company_net, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $pf->company_net >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($pf->company_net_percent, 1) }}%</td>
                            <td class="px-2 py-2 text-center">
                                @php $ps = $pf->deal->payroll_status ?? 'pending'; @endphp
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
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-2 py-4 text-crm-t3 text-center">No profitable deals found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ RECENT BATCHES ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
        <div class="text-sm font-bold mb-3">Recent Batches</div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border">
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Batch Name</th>
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Period</th>
                        <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Total Gross</th>
                        <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Company Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches ?? [] as $batch)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-2 py-2 font-semibold">{{ $batch->batch_name }}</td>
                            <td class="px-2 py-2 text-crm-t3">{{ \Carbon\Carbon::parse($batch->period_start)->format('M d') }} &mdash; {{ \Carbon\Carbon::parse($batch->period_end)->format('M d, Y') }}</td>
                            <td class="px-2 py-2 text-center">
                                @php $bs = $batch->batch_status ?? 'draft'; @endphp
                                <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full
                                    @if($bs === 'paid') bg-emerald-100 text-emerald-700
                                    @elseif($bs === 'approved') bg-blue-100 text-blue-700
                                    @elseif($bs === 'locked') bg-purple-100 text-purple-700
                                    @elseif($bs === 'void') bg-red-100 text-red-700
                                    @else bg-gray-100 text-gray-700
                                    @endif
                                ">{{ ucfirst($bs) }}</span>
                            </td>
                            <td class="px-2 py-2 text-right font-mono">${{ number_format($batch->total_gross, 2) }}</td>
                            <td class="px-2 py-2 text-right font-mono font-bold {{ ($batch->total_company_net ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">${{ number_format($batch->total_company_net, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-4 text-crm-t3 text-center">No batches found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
