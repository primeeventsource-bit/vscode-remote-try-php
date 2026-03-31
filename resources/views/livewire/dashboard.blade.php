<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Dashboard</h2>
        <p class="text-xs text-crm-t3 mt-1">PRIME CRM {{ $isCloser ? '· My Performance' : ($isMaster ? '· Master View' : '· Admin View') }}</p>
    </div>

    {{-- ══════════════════════════════════════════════
         KPI CARDS
    ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @if($isCloser)
            {{-- Closer personal cards --}}
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Deals This Week</div>
                <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $myWeekDeals->count() }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $myWeekCharged->count() }} charged</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Revenue (Week)</div>
                <div class="text-2xl font-extrabold text-emerald-500 mt-1">${{ number_format($myWeekRev) }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">All-time: ${{ number_format($myRevTotal) }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Charged Deals</div>
                <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $myCharged->count() }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $myPending->count() }} pending</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">My Chargebacks</div>
                <div class="text-2xl font-extrabold text-red-500 mt-1">{{ $myChargebacks->count() }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">${{ number_format($myChargebacks->sum('fee')) }}</div>
            </div>
        @else
            {{-- Admin / Master cards --}}
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
         CHARTS ROW 1 — Monthly Revenue + Deal Status Donut
    ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        {{-- Monthly Revenue Bar Chart (takes 2/3 width) --}}
        <div class="md:col-span-2 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">{{ $isCloser ? 'My Monthly Revenue' : 'Monthly Charged Revenue' }}</div>
            <div class="text-[10px] text-crm-t3 mb-4">Last 6 months · charged deals only</div>
            @php
                $maxRev = (float) ($monthlyData->max('rev') ?: 1);
                $barW   = 34;
                $gap    = 10;
                $padL   = 8;
                $padR   = 8;
                $svgW   = $padL + count($monthlyData) * ($barW + $gap) - $gap + $padR;
                $maxH   = 90;
            @endphp
            <svg viewBox="0 0 {{ $svgW }} 130" class="w-full" preserveAspectRatio="xMidYMid meet">
                {{-- Y-axis guideline --}}
                @for ($gl = 0; $gl <= 4; $gl++)
                    @php $gy = $maxH - ($gl / 4) * $maxH; @endphp
                    <line x1="{{ $padL - 4 }}" y1="{{ $gy }}" x2="{{ $svgW - $padR }}" y2="{{ $gy }}" stroke="#e5e7eb" stroke-width="0.5"/>
                    @if($gl > 0)
                        <text x="{{ $padL - 6 }}" y="{{ $gy + 2 }}" text-anchor="end" font-size="6" fill="#9ca3af">${{ number_format(($gl / 4) * $maxRev / 1000, 0) }}k</text>
                    @endif
                @endfor

                @foreach($monthlyData as $i => $m)
                    @php
                        $barH = max(2, ($m['rev'] / $maxRev) * $maxH);
                        $x    = $padL + $i * ($barW + $gap);
                        $y    = $maxH - $barH;
                        $isCurrentMonth = $i === count($monthlyData) - 1;
                    @endphp
                    {{-- Bar --}}
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barW }}" height="{{ $barH }}"
                          rx="3"
                          fill="{{ $isCurrentMonth ? '#3b82f6' : '#93c5fd' }}"/>
                    {{-- Value label (only if bar tall enough) --}}
                    @if($m['rev'] > 0)
                        <text x="{{ $x + $barW / 2 }}" y="{{ max(10, $y - 3) }}"
                              text-anchor="middle" font-size="6.5" fill="{{ $isCurrentMonth ? '#1d4ed8' : '#6b7280' }}" font-weight="{{ $isCurrentMonth ? 'bold' : 'normal' }}">
                            ${{ $m['rev'] >= 1000 ? number_format($m['rev'] / 1000, 1) . 'k' : number_format($m['rev']) }}
                        </text>
                    @endif
                    {{-- Month label --}}
                    <text x="{{ $x + $barW / 2 }}" y="{{ $maxH + 14 }}"
                          text-anchor="middle" font-size="8"
                          fill="{{ $isCurrentMonth ? '#1d4ed8' : '#9ca3af' }}"
                          font-weight="{{ $isCurrentMonth ? 'bold' : 'normal' }}">{{ $m['label'] }}</text>
                    {{-- Deal count --}}
                    @if($m['count'] > 0)
                        <text x="{{ $x + $barW / 2 }}" y="{{ $maxH + 24 }}"
                              text-anchor="middle" font-size="6.5" fill="#9ca3af">{{ $m['count'] }}d</text>
                    @endif
                @endforeach
            </svg>
        </div>

        {{-- Deal Status Donut Chart (takes 1/3) --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">{{ $isCloser ? 'My Deal Status' : 'Deal Status' }}</div>
            <div class="text-[10px] text-crm-t3 mb-3">All time breakdown</div>
            @php
                $dSource     = $isCloser ? $myDeals : $deals;
                $dCharged    = $isCloser ? $myCharged    : $charged;
                $dPending    = $isCloser ? $myPending    : $pending;
                $dCB         = $isCloser ? $myChargebacks: $chargebacks;
                $dCancelled  = $isCloser ? $dSource->where('status','cancelled') : $cancelled;
                $dTotal      = $dCharged->count() + $dPending->count() + $dCB->count() + $dCancelled->count();
                $donutR      = 45;
                $donutCx     = 60;
                $donutCy     = 60;
                $donutCirc   = 2 * M_PI * $donutR;
                $donutSegs   = [
                    ['count' => $dCharged->count(),   'color' => '#10b981', 'label' => 'Charged'],
                    ['count' => $dPending->count(),   'color' => '#f59e0b', 'label' => 'Pending'],
                    ['count' => $dCB->count(),        'color' => '#ef4444', 'label' => 'CB'],
                    ['count' => $dCancelled->count(), 'color' => '#9ca3af', 'label' => 'Cancelled'],
                ];
                $donutCum = 0;
            @endphp
            <div class="flex items-center gap-4">
                <svg viewBox="0 0 120 120" class="w-28 h-28 flex-shrink-0">
                    @if($dTotal > 0)
                        @foreach($donutSegs as $seg)
                            @if($seg['count'] > 0)
                                @php
                                    $dash   = ($seg['count'] / $dTotal) * $donutCirc;
                                    $gap    = $donutCirc - $dash;
                                    $offset = $donutCum;
                                    $donutCum += $dash;
                                @endphp
                                <circle cx="{{ $donutCx }}" cy="{{ $donutCy }}" r="{{ $donutR }}"
                                        fill="none"
                                        stroke="{{ $seg['color'] }}"
                                        stroke-width="22"
                                        stroke-dasharray="{{ round($dash, 2) }} {{ round($gap, 2) }}"
                                        stroke-dashoffset="{{ round(-$offset, 2) }}"
                                        transform="rotate(-90 {{ $donutCx }} {{ $donutCy }})"/>
                            @endif
                        @endforeach
                    @else
                        <circle cx="{{ $donutCx }}" cy="{{ $donutCy }}" r="{{ $donutR }}"
                                fill="none" stroke="#e5e7eb" stroke-width="22"/>
                    @endif
                    <text x="{{ $donutCx }}" y="{{ $donutCy - 5 }}" text-anchor="middle" font-size="16" font-weight="bold" fill="#111">{{ $dTotal }}</text>
                    <text x="{{ $donutCx }}" y="{{ $donutCy + 10 }}" text-anchor="middle" font-size="8" fill="#9ca3af">Deals</text>
                </svg>
                <div class="space-y-2 flex-1 min-w-0">
                    @foreach($donutSegs as $seg)
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background:{{ $seg['color'] }}"></span>
                            <span class="text-xs text-crm-t2 flex-1">{{ $seg['label'] }}</span>
                            <span class="text-xs font-semibold font-mono">{{ $seg['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         CHARTS ROW 2
    ══════════════════════════════════════════════ --}}
    @if(!$isCloser)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

        {{-- Top Closers — horizontal progress bars --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">Top Closers</div>
            <div class="text-[10px] text-crm-t3 mb-4">Ranked by charged revenue (all-time)</div>
            @php $maxCloserRev = (float)($closers->max('rev') ?: 1); @endphp
            @forelse($closers->take(8) as $idx => $c)
                @php $pct = $c['rev'] > 0 ? min(100, ($c['rev'] / $maxCloserRev) * 100) : 0; @endphp
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2">
                            <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white"
                                 style="background:{{ $c['user']->color ?? '#6b7280' }}">{{ $c['user']->avatar ?? '?' }}</div>
                            <span class="text-xs font-medium">{{ $c['user']->name }}</span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-xs font-bold font-mono text-emerald-600">${{ number_format($c['rev']) }}</span>
                            <span class="text-[10px] text-crm-t3">· {{ $c['count'] }}d</span>
                        </div>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-1.5 rounded-full transition-all"
                             style="width:{{ number_format($pct, 1) }}%;background:{{ $c['user']->color ?? '#3b82f6' }}"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-crm-t3 py-4 text-center">No closer data.</p>
            @endforelse
        </div>

        {{-- Revenue Split + Recent Deals --}}
        <div class="space-y-4">
            {{-- Revenue split card --}}
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-semibold mb-3">Revenue Split</div>
                @php $revTotal = $totalRev + $cbRev + $pendRev ?: 1; @endphp
                @foreach([
                    ['Charged',    $totalRev, '#10b981'],
                    ['Pending',    $pendRev,  '#f59e0b'],
                    ['Chargebacks',$cbRev,    '#ef4444'],
                ] as [$rl, $rv, $rc])
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

            {{-- Recent Deals --}}
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
         CLOSER-ONLY: My Recent Deals
    ══════════════════════════════════════════════ --}}
    @if($isCloser)
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-6">
        <div class="text-sm font-semibold mb-3">My Recent Deals</div>
        @forelse($myDeals->sortByDesc('id')->take(8) as $d)
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
