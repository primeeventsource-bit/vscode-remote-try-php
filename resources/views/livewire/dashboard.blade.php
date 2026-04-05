<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Dashboard</h2>
            <p class="text-xs text-crm-t3 mt-1">PRIME CRM · {{ auth()->user()->name }}
                @if($isFronter) · Fronter
                @elseif($isCloser) · Closer
                @elseif($isAdmin) · Admin
                @elseif($isMaster) · Master Admin
                @endif
            </p>
        </div>
        {{-- Pipeline Stats Range Dropdown --}}
        <div class="flex items-center gap-2">
            <span class="text-xs text-crm-t3 font-semibold">View By:</span>
            <select id="fld-dash-statsRange" wire:model.live="statsRange" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                <option value="live">Live</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
            </select>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         MY PIPELINE STATS (role-scoped, per user)
    ══════════════════════════════════════════════ --}}
    @if($userRole === 'fronter')
        {{-- FRONTER: My Pipeline Stats --}}
        <div class="mb-6">
            <div class="text-xs text-crm-t3 uppercase tracking-wider font-semibold mb-3">My Pipeline Performance</div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Transfers Sent</div>
                    <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $pipelineStats['transfers_sent'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Deals Closed</div>
                    <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ $pipelineStats['deals_closed'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-400">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My No Deals</div>
                    <div class="text-2xl font-extrabold text-red-500 mt-1">{{ $pipelineStats['no_deals'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Close %</div>
                    <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ number_format($pipelineStats['close_pct'] ?? 0, 1) }}%</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-gray-400">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My No-Deal %</div>
                    <div class="text-2xl font-extrabold text-gray-500 mt-1">{{ number_format($pipelineStats['no_deal_pct'] ?? 0, 1) }}%</div>
                </div>
            </div>
        </div>

    @elseif($userRole === 'closer')
        {{-- CLOSER: My Pipeline Stats --}}
        <div class="mb-6">
            <div class="text-xs text-crm-t3 uppercase tracking-wider font-semibold mb-3">My Pipeline Performance</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Transfers Received</div>
                    <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $pipelineStats['transfers_received'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Deals Closed</div>
                    <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ $pipelineStats['deals_closed'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Sent to Verification</div>
                    <div class="text-2xl font-extrabold text-amber-500 mt-1">{{ $pipelineStats['sent_to_verification'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-400">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Not Closed</div>
                    <div class="text-2xl font-extrabold text-red-500 mt-1">{{ $pipelineStats['not_closed'] ?? 0 }}</div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Close %</div>
                    <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ number_format($pipelineStats['close_pct'] ?? 0, 1) }}%</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-gray-400">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My No-Close %</div>
                    <div class="text-2xl font-extrabold text-gray-500 mt-1">{{ number_format($pipelineStats['no_close_pct'] ?? 0, 1) }}%</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-indigo-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Verification %</div>
                    <div class="text-2xl font-extrabold text-indigo-500 mt-1">{{ number_format($pipelineStats['verification_pct'] ?? 0, 1) }}%</div>
                </div>
            </div>
        </div>

    @elseif($userRole === 'admin')
        {{-- ADMIN: My Pipeline Stats --}}
        <div class="mb-6">
            <div class="text-xs text-crm-t3 uppercase tracking-wider font-semibold mb-3">My Verification Performance</div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Received</div>
                    <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $pipelineStats['received'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Charged / Green</div>
                    <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ $pipelineStats['charged_green'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-400">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Not Charged</div>
                    <div class="text-2xl font-extrabold text-red-500 mt-1">{{ $pipelineStats['not_charged'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Charge %</div>
                    <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ number_format($pipelineStats['charge_pct'] ?? 0, 1) }}%</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-gray-400">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Not Charged %</div>
                    <div class="text-2xl font-extrabold text-gray-500 mt-1">{{ number_format($pipelineStats['not_charged_pct'] ?? 0, 1) }}%</div>
                </div>
            </div>
        </div>

    @elseif($userRole === 'master_admin')
        {{-- MASTER ADMIN: Company-wide Pipeline Summary --}}
        <div class="mb-6">
            <div class="text-xs text-crm-t3 uppercase tracking-wider font-semibold mb-3">Company Pipeline Summary</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Total Transfers</div>
                    <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $pipelineStats['total_transfers'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deals Closed</div>
                    <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $pipelineStats['total_deals_closed'] ?? 0 }}</div>
                    <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($pipelineStats['transfer_to_deal_pct'] ?? 0, 1) }}% conversion</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Sent to Verification</div>
                    <div class="text-2xl font-extrabold text-amber-500 mt-1">{{ $pipelineStats['total_sent_to_verification'] ?? 0 }}</div>
                </div>
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged / Green</div>
                    <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ $pipelineStats['total_charged_green'] ?? 0 }}</div>
                    <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($pipelineStats['overall_conversion_pct'] ?? 0, 1) }}% overall</div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════
         TASK WIDGET
    ══════════════════════════════════════════════ --}}
    @if(($taskWidget['open'] ?? 0) > 0 || ($taskWidget['overdue'] ?? 0) > 0)
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <a href="{{ route('tasks') }}" class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-red-500 hover:bg-crm-hover transition">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Overdue Tasks</div>
                <div class="text-xl font-extrabold text-red-500 mt-0.5">{{ $taskWidget['overdue'] ?? 0 }}</div>
            </a>
            <a href="{{ route('tasks') }}" class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-amber-500 hover:bg-crm-hover transition">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Due Today</div>
                <div class="text-xl font-extrabold text-amber-500 mt-0.5">{{ $taskWidget['due_today'] ?? 0 }}</div>
            </a>
            <a href="{{ route('tasks') }}" class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-blue-500 hover:bg-crm-hover transition">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Open Tasks</div>
                <div class="text-xl font-extrabold text-blue-500 mt-0.5">{{ $taskWidget['open'] ?? 0 }}</div>
            </a>
            <a href="{{ route('tasks') }}" class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-purple-500 hover:bg-crm-hover transition">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Urgent</div>
                <div class="text-xl font-extrabold text-purple-500 mt-0.5">{{ $taskWidget['urgent'] ?? 0 }}</div>
            </a>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════
         KPI CARDS (existing - scoped by role)
    ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @if($isCloser)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Deals This Week</div>
                <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $weekDeals->count() }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $weekCharged->count() }} charged</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Revenue (Week)</div>
                <div class="text-2xl font-extrabold text-emerald-500 mt-1">${{ number_format($weekRev) }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">All-time: ${{ number_format($totalRev) }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Charged Deals</div>
                <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $charged->count() }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $pending->count() }} pending</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Chargebacks</div>
                <div class="text-2xl font-extrabold text-red-500 mt-1">{{ $chargebacks->count() }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">${{ number_format($chargebacks->sum('fee')) }}</div>
            </div>
        @elseif($isFronter)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Total Leads</div>
                <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $totalLeads }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $assignedLeads }} assigned to me</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Transfers</div>
                <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ $pipelineStats['transfers_sent'] ?? 0 }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Became Deals</div>
                <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $pipelineStats['deals_closed'] ?? 0 }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Close Rate</div>
                <div class="text-2xl font-extrabold text-amber-500 mt-1">{{ number_format($pipelineStats['close_pct'] ?? 0, 1) }}%</div>
            </div>
        @else
            {{-- Admin / Master --}}
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Total Leads</div>
                <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $totalLeads }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $assignedLeads }} assigned</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deals This Week</div>
                <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $weekDeals->count() }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $weekCharged->count() }} charged</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged Revenue (Week)</div>
                <div class="text-2xl font-extrabold text-emerald-500 mt-1">${{ number_format($weekRev) }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">All-time: ${{ number_format($totalRev) }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Chargebacks</div>
                <div class="text-2xl font-extrabold text-red-500 mt-1">${{ number_format($cbRev) }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $chargebacks->count() }} deals</div>
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════
         CHARTS ROW 1 — Monthly Revenue + Deal Status
    ══════════════════════════════════════════════ --}}
    @if($deals->isNotEmpty())
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
        <div class="lg:col-span-3 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">{{ ($isCloser || $isFronter) ? 'My Monthly Revenue' : 'Monthly Charged Revenue' }}</div>
            <div class="text-[10px] text-crm-t3 mb-3">Last 6 months</div>
            @php
                $maxRev = (float) ($monthlyData->max('rev') ?: 1);
                $barW = 28; $gap = 8; $padL = 6; $padR = 6;
                $svgW = $padL + count($monthlyData) * ($barW + $gap) - $gap + $padR;
                $maxH = 70;
            @endphp
            <svg viewBox="0 0 {{ $svgW }} 110" class="w-full h-auto" preserveAspectRatio="xMidYMid meet" style="max-height: 180px;">
                @for ($gl = 0; $gl <= 4; $gl++)
                    @php $gy = $maxH - ($gl / 4) * $maxH; @endphp
                    <line x1="{{ $padL - 4 }}" y1="{{ $gy }}" x2="{{ $svgW - $padR }}" y2="{{ $gy }}" stroke="#e5e7eb" stroke-width="0.5"/>
                @endfor
                @foreach($monthlyData as $i => $m)
                    @php
                        $barH = max(2, ($m['rev'] / $maxRev) * $maxH);
                        $x = $padL + $i * ($barW + $gap);
                        $y = $maxH - $barH;
                        $isCurrent = $i === count($monthlyData) - 1;
                    @endphp
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barW }}" height="{{ $barH }}" rx="2" fill="{{ $isCurrent ? '#3b82f6' : '#93c5fd' }}"/>
                    @if($m['rev'] > 0)
                        <text x="{{ $x + $barW / 2 }}" y="{{ max(8, $y - 2) }}" text-anchor="middle" font-size="5.5" fill="{{ $isCurrent ? '#1d4ed8' : '#6b7280' }}" font-weight="{{ $isCurrent ? 'bold' : 'normal' }}">${{ $m['rev'] >= 1000 ? number_format($m['rev'] / 1000, 1) . 'k' : number_format($m['rev']) }}</text>
                    @endif
                    <text x="{{ $x + $barW / 2 }}" y="{{ $maxH + 11 }}" text-anchor="middle" font-size="7" fill="{{ $isCurrent ? '#1d4ed8' : '#9ca3af' }}">{{ $m['label'] }}</text>
                @endforeach
            </svg>
        </div>

        <div class="lg:col-span-2 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">{{ ($isCloser || $isFronter) ? 'My Deal Status' : 'Deal Status' }}</div>
            <div class="text-[10px] text-crm-t3 mb-3">All time</div>
            @php
                $dTotal = $charged->count() + $pending->count() + $chargebacks->count() + $cancelled->count();
                $segs = [
                    ['count' => $charged->count(), 'color' => '#10b981', 'label' => 'Charged'],
                    ['count' => $pending->count(), 'color' => '#f59e0b', 'label' => 'Pending'],
                    ['count' => $chargebacks->count(), 'color' => '#ef4444', 'label' => 'CB'],
                    ['count' => $cancelled->count(), 'color' => '#9ca3af', 'label' => 'Cancelled'],
                ];
            @endphp
            <div class="space-y-3.5">
                @foreach($segs as $seg)
                    @php $pct = $dTotal > 0 ? ($seg['count'] / $dTotal) * 100 : 0; @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background:{{ $seg['color'] }}"></span>
                                <span class="text-xs text-crm-t2">{{ $seg['label'] }}</span>
                            </div>
                            <span class="text-xs font-semibold font-mono">{{ $seg['count'] }}</span>
                        </div>
                        <div class="h-3 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-3 rounded-full transition-all" style="width: {{ $pct }}%; background: {{ $seg['color'] }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         TOP CLOSERS + REVENUE SPLIT + RECENT (Master Admin only)
    ══════════════════════════════════════════════ --}}
    @if($isMaster)
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
        <div class="lg:col-span-2 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">Top Closers</div>
            <div class="text-[10px] text-crm-t3 mb-4">Ranked by charged revenue</div>
            @php $maxCloserRev = (float)($closers->max('rev') ?: 1); @endphp
            @forelse($closers->take(8) as $c)
                @php $pct = $c['rev'] > 0 ? min(100, ($c['rev'] / $maxCloserRev) * 100) : 0; @endphp
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2">
                            <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white" style="background:{{ $c['user']->color ?? '#6b7280' }}">{{ $c['user']->avatar ?? '?' }}</div>
                            <span class="text-xs font-medium">{{ $c['user']->name }}</span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xs font-bold font-mono text-emerald-600">${{ number_format($c['rev']) }}</span>
                            <span class="text-[10px] text-crm-t3">{{ $c['count'] }}d</span>
                        </div>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-1.5 rounded-full transition-all" style="width:{{ number_format($pct, 1) }}%;background:{{ $c['user']->color ?? '#3b82f6' }}"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-crm-t3 py-4 text-center">No closer data.</p>
            @endforelse
        </div>

        <div class="lg:col-span-3 space-y-4">
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-semibold mb-3">Revenue Split</div>
                @php $revTotal = max(1, $totalRev + $cbRev + $pendRev); @endphp
                @foreach([['Charged', $totalRev, '#10b981'], ['Pending', $pendRev, '#f59e0b'], ['Chargebacks', $cbRev, '#ef4444']] as [$rl, $rv, $rc])
                    @php $rPct = min(100, ($rv / $revTotal) * 100); @endphp
                    <div class="mb-2">
                        <div class="flex justify-between mb-0.5">
                            <span class="text-xs text-crm-t2">{{ $rl }}</span>
                            <span class="text-xs font-bold font-mono" style="color:{{ $rc }}">${{ number_format($rv) }}</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-1.5 rounded-full" style="width:{{ number_format($rPct, 1) }}%;background:{{ $rc }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-semibold mb-3">Recent Deals</div>
                @foreach($recentDeals as $d)
                    <div class="flex items-center justify-between py-1.5 border-b border-crm-border last:border-0 text-sm">
                        <div class="min-w-0">
                            <span class="font-semibold truncate block">{{ $d->owner_name }}</span>
                            <span class="text-crm-t3 text-[10px]">{{ $d->resort_name }}</span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="font-mono font-bold text-emerald-500">${{ number_format($d->fee) }}</span>
                            @if($d->charged_back === 'yes')
                                <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-500">CB</span>
                            @elseif($d->charged === 'yes')
                                <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600">Charged</span>
                            @else
                                <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-600">Pending</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         CHARGEBACK SECTION (Admin/Master only)
    ══════════════════════════════════════════════ --}}
    @if($isMaster || $isAdmin)
        <livewire:chargeback-dashboard-section />
    @endif

    {{-- ══════════════════════════════════════════════
         CLOSER: My Recent Deals
    ══════════════════════════════════════════════ --}}
    @if($isCloser && $deals->isNotEmpty())
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-6">
        <div class="text-sm font-semibold mb-3">My Recent Deals</div>
        @forelse($deals->sortByDesc('id')->take(8) as $d)
            <div class="flex items-center justify-between py-1.5 border-b border-crm-border last:border-0 text-sm">
                <div class="min-w-0">
                    <span class="font-semibold truncate block">{{ $d->owner_name }}</span>
                    <span class="text-crm-t3 text-[10px]">{{ $d->resort_name }} · {{ $d->timestamp?->format('n/j/Y') ?? '--' }}</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="font-mono font-bold text-emerald-500">${{ number_format($d->fee) }}</span>
                    @if($d->charged_back === 'yes')
                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-500">CB</span>
                    @elseif($d->charged === 'yes')
                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600">Charged</span>
                    @else
                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-600">Pending</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-crm-t3 py-4 text-center">No deals found.</p>
        @endforelse
    </div>
    @endif
</div>
