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
            <svg viewBox="0 0 {{ $svgW }} 110" class="w-full h-auto" preserveAspectRatio="xMidYMid meet" style="max-height: 120px;">
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
                $donutR      = 9;
                $donutCx     = 30;
                $donutCy     = 30;
                $donutCirc   = 2 * M_PI * $donutR;
                $donutSegs   = [
                    ['count' => $dCharged->count(),   'color' => '#10b981', 'label' => 'Charged'],
                    ['count' => $dPending->count(),   'color' => '#f59e0b', 'label' => 'Pending'],
                    ['count' => $dCB->count(),        'color' => '#ef4444', 'label' => 'CB'],
                    ['count' => $dCancelled->count(), 'color' => '#9ca3af', 'label' => 'Cancelled'],
                ];
                $donutCum = 0;
            @endphp
            <div class="flex items-center gap-3">
                <svg viewBox="0 0 60 60" class="flex-shrink-0" style="width: 25px; height: 25px;">
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
                                        stroke-width="4.5"
                                        stroke-dasharray="{{ round($dash, 2) }} {{ round($gap, 2) }}"
                                        stroke-dashoffset="{{ round(-$offset, 2) }}"
                                        transform="rotate(-90 {{ $donutCx }} {{ $donutCy }})"/>
                            @endif
                        @endforeach
                    @else
                        <circle cx="{{ $donutCx }}" cy="{{ $donutCy }}" r="{{ $donutR }}"
                                fill="none" stroke="#e5e7eb" stroke-width="4.5"/>
                    @endif
                    <text x="{{ $donutCx }}" y="{{ $donutCy - 2 }}" text-anchor="middle" font-size="5" font-weight="bold" fill="#111">{{ $dTotal }}</text>
                    <text x="{{ $donutCx }}" y="{{ $donutCy + 6 }}" text-anchor="middle" font-size="2.5" fill="#9ca3af">Deals</text>
                </svg>
                <div class="space-y-1.5 flex-1 min-w-0">
                    @foreach($donutSegs as $seg)
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-sm flex-shrink-0" style="background:{{ $seg['color'] }}"></span>
                            <span class="text-xs text-crm-t2 flex-1">{{ $seg['label'] }}</span>
                            <span class="text-xs font-semibold font-mono">{{ $seg['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         CHARTS ROW 2 — Chargeback Trend + (Admin only)
    ══════════════════════════════════════════════ --}}
    @if(!$isCloser)
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">

        {{-- Chargeback Trend Bar Chart (takes 3/5 width) --}}
        <div class="lg:col-span-3 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-1">Chargeback Trend</div>
            <div class="text-[10px] text-crm-t3 mb-3">Last 6 months · monthly breakdown</div>
            @php
                $maxCBRev = (float) ($monthlyChargebackData->max('rev') ?: 1);
                $cbBarW   = 28;
                $cbGap    = 8;
                $cbPadL   = 6;
                $cbPadR   = 6;
                $cbSvgW   = $cbPadL + count($monthlyChargebackData) * ($cbBarW + $cbGap) - $cbGap + $cbPadR;
                $cbMaxH   = 70;
            @endphp
            <svg viewBox="0 0 {{ $cbSvgW }} 110" class="w-full h-auto" preserveAspectRatio="xMidYMid meet" style="max-height: 120px;">
                {{-- Y-axis guideline --}}
                @for ($gl = 0; $gl <= 4; $gl++)
                    @php $gcy = $cbMaxH - ($gl / 4) * $cbMaxH; @endphp
                    <line x1="{{ $cbPadL - 4 }}" y1="{{ $gcy }}" x2="{{ $cbSvgW - $cbPadR }}" y2="{{ $gcy }}" stroke="#fee2e2" stroke-width="0.5"/>
                    @if($gl > 0)
                        <text x="{{ $cbPadL - 6 }}" y="{{ $gcy + 2 }}" text-anchor="end" font-size="5" fill="#9ca3af">${{ number_format(($gl / 4) * $maxCBRev / 1000, 0) }}k</text>
                    @endif
                @endfor

                @foreach($monthlyChargebackData as $i => $cb)
                    @php
                        $cbBarH = max(2, ($cb['rev'] / $maxCBRev) * $cbMaxH);
                        $cbX    = $cbPadL + $i * ($cbBarW + $cbGap);
                        $cbY    = $cbMaxH - $cbBarH;
                        $cbIsCurrentMonth = $i === count($monthlyChargebackData) - 1;
                    @endphp
                    {{-- Bar --}}
                    <rect x="{{ $cbX }}" y="{{ $cbY }}" width="{{ $cbBarW }}" height="{{ $cbBarH }}"
                          rx="2"
                          fill="{{ $cbIsCurrentMonth ? '#ef4444' : '#fca5a5' }}"/>
                    {{-- Value label --}}
                    @if($cb['rev'] > 0)
                        <text x="{{ $cbX + $cbBarW / 2 }}" y="{{ max(8, $cbY - 2) }}"
                              text-anchor="middle" font-size="5.5" fill="{{ $cbIsCurrentMonth ? '#991b1b' : '#7f1d1d' }}" font-weight="{{ $cbIsCurrentMonth ? 'bold' : 'normal' }}">
                            ${{ $cb['rev'] >= 1000 ? number_format($cb['rev'] / 1000, 1) . 'k' : number_format($cb['rev']) }}
                        </text>
                    @endif
                    {{-- Month label --}}
                    <text x="{{ $cbX + $cbBarW / 2 }}" y="{{ $cbMaxH + 11 }}"
                          text-anchor="middle" font-size="7"
                          fill="{{ $cbIsCurrentMonth ? '#991b1b' : '#9ca3af' }}"
                          font-weight="{{ $cbIsCurrentMonth ? 'bold' : 'normal' }}">{{ $cb['label'] }}</text>
                    {{-- Chargeback count --}}
                    @if($cb['count'] > 0)
                        <text x="{{ $cbX + $cbBarW / 2 }}" y="{{ $cbMaxH + 20 }}"
                              text-anchor="middle" font-size="5" fill="#9ca3af">{{ $cb['count'] }}cb</text>
                    @endif
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
