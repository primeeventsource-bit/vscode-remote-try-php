<div class="p-5">
    {{-- ═══ HEADER ═══ --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Sales Intelligence</h2>
            <p class="text-xs text-crm-t3 mt-1">Live AI scoring, follow-up urgency, close probability, and coaching insights</p>
        </div>
        <x-dashboard.filter-bar
            :dateRange="$dateRange"
            :ownerFilter="$ownerFilter"
            :users="$users"
            :showOwnerFilter="$isAdmin"
            :showExport="$isMaster"
        />
    </div>

    {{-- ═══ ROW 1 — KPI SUMMARY CARDS ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ count($summaryCards) }} gap-3 mb-6">
        @foreach($summaryCards as $card)
            <x-dashboard.kpi-summary-card :card="$card" />
        @endforeach
    </div>

    {{-- ═══ ROW 2 — AI PRIORITY ALERTS ═══ --}}
    @if(!empty($priorityAlerts['items']))
        <div class="mb-6">
            <x-dashboard.card-shell title="{{ $priorityAlerts['title'] }}" subtitle="{{ $priorityAlerts['subtitle'] }}" :noPadding="true">
                <div class="divide-y divide-crm-border max-h-72 overflow-y-auto">
                    @foreach($priorityAlerts['items'] as $alert)
                        <div class="flex items-start gap-3 px-4 py-3 hover:bg-crm-hover transition {{ $alert['severity'] === 'high' ? 'bg-red-50/30' : '' }}">
                            <x-dashboard.status-badge :variant="$alert['severity']" :pulse="$alert['severity'] === 'high'">{{ ucfirst($alert['severity']) }}</x-dashboard.status-badge>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-crm-t1">{{ $alert['title'] }}</div>
                                <p class="text-[10px] text-crm-t3 mt-0.5">{{ $alert['message'] }}</p>
                                <div class="text-[9px] text-crm-t3 mt-0.5">{{ $alert['entity_name'] }}{{ $alert['owner'] ? ' — ' . $alert['owner']['name'] : '' }}</div>
                            </div>
                            <a href="{{ $alert['action']['target_url'] }}" class="px-2.5 py-1 text-[9px] font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition flex-shrink-0">
                                {{ $alert['action']['label'] }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </x-dashboard.card-shell>
        </div>
    @endif

    {{-- ═══ ROW 3 — CHARTS ═══ --}}
    @if(!$isFronter && $dealProbability && $pipelineRisk)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <div class="lg:col-span-2">
                @php $dp = $dealProbability; @endphp
                <x-dashboard.deal-probability-chart
                    :bins="collect($dp['series'])->pluck('count', 'label')->toArray()"
                    :revenue="collect($dp['series'])->pluck('weighted_revenue', 'label')->toArray()"
                />
            </div>
            <div>
                <x-dashboard.pipeline-risk-chart
                    :risks="collect($pipelineRisk['series'] ?? [])->pluck('count', 'label')->toArray()"
                />
            </div>
        </div>
    @endif

    {{-- ═══ ROW 4 — AT-RISK DEALS + HOTTEST LEADS ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        @if(!$isFronter && $atRiskDeals)
            <x-dashboard.at-risk-deals-table :deals="collect($atRiskDeals['rows'])" />
        @endif
        @if($hottestLeads)
            <x-dashboard.hottest-leads-table :leads="collect($hottestLeads['rows'])" />
        @endif
        @if($isFronter && $followupQueue)
            <x-dashboard.followup-queue-table :items="collect($followupQueue['rows'])" />
        @endif
    </div>

    {{-- ═══ ROW 5 — FOLLOW-UP QUEUE (non-fronter) ═══ --}}
    @if(!$isFronter && $followupQueue)
        <div class="mb-6">
            <x-dashboard.followup-queue-table :items="collect($followupQueue['rows'])" />
        </div>
    @endif

    {{-- ═══ ROW 6 — COACHING + MISTAKES (Admin) ═══ --}}
    @if($isAdmin && $coachingWatchlist && $topMistakes)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <x-dashboard.rep-coaching-watchlist :users="collect($coachingWatchlist['rows'])" />
            <x-dashboard.top-mistakes-chart :mistakes="collect($topMistakes['items'])->map(fn($m) => (object) $m)" />
        </div>
    @endif

    {{-- ═══ ROW 7 — UPCOMING REVENUE (Admin/Closer) ═══ --}}
    @if($upcomingRevenue && !empty($upcomingRevenue['rows']))
        <div class="mb-6">
            <x-dashboard.card-shell title="{{ $upcomingRevenue['title'] }}" subtitle="{{ $upcomingRevenue['subtitle'] }}" tooltip="{{ $upcomingRevenue['tooltip'] }}" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-crm-border bg-white">
                                <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Deal</th>
                                <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Closer</th>
                                <th class="text-center px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                                <th class="text-right px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Value</th>
                                <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Expected</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($upcomingRevenue['rows'] as $rev)
                                <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                    <td class="px-3 py-2.5 font-semibold">{{ Str::limit($rev['deal_name'], 22) }}</td>
                                    <td class="px-3 py-2.5 text-crm-t3">{{ $rev['owner']['name'] ?? '--' }}</td>
                                    <td class="px-3 py-2.5 text-center">
                                        <x-dashboard.status-badge :variant="$rev['close_probability'] >= 80 ? 'strong' : 'moderate'">
                                            {{ $rev['close_probability'] }}% {{ $rev['close_probability_label'] }}
                                        </x-dashboard.status-badge>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-semibold">{{ $rev['value_display'] }}</td>
                                    <td class="px-3 py-2.5 text-[10px] text-crm-t3">{{ $rev['expected_timeframe'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dashboard.card-shell>
        </div>
    @endif

    {{-- ═══ ROW 8 — AGENT LEADERBOARD + PERFORMANCE ALERTS ═══ --}}
    @if(($agentLeaderboard ?? null) && !empty($agentLeaderboard['rows']))
    <div class="mb-6">
        <div class="bg-crm-card border border-crm-border rounded-lg">
            <div class="flex items-center justify-between p-4 border-b border-crm-border">
                <div>
                    <div class="text-sm font-bold">{{ $agentLeaderboard['title'] }}</div>
                    <div class="text-[10px] text-crm-t3">{{ $agentLeaderboard['subtitle'] }}</div>
                </div>
                <a href="/stats" class="text-xs text-blue-500 hover:underline">Full Stats</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold w-8">#</th>
                            <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Agent</th>
                            <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                            <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Location</th>
                            <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Deals</th>
                            <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Revenue</th>
                            <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                            <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Badge</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($agentLeaderboard['rows'] as $idx => $agent)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-3 py-2 font-bold {{ $idx < 3 ? 'text-amber-500' : 'text-crm-t3' }}">{{ $idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full flex items-center justify-center text-[7px] font-bold text-white" style="background: {{ $agent['color'] ?? '#6b7280' }}">{{ $agent['avatar'] ?? '--' }}</div>
                                        <span class="font-semibold">{{ $agent['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-2 py-2">
                                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full {{ ($agent['role'] ?? '') === 'fronter' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">{{ ucfirst($agent['role'] ?? '--') }}</span>
                                </td>
                                <td class="text-center px-2 py-2">
                                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full {{ ($agent['location'] ?? 'US') === 'Panama' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">{{ $agent['location'] ?? 'US' }}</span>
                                </td>
                                <td class="text-center px-2 py-2 font-mono font-bold text-emerald-600">{{ $agent['deals_closed'] ?? 0 }}</td>
                                <td class="text-center px-2 py-2 font-mono font-bold text-green-600">${{ number_format($agent['revenue'] ?? 0, 0) }}</td>
                                <td class="text-center px-2 py-2">
                                    @php $cr = $agent['close_rate'] ?? 0; @endphp
                                    <span class="font-bold {{ $cr >= 50 ? 'text-emerald-600' : ($cr >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($cr, 1) }}%</span>
                                </td>
                                <td class="text-center px-2 py-2">
                                    @if($agent['badge'] ?? null)
                                        @php $bc = match($agent['badge']) { 'High Performer' => 'bg-emerald-100 text-emerald-700', 'Top Revenue' => 'bg-green-100 text-green-700', 'Fast Responder' => 'bg-blue-100 text-blue-700', 'Needs Improvement' => 'bg-red-100 text-red-700', default => 'bg-gray-100 text-gray-700' }; @endphp
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full {{ $bc }}">{{ $agent['badge'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Agent Performance Alerts --}}
    @if($isAdmin && ($agentPerfAlerts ?? null) && !empty($agentPerfAlerts['items']))
    <div class="mb-6">
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-bold mb-2">{{ $agentPerfAlerts['title'] }}</div>
            <div class="text-[10px] text-crm-t3 mb-3">{{ $agentPerfAlerts['subtitle'] }}</div>
            <div class="space-y-2">
                @foreach($agentPerfAlerts['items'] as $alert)
                    <div class="flex items-center gap-3 p-2 rounded-lg {{ ($alert['severity'] ?? '') === 'positive' ? 'bg-emerald-50' : 'bg-amber-50' }}">
                        <span class="w-2 h-2 rounded-full {{ ($alert['severity'] ?? '') === 'positive' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        <span class="text-xs">{{ $alert['message'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ ROW 9 — FEEDS ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @if($recentScoreChanges)
            <x-dashboard.recent-score-changes-feed :items="collect($recentScoreChanges['items'])" />
        @endif
        @if($aiRecommendations)
            <x-dashboard.ai-recommendations-feed :items="collect($aiRecommendations['items'])->map(fn($r) => (object) $r)" />
        @endif
    </div>

    {{-- ═══ DRILLDOWN DRAWER ═══ --}}
    <x-dashboard.ai-insight-drilldown-drawer />
</div>
