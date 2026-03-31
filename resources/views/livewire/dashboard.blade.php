<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Dashboard</h2>
        <p class="text-xs text-crm-t3 mt-1">PRIME CRM</p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        @if(!$isCloser)
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Total Leads</div>
            <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $totalLeads }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $assignedLeads }} assigned</div>
        </div>
        @endif
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deals This Week</div>
            <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $weekDeals->count() }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $weekCharged->count() }} charged</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged Revenue (Week)</div>
            <div class="text-2xl font-extrabold text-emerald-500 mt-1">${{ number_format($weekRev) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $weekCharged->count() }} deals | All-time: ${{ number_format($totalRev) }}</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Chargebacks</div>
            <div class="text-2xl font-extrabold text-red-500 mt-1">${{ number_format($cbRev) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $chargebacks->count() }} deals</div>
        </div>
    </div>

    {{-- Deal Status + Revenue --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">Deal Status Breakdown</div>
            @foreach([
                ['Charged', $charged->count(), 'bg-emerald-500'],
                ['Pending', $pending->count(), 'bg-amber-500'],
                ['Chargebacks', $chargebacks->count(), 'bg-red-500'],
                ['Cancelled', $cancelled->count(), 'bg-gray-400'],
            ] as [$label, $count, $color])
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-3 h-3 rounded-sm {{ $color }}"></div>
                    <span class="flex-1 text-sm">{{ $label }}</span>
                    <span class="font-mono font-semibold text-sm">{{ $count }}</span>
                    <span class="text-[10px] text-crm-t3">
                        ({{ $deals->count() > 0 ? round($count / $deals->count() * 100) : 0 }}%)
                    </span>
                </div>
            @endforeach
        </div>

        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">Revenue Split</div>
            @foreach([
                ['Charged', $totalRev, 'text-emerald-500'],
                ['Chargebacks', $cbRev, 'text-red-500'],
                ['Pending', $pendRev, 'text-amber-500'],
            ] as [$label, $amount, $color])
                <div class="flex items-center gap-3 mb-3">
                    <span class="flex-1 text-sm">{{ $label }}</span>
                    <span class="font-mono font-bold {{ $color }}">${{ number_format($amount) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Deals + Top Closers --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">Recent Deals</div>
            @foreach($recentDeals as $d)
                <div class="flex justify-between items-center py-2 border-b border-crm-border text-sm">
                    <div>
                        <span class="font-semibold">{{ $d->owner_name }}</span>
                        <span class="text-crm-t3 text-[10px] ml-2">{{ $d->resort_name }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-emerald-500">${{ number_format($d->fee) }}</span>
                        @if($d->charged_back === 'yes')
                            <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-500">CB</span>
                        @elseif($d->charged === 'yes')
                            <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-500">Charged</span>
                        @else
                            <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-500">Pending</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">Top Closers</div>
            @foreach($closers as $c)
                <div class="flex items-center gap-3 py-2 border-b border-crm-border">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-semibold text-white"
                         style="background: {{ $c['user']->color }}">{{ $c['user']->avatar }}</div>
                    <span class="flex-1 text-sm font-medium">{{ $c['user']->name }}</span>
                    <div class="text-right">
                        <div class="text-sm font-bold font-mono">{{ $c['count'] }} deals</div>
                        <div class="text-[10px] text-emerald-500">${{ number_format($c['rev']) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
