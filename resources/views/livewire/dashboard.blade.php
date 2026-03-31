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
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">

        {{-- Monthly Revenue Bar Chart (takes 3/5 width) --}}
        <div class="lg:col-span-3 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">{{ $isCloser ? 'My Monthly Revenue' : 'Monthly Charged Revenue' }}</div>
            <div class="text-[10px] text-crm-t3 mb-3">Last 6 months · charged deals only</div>
            @php
                $maxRev = (float) ($monthlyData->max('rev') ?: 1);
                $barW   = 28;
                $gap    = 8;
                $padL   = 6;
                $padR   = 6;
                $svgW   = $padL + count($monthlyData) * ($barW + $gap) - $gap + $padR;
                $maxH   = 70;
            @endphp
            <svg viewBox="0 0 {{ $svgW }} 110" class="w-full h-auto" preserveAspectRatio="xMidYMid meet" style="max-height: 180px;">
                {{-- Y-axis guideline --}}
                @for ($gl = 0; $gl <= 4; $gl++)
                    @php $gy = $maxH - ($gl / 4) * $maxH; @endphp
                    <line x1="{{ $padL - 4 }}" y1="{{ $gy }}" x2="{{ $svgW - $padR }}" y2="{{ $gy }}" stroke="#e5e7eb" stroke-width="0.5"/>
                    @if($gl > 0)
                        <text x="{{ $padL - 6 }}" y="{{ $gy + 2 }}" text-anchor="end" font-size="5" fill="#9ca3af">${{ number_format(($gl / 4) * $maxRev / 1000, 0) }}k</text>
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
                          rx="2"
                          fill="{{ $isCurrentMonth ? '#3b82f6' : '#93c5fd' }}"/>
                    {{-- Value label (only if bar tall enough) --}}
                    @if($m['rev'] > 0)
                        <text x="{{ $x + $barW / 2 }}" y="{{ max(8, $y - 2) }}"
                              text-anchor="middle" font-size="5.5" fill="{{ $isCurrentMonth ? '#1d4ed8' : '#6b7280' }}" font-weight="{{ $isCurrentMonth ? 'bold' : 'normal' }}">
                            ${{ $m['rev'] >= 1000 ? number_format($m['rev'] / 1000, 1) . 'k' : number_format($m['rev']) }}
                        </text>
                    @endif
                    {{-- Month label --}}
                    <text x="{{ $x + $barW / 2 }}" y="{{ $maxH + 11 }}"
                          text-anchor="middle" font-size="7"
                          fill="{{ $isCurrentMonth ? '#1d4ed8' : '#9ca3af' }}"
                          font-weight="{{ $isCurrentMonth ? 'bold' : 'normal' }}">{{ $m['label'] }}</text>
                    {{-- Deal count --}}
                    @if($m['count'] > 0)
                        <text x="{{ $x + $barW / 2 }}" y="{{ $maxH + 20 }}"
                              text-anchor="middle" font-size="5" fill="#9ca3af">{{ $m['count'] }}d</text>
                    @endif
                @endforeach
            </svg>
        </div>

        {{-- Deal Status Donut Chart (takes 2/5) --}}
        <div class="lg:col-span-2 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">{{ $isCloser ? 'My Deal Status' : 'Deal Status' }}</div>
            <div class="text-[10px] text-crm-t3 mb-3">All time breakdown</div>
            @php
                $dSource     = $isCloser ? $myDeals : $deals;
                $dCharged    = $isCloser ? $myCharged    : $charged;
                $dPending    = $isCloser ? $myPending    : $pending;
                $dCB         = $isCloser ? $myChargebacks: $chargebacks;
                $dCancelled  = $isCloser ? $dSource->where('status','cancelled') : $cancelled;
                $dTotal      = $dCharged->count() + $dPending->count() + $dCB->count() + $dCancelled->count();
                $dealStatusSegs   = [
                    ['count' => $dCharged->count(),   'color' => '#10b981', 'label' => 'Charged'],
                    ['count' => $dPending->count(),   'color' => '#f59e0b', 'label' => 'Pending'],
                    ['count' => $dCB->count(),        'color' => '#ef4444', 'label' => 'CB'],
                    ['count' => $dCancelled->count(), 'color' => '#9ca3af', 'label' => 'Cancelled'],
                ];
            @endphp
            <div class="space-y-3.5">
                @foreach($dealStatusSegs as $seg)
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
                            <div class="h-3 rounded-full transition-all" 
                                 style="width: {{ $pct }}%; background: {{ $seg['color'] }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         CHARTS ROW 2 — Chargeback Trend + (Admin only)
    ══════════════════════════════════════════════ --}}
    @if(!$isCloser)
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">

        {{-- Chargeback Trend Line Chart (takes 3/5 width) --}}
        <div class="lg:col-span-3 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">Chargeback Trend</div>
            <div class="text-[10px] text-crm-t3 mb-3">Last 6 months · monthly breakdown</div>
            @php
                $maxCBRev = (float) ($monthlyChargebackData->max('rev') ?: 1);
                $cbPadL   = 10;
                $cbPadR   = 8;
                $cbPadT   = 8;
                $cbPadB   = 25;
                $cbPlotW  = 100;
                $cbPlotH  = 70;
                $cbSvgW   = $cbPadL + $cbPlotW + $cbPadR;
                $cbSvgH   = $cbPadT + $cbPlotH + $cbPadB;
                $points   = [];
            @endphp
            <svg viewBox="0 0 {{ $cbSvgW }} {{ $cbSvgH }}" class="w-full h-auto" preserveAspectRatio="xMidYMid meet" style="max-height: 180px;">
                {{-- Grid lines --}}
                @for ($gl = 0; $gl <= 4; $gl++)
                    @php $gcy = $cbPadT + ($gl / 4) * $cbPlotH; @endphp
                    <line x1="{{ $cbPadL }}" y1="{{ $gcy }}" x2="{{ $cbPadL + $cbPlotW }}" y2="{{ $gcy }}" stroke="#fee2e2" stroke-width="0.5"/>
                    <text x="{{ $cbPadL - 4 }}" y="{{ $gcy + 2 }}" text-anchor="end" font-size="5" fill="#9ca3af">${{ number_format((1 - $gl / 4) * $maxCBRev / 1000, 0) }}k</text>
                @endfor

                {{-- Generate line path --}}
                @php
                    $dataCount = count($monthlyChargebackData);
                    $xStep = $dataCount > 1 ? $cbPlotW / ($dataCount - 1) : $cbPlotW;
                    $pathData = '';
                    foreach($monthlyChargebackData as $i => $cb) {
                        $x = $cbPadL + $i * $xStep;
                        $y = $cbPadT + $cbPlotH - (($cb['rev'] / $maxCBRev) * $cbPlotH);
                        $points[] = ['x' => $x, 'y' => $y, 'rev' => $cb['rev'], 'label' => $cb['label']];
                        $pathData .= ($i === 0 ? 'M' : 'L') . ' ' . round($x, 1) . ',' . round($y, 1) . ' ';
                    }
                @endphp
                
                {{-- Line path --}}
                <polyline points="{{ implode(' ', array_map(fn($p) => round($p['x'], 1) . ',' . round($p['y'], 1), $points)) }}"
                          fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                
                {{-- Data points --}}
                @foreach($points as $i => $p)
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.5"
                            fill="{{ $i === count($points) - 1 ? '#991b1b' : '#fecaca' }}"
                            stroke="{{ $i === count($points) - 1 ? '#7f1d1d' : '#dc2626' }}"
                            stroke-width="1.5"/>
                    @if($p['rev'] > 0)
                        <text x="{{ $p['x'] }}" y="{{ $p['y'] - 8 }}"
                              text-anchor="middle" font-size="5" fill="{{ $i === count($points) - 1 ? '#991b1b' : '#7f1d1d' }}" font-weight="bold">
                            ${{ $p['rev'] >= 1000 ? number_format($p['rev'] / 1000, 1) . 'k' : number_format($p['rev']) }}
                        </text>
                    @endif
                @endforeach

                {{-- Month labels --}}
                @foreach($points as $i => $p)
                    <text x="{{ $p['x'] }}" y="{{ $cbSvgH - 4 }}"
                          text-anchor="middle" font-size="7"
                          fill="{{ $i === count($points) - 1 ? '#991b1b' : '#9ca3af' }}"
                          font-weight="{{ $i === count($points) - 1 ? 'bold' : 'normal' }}">{{ $points[$i]['label'] }}</text>
                @endforeach
            </svg>
        </div>

        {{-- Chargeback Stats Card (takes 2/5) --}}
        <div class="lg:col-span-2 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">Chargeback Stats</div>
            <div class="text-[10px] text-crm-t3 mb-4">All time summary</div>
            <div class="space-y-3">
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1">Total Chargebacks</div>
                    <div class="text-2xl font-extrabold text-red-600">{{ $chargebacks->count() }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1">Chargeback Revenue</div>
                    <div class="text-xl font-bold text-red-500">${{ number_format($cbRev) }}</div>
                </div>
                <div class="pt-2 border-t border-crm-border">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1">CB Rate</div>
                    <div class="text-lg font-semibold">
                        {{ $deals->count() > 0 ? number_format(($chargebacks->count() / $deals->count()) * 100, 1) : 0 }}%
                    </div>
                </div>
                <div class="pt-2 border-t border-crm-border">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1">Avg CB Amount</div>
                    <div class="text-lg font-semibold">
                        {{ $chargebacks->count() > 0 ? '$' . number_format($chargebacks->sum('fee') / $chargebacks->count()) : '$0' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         CHARTS ROW 3 — Top Closers + Revenue + Recent
    ══════════════════════════════════════════════ --}}
    @if(!$isCloser)
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">

        {{-- Top Closers — horizontal progress bars (takes 2/5) --}}
        <div class="lg:col-span-2 bg-crm-card border border-crm-border rounded-lg p-4">
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

        {{-- Revenue Split + Recent Deals (takes 3/5) --}}
        <div class="lg:col-span-3 space-y-4">
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
