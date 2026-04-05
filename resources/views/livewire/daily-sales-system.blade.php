<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Daily Sales System</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ now()->format('l, F j, Y') }} — Track daily performance and hit targets</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-crm-t3 font-semibold">Period:</span>
            <select id="fld-daily-period" wire:model.live="period" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg">
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
            </select>
        </div>
    </div>

    {{-- My Daily Progress --}}
    <div class="mb-6">
        <div class="text-xs text-crm-t3 uppercase tracking-wider font-semibold mb-3">My Daily Progress</div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @foreach([
                ['label' => 'Calls', 'actual' => $myProgress['calls']['actual'], 'target' => $myProgress['calls']['target'], 'color' => 'blue'],
                ['label' => 'Contacts', 'actual' => $myProgress['contacts']['actual'], 'target' => $myProgress['contacts']['target'], 'color' => 'purple'],
                ['label' => 'Transfers', 'actual' => $myProgress['transfers']['actual'], 'target' => $myProgress['transfers']['target'], 'color' => 'amber'],
                ['label' => 'Deals Closed', 'actual' => $myProgress['deals']['actual'], 'target' => $myProgress['deals']['target'], 'color' => 'emerald'],
                ['label' => 'Revenue', 'actual' => $myProgress['revenue']['actual'], 'target' => $myProgress['revenue']['target'], 'color' => 'emerald', 'money' => true],
            ] as $kpi)
                @php
                    $pct = ($kpi['target'] ?? 0) > 0 ? min(100, round(($kpi['actual'] / $kpi['target']) * 100)) : 0;
                    $val = ($kpi['money'] ?? false) ? '$' . number_format($kpi['actual']) : $kpi['actual'];
                    $tgt = ($kpi['money'] ?? false) ? '$' . number_format($kpi['target']) : $kpi['target'];
                @endphp
                <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">{{ $kpi['label'] }}</div>
                    <div class="text-2xl font-extrabold text-{{ $kpi['color'] }}-500 mt-1">{{ $val }}</div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-[10px] text-crm-t3">Target: {{ $tgt }}</span>
                        <span class="text-[10px] font-bold {{ $pct >= 100 ? 'text-emerald-600' : ($pct >= 50 ? 'text-amber-600' : 'text-red-500') }}">{{ $pct }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                        <div class="h-full rounded-full bg-{{ $kpi['color'] }}-500 transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Objections Today --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Objections Handled Today</div>
                <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $myProgress['objections'] }}</div>
            </div>
            <a href="{{ route('sales-training') }}" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Open Sales Training</a>
        </div>
    </div>

    {{-- Team Summary (Admin only) --}}
    @if($isAdmin)
        <div class="mb-6">
            <div class="text-xs text-crm-t3 uppercase tracking-wider font-semibold mb-3">Team Summary — Today</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Team Calls</div>
                    <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $teamSummary['total_calls'] }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Team Deals</div>
                    <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ $teamSummary['total_deals'] }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Team Revenue</div>
                    <div class="text-2xl font-extrabold text-purple-500 mt-1">${{ number_format($teamSummary['total_revenue']) }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Active Reps</div>
                    <div class="text-2xl font-extrabold text-amber-500 mt-1">{{ $teamSummary['active_reps'] }}</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Leaderboard --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-crm-border bg-crm-surface">
            <div class="text-sm font-bold">Leaderboard</div>
        </div>
        @forelse($leaderboard as $i => $rep)
            <div class="flex items-center gap-3 px-4 py-3 border-b border-crm-border last:border-0 hover:bg-crm-hover transition">
                <span class="w-6 text-center text-sm font-extrabold {{ $i === 0 ? 'text-amber-500' : ($i === 1 ? 'text-gray-400' : ($i === 2 ? 'text-amber-700' : 'text-crm-t3')) }}">{{ $i + 1 }}</span>
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[9px] font-bold text-white flex-shrink-0" style="background: {{ $rep['color'] ?? '#6b7280' }}">{{ $rep['avatar'] ?? '--' }}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold">{{ $rep['name'] }}</div>
                    <div class="text-[10px] text-crm-t3">{{ ucfirst(str_replace('_', ' ', $rep['role'] ?? '')) }} &middot; {{ $rep['deals'] }} deals &middot; {{ $rep['calls'] }} calls</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold font-mono text-emerald-600">${{ number_format($rep['revenue']) }}</div>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-sm text-crm-t3">No performance data yet for this period</div>
        @endforelse
    </div>
</div>
