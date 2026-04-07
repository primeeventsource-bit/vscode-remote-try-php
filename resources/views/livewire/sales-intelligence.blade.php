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

    {{-- ═══ ROW 8 — FEEDS ═══ --}}
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
