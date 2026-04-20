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
         WEEK NAVIGATOR BAR
    ══════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-crm-card border border-crm-border rounded-xl px-5 py-3 mb-5">
        <div class="flex items-center gap-3">
            <button wire:click="goToPreviousWeek"
                class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-crm-t3 hover:text-black transition text-lg"
                title="Previous week">‹</button>

            <div>
                <div class="flex items-center gap-2">
                    @if($isCurrentWeek ?? true)
                        <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full px-2 py-0.5 font-semibold">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> LIVE
                        </span>
                    @else
                        <span class="text-[10px] bg-amber-50 text-amber-600 border border-amber-200 rounded-full px-2 py-0.5 font-semibold">
                            ARCHIVED
                        </span>
                    @endif
                    <span class="font-semibold text-sm">
                        @if($isCurrentWeek ?? true)
                            Current Week
                        @else
                            {{ \Carbon\Carbon::parse($weekStats['week_start'])->format('M d') }}
                            – {{ \Carbon\Carbon::parse($weekStats['week_end'])->format('M d, Y') }}
                        @endif
                    </span>
                    <span class="text-crm-t3 text-xs font-mono">{{ $viewingWeek }}</span>
                </div>
                <div class="text-crm-t3 text-xs mt-0.5">
                    {{ $weekStats['total_deals'] ?? 0 }} deals ·
                    ${{ number_format((float)($weekStats['total_revenue'] ?? 0), 0) }} revenue
                </div>
            </div>

            <button wire:click="goToNextWeek"
                @class([
                    'w-8 h-8 flex items-center justify-center rounded-lg transition text-lg',
                    'text-gray-300 cursor-not-allowed' => ($isCurrentWeek ?? true),
                    'hover:bg-gray-100 text-crm-t3 hover:text-black' => !($isCurrentWeek ?? true),
                ])
                @disabled($isCurrentWeek ?? true)
                title="Next week">›</button>
        </div>

        @if($isMaster || $isAdmin)
        <div class="flex items-center gap-2">
            <select id="week-navigator-select" name="week_navigator" autocomplete="off"
                wire:change="jumpToWeek($event.target.value)"
                class="bg-white border border-crm-border text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-blue-400">
                <option value="">Jump to week…</option>
                @foreach(($availableWeeks ?? []) as $wk)
                    <option value="{{ $wk }}" @selected($wk === $viewingWeek)>{{ $wk }}</option>
                @endforeach
            </select>

            @if(!($isCurrentWeek ?? true))
            <button wire:click="goToCurrentWeek"
                class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg transition">
                ↩ Live
            </button>
            @endif

            <button wire:click="forceSnapshot"
                wire:loading.attr="disabled"
                class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition"
                title="Save snapshot for this week">
                <span wire:loading.remove wire:target="forceSnapshot">💾 Snapshot</span>
                <span wire:loading wire:target="forceSnapshot">Saving…</span>
            </button>
        </div>
        @endif
    </div>

    @if(!($isCurrentWeek ?? true))
    <div class="mb-4 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 text-amber-700 text-sm">
        ⚠ Viewing archived stats for <strong>{{ $viewingWeek }}</strong>
        ({{ \Carbon\Carbon::parse($weekStats['week_start'])->format('M d') }}–{{ \Carbon\Carbon::parse($weekStats['week_end'])->format('M d, Y') }})
        — not live data
    </div>
    @endif

    @if(session('weekly_stats_message'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-2 text-sm">
        ✓ {{ session('weekly_stats_message') }}
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         TAB NAV
    ══════════════════════════════════════════════ --}}
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-5 w-fit">
        @foreach(['overview' => 'Overview', 'closers' => 'Closers', 'fronters' => 'Fronters', 'chart' => 'Chart'] as $tab => $label)
        <button wire:click="setTab('{{ $tab }}')"
            @class([
                'px-4 py-1.5 rounded-lg text-sm font-medium transition',
                'bg-white text-black shadow-sm' => $activeTab === $tab,
                'text-crm-t3 hover:text-black' => $activeTab !== $tab,
            ])>
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════
         TAB: OVERVIEW — existing dashboard content
    ══════════════════════════════════════════════ --}}
    @if($activeTab === 'overview')

    {{-- ══════════════════════════════════════════════
         MY PIPELINE STATS (role-scoped, per user)
    ══════════════════════════════════════════════ --}}
    @if($userRole === 'fronter')
        {{-- FRONTER: My Pipeline Stats --}}
        <div class="mb-6" data-training="fronter-stats">
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
         AGENT STATS + ROLE BREAKDOWN (admin/master_admin)
    ══════════════════════════════════════════════ --}}
    @if(($isMaster || $isAdmin) && !empty($agentSummary ?? []))
        <x-dashboard.agent-stats-widget
            :agentSummary="$agentSummary"
            :roleBreakdown="$roleBreakdown ?? []"
            :topAgents="$topAgents ?? []"
        />
    @endif

    {{-- ══════════════════════════════════════════════
         PERFORMANCE LEADERBOARD (all authenticated users)
    ══════════════════════════════════════════════ --}}
    @if(!empty($topAgents ?? []))
        <x-dashboard.leaderboard-widget
            :topAgents="$topAgents ?? []"
            :canSeeStats="$canSeeStats ?? false"
        />
    @endif

    {{-- ══════════════════════════════════════════════
         AI PERFORMANCE INSIGHTS (admin/master_admin only)
    ══════════════════════════════════════════════ --}}
    @if(($canSeeStats ?? false) && !empty($aiInsights ?? []))
        <x-dashboard.ai-performance-insights-widget
            :aiInsights="$aiInsights ?? []"
        />
    @endif

    {{-- ══════════════════════════════════════════════
         TASK SCREEN WIDGET (admin/master_admin — ALL tasks)
    ══════════════════════════════════════════════ --}}
    @if($showTaskScreen ?? false)
        <div class="bg-crm-card border border-crm-border rounded-lg mb-6">
            <div class="flex items-center justify-between p-4 border-b border-crm-border">
                <div>
                    <div class="text-sm font-bold">Automatic Task List</div>
                    <div class="text-[10px] text-crm-t3">All open tasks across all admins</div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 text-[10px]">
                        <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-600 font-bold">{{ $taskWidget['overdue'] ?? 0 }} Overdue</span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 font-bold">{{ $taskWidget['due_today'] ?? 0 }} Due Today</span>
                        <span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-600 font-bold">{{ $taskWidget['urgent'] ?? 0 }} Urgent</span>
                        <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 font-bold">{{ $taskWidget['open'] ?? 0 }} Open</span>
                    </div>
                    <a href="{{ route('tasks') }}" class="px-3 py-1.5 text-[10px] font-semibold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">View All</a>
                </div>
            </div>

            <div class="max-h-[400px] overflow-y-auto">
                @forelse($dashboardTasks as $task)
                    @php
                        $isOverdue = $task->due_date && \Carbon\Carbon::parse($task->due_date)->isPast();
                        $isDueToday = $task->due_date && \Carbon\Carbon::parse($task->due_date)->isToday();
                        $assignee = $users[$task->assigned_to] ?? null;
                        $pBadge = match($task->priority ?? 'medium') {
                            'urgent' => 'bg-red-50 text-red-600',
                            'high' => 'bg-orange-50 text-orange-600',
                            'medium' => 'bg-blue-50 text-blue-600',
                            default => 'bg-gray-100 text-gray-500',
                        };
                        $typeIcon = match($task->type ?? 'general') {
                            'client_contact' => '📞',
                            'follow_up' => '🔄',
                            'verification_action' => '✓',
                            'missing_evidence' => '📎',
                            'chargeback_deadline' => '⚠',
                            'transfer_followup' => '↗',
                            'internal_review' => '📋',
                            default => '📌',
                        };
                    @endphp
                    <a href="{{ route('tasks') }}" class="flex items-center gap-3 px-4 py-2.5 border-b border-crm-border hover:bg-crm-hover transition {{ $isOverdue ? 'bg-red-50/30' : '' }}">
                        <span class="text-sm">{{ $typeIcon }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold truncate">{{ $task->title }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                @if($assignee)
                                    <span class="text-[9px] text-crm-t3">{{ $assignee->name ?? '?' }}</span>
                                @endif
                                @if($task->client_name)
                                    <span class="text-[9px] text-crm-t3">{{ $task->client_name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            @if($task->due_date)
                                <span class="text-[9px] font-mono {{ $isOverdue ? 'text-red-600 font-bold' : ($isDueToday ? 'text-amber-600 font-semibold' : 'text-crm-t3') }}">
                                    {{ \Carbon\Carbon::parse($task->due_date)->format('n/j g:iA') }}
                                </span>
                            @endif
                            <span class="text-[8px] font-bold px-1.5 py-0.5 rounded {{ $pBadge }}">{{ ucfirst($task->priority ?? 'medium') }}</span>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-8 text-center">
                        <p class="text-sm text-crm-t3">No open tasks</p>
                    </div>
                @endforelse
            </div>
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
                <div class="text-[10px] text-crm-t3 mt-1">${{ number_format($cbRev) }}</div>
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
                @foreach($recentDeals->filter() as $d)
                    <div class="flex items-center justify-between py-1.5 border-b border-crm-border last:border-0 text-sm">
                        <div class="min-w-0">
                            <span class="font-semibold truncate block">{{ $d->owner_name ?? 'N/A' }}</span>
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
        @forelse($deals->filter()->sortByDesc('id')->take(8) as $d)
            <div class="flex items-center justify-between py-1.5 border-b border-crm-border last:border-0 text-sm">
                <div class="min-w-0">
                    <span class="font-semibold truncate block">{{ $d->owner_name ?? 'N/A' }}</span>
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

    {{-- End of Overview tab --}}
    @endif

    {{-- ══════════════════════════════════════════════
         TAB: CLOSERS BREAKDOWN
    ══════════════════════════════════════════════ --}}
    @if($activeTab === 'closers')
    <div>
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex gap-2">
                @foreach(['revenue' => 'By Revenue', 'deals' => 'By Deals', 'closed' => 'By Closed'] as $s => $label)
                <button wire:click="setSortCloser('{{ $s }}')"
                    @class([
                        'px-3 py-1.5 text-xs rounded-lg transition font-medium',
                        'bg-blue-600 text-white' => $closerSort === $s,
                        'bg-gray-100 text-crm-t3 hover:bg-gray-200' => $closerSort !== $s,
                    ])>
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <div class="flex gap-2">
                @foreach(['all' => 'All Offices', 'US' => '🇺🇸 US', 'Panama' => '🇵🇦 Panama'] as $o => $label)
                <button wire:click="setOfficeFilter('{{ $o }}')"
                    @class([
                        'px-3 py-1.5 text-xs rounded-lg transition font-medium',
                        'bg-purple-600 text-white' => $officeFilter === $o,
                        'bg-gray-100 text-crm-t3 hover:bg-gray-200' => $officeFilter !== $o,
                    ])>
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>

        @if(count($weekClosers ?? []) === 0)
            <div class="text-center text-crm-t3 py-12">No closer data for this week.</div>
        @else
        <div class="overflow-x-auto rounded-xl border border-crm-border bg-crm-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-crm-t3 text-[11px] uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Closer</th>
                        <th class="px-4 py-3 text-left">Office</th>
                        <th class="px-4 py-3 text-right">Deals</th>
                        <th class="px-4 py-3 text-right">Closed</th>
                        <th class="px-4 py-3 text-right">Cancelled</th>
                        <th class="px-4 py-3 text-right">Pending</th>
                        <th class="px-4 py-3 text-right">Revenue</th>
                        <th class="px-4 py-3 text-right">Commission</th>
                        <th class="px-4 py-3 text-right">Avg Sale</th>
                        <th class="px-4 py-3 text-right">Close %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-crm-border">
                    @foreach($weekClosers as $i => $c)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-mono text-crm-t3">
                            @if($i === 0) 🥇 @elseif($i === 1) 🥈 @elseif($i === 2) 🥉 @else {{ $i + 1 }} @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $c['name'] }}</div>
                            <div class="text-crm-t3 text-xs capitalize">{{ str_replace('_', ' ', $c['role']) }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $c['office'] === 'Panama' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600' }}">
                                {{ $c['office'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $c['deals'] }}</td>
                        <td class="px-4 py-3 text-right text-emerald-600 font-semibold">{{ $c['closed'] }}</td>
                        <td class="px-4 py-3 text-right text-red-500">{{ $c['cancelled'] }}</td>
                        <td class="px-4 py-3 text-right text-amber-600">{{ $c['pending'] }}</td>
                        <td class="px-4 py-3 text-right font-semibold">${{ number_format((float)$c['revenue'], 0) }}</td>
                        <td class="px-4 py-3 text-right text-emerald-700">${{ number_format((float)$c['commission'], 0) }}</td>
                        <td class="px-4 py-3 text-right text-crm-t2">${{ number_format((float)$c['avg_sale'], 0) }}</td>
                        <td class="px-4 py-3 text-right">
                            @php $pct = $c['deals'] > 0 ? round(($c['closed'] / $c['deals']) * 100) : 0; @endphp
                            <span @class([
                                'font-semibold',
                                'text-emerald-600' => $pct >= 60,
                                'text-amber-600' => $pct >= 30 && $pct < 60,
                                'text-red-500' => $pct < 30,
                            ])>{{ $pct }}%</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold border-t border-crm-border">
                        <td colspan="3" class="px-4 py-3 text-crm-t3 text-[11px] uppercase">Totals</td>
                        <td class="px-4 py-3 text-right">{{ collect($weekClosers)->sum('deals') }}</td>
                        <td class="px-4 py-3 text-right text-emerald-600">{{ collect($weekClosers)->sum('closed') }}</td>
                        <td class="px-4 py-3 text-right text-red-500">{{ collect($weekClosers)->sum('cancelled') }}</td>
                        <td class="px-4 py-3 text-right text-amber-600">{{ collect($weekClosers)->sum('pending') }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format((float)collect($weekClosers)->sum('revenue'), 0) }}</td>
                        <td class="px-4 py-3 text-right text-emerald-700">${{ number_format((float)collect($weekClosers)->sum('commission'), 0) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         TAB: FRONTERS BREAKDOWN
    ══════════════════════════════════════════════ --}}
    @if($activeTab === 'fronters')
    <div>
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex gap-2">
                @foreach(['sets' => 'By Sets', 'conversion' => 'By Conversion', 'revenue_assisted' => 'By Revenue'] as $s => $label)
                <button wire:click="setSortFronter('{{ $s }}')"
                    @class([
                        'px-3 py-1.5 text-xs rounded-lg transition font-medium',
                        'bg-blue-600 text-white' => $fronterSort === $s,
                        'bg-gray-100 text-crm-t3 hover:bg-gray-200' => $fronterSort !== $s,
                    ])>
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <div class="flex gap-2">
                @foreach(['all' => 'All Offices', 'US' => '🇺🇸 US', 'Panama' => '🇵🇦 Panama'] as $o => $label)
                <button wire:click="setOfficeFilter('{{ $o }}')"
                    @class([
                        'px-3 py-1.5 text-xs rounded-lg transition font-medium',
                        'bg-purple-600 text-white' => $officeFilter === $o,
                        'bg-gray-100 text-crm-t3 hover:bg-gray-200' => $officeFilter !== $o,
                    ])>
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>

        @if(count($weekFronters ?? []) === 0)
            <div class="text-center text-crm-t3 py-12">No fronter data for this week.</div>
        @else
        <div class="overflow-x-auto rounded-xl border border-crm-border bg-crm-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-crm-t3 text-[11px] uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Fronter</th>
                        <th class="px-4 py-3 text-left">Office</th>
                        <th class="px-4 py-3 text-right">Sets</th>
                        <th class="px-4 py-3 text-right">Closed</th>
                        <th class="px-4 py-3 text-right">Cancelled</th>
                        <th class="px-4 py-3 text-right">Conversion</th>
                        <th class="px-4 py-3 text-right">Revenue Assisted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-crm-border">
                    @foreach($weekFronters as $i => $f)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-mono text-crm-t3">
                            @if($i === 0) 🥇 @elseif($i === 1) 🥈 @elseif($i === 2) 🥉 @else {{ $i + 1 }} @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $f['name'] }}</div>
                            <div class="text-crm-t3 text-xs capitalize">{{ str_replace('_', ' ', $f['role']) }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $f['office'] === 'Panama' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600' }}">
                                {{ $f['office'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $f['sets'] }}</td>
                        <td class="px-4 py-3 text-right text-emerald-600">{{ $f['sets_closed'] }}</td>
                        <td class="px-4 py-3 text-right text-red-500">{{ $f['cancelled'] }}</td>
                        <td class="px-4 py-3 text-right">
                            <span @class([
                                'font-semibold',
                                'text-emerald-600' => $f['conversion_rate'] >= 50,
                                'text-amber-600' => $f['conversion_rate'] >= 25 && $f['conversion_rate'] < 50,
                                'text-red-500' => $f['conversion_rate'] < 25,
                            ])>{{ $f['conversion_rate'] }}%</span>
                        </td>
                        <td class="px-4 py-3 text-right text-crm-t2">${{ number_format((float)$f['revenue_assisted'], 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold border-t border-crm-border">
                        <td colspan="3" class="px-4 py-3 text-crm-t3 text-[11px] uppercase">Totals</td>
                        <td class="px-4 py-3 text-right">{{ collect($weekFronters)->sum('sets') }}</td>
                        <td class="px-4 py-3 text-right text-emerald-600">{{ collect($weekFronters)->sum('sets_closed') }}</td>
                        <td class="px-4 py-3 text-right text-red-500">{{ collect($weekFronters)->sum('cancelled') }}</td>
                        <td class="px-4 py-3 text-right text-crm-t3">
                            @php
                                $totalSets   = collect($weekFronters)->sum('sets');
                                $totalClosed = collect($weekFronters)->sum('sets_closed');
                                $avgConv = $totalSets > 0 ? round(($totalClosed / $totalSets) * 100, 1) : 0;
                            @endphp
                            {{ $avgConv }}% avg
                        </td>
                        <td class="px-4 py-3 text-right">${{ number_format((float)collect($weekFronters)->sum('revenue_assisted'), 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         TAB: COMPARISON CHART (Chart.js)
    ══════════════════════════════════════════════ --}}
    @if($activeTab === 'chart')
    @php $totals = $chartData['totals']; @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-crm-card rounded-xl p-4 border border-crm-border">
            <div class="text-crm-t3 text-xs mb-1">This Week Deals</div>
            <div class="text-2xl font-bold">{{ $totals['this_deals'] }}</div>
        </div>
        <div class="bg-crm-card rounded-xl p-4 border border-crm-border">
            <div class="text-crm-t3 text-xs mb-1">Last Week Deals</div>
            <div class="text-2xl font-bold text-crm-t3">{{ $totals['prev_deals'] }}</div>
        </div>
        <div class="bg-crm-card rounded-xl p-4 border border-crm-border">
            <div class="text-crm-t3 text-xs mb-1">This Week Revenue</div>
            <div class="text-2xl font-bold">${{ number_format((float)$totals['this_revenue'], 0) }}</div>
        </div>
        <div class="bg-crm-card rounded-xl p-4 border border-crm-border">
            <div class="text-crm-t3 text-xs mb-1">Last Week Revenue</div>
            <div class="text-2xl font-bold text-crm-t3">${{ number_format((float)$totals['prev_revenue'], 0) }}</div>
        </div>
    </div>

    @if($totals['deals_pct'] !== null || $totals['revenue_pct'] !== null)
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-crm-card rounded-xl p-4 border border-crm-border">
            <div class="text-crm-t3 text-xs mb-1">Deals — vs Last Week</div>
            <div @class([
                'text-xl font-bold',
                'text-emerald-600' => ($totals['deals_pct'] ?? 0) >= 0,
                'text-red-500' => ($totals['deals_pct'] ?? 0) < 0,
            ])>
                {{ ($totals['deals_pct'] ?? 0) >= 0 ? '▲' : '▼' }} {{ abs($totals['deals_pct'] ?? 0) }}%
            </div>
        </div>
        <div class="bg-crm-card rounded-xl p-4 border border-crm-border">
            <div class="text-crm-t3 text-xs mb-1">Revenue — vs Last Week</div>
            <div @class([
                'text-xl font-bold',
                'text-emerald-600' => ($totals['revenue_pct'] ?? 0) >= 0,
                'text-red-500' => ($totals['revenue_pct'] ?? 0) < 0,
            ])>
                {{ ($totals['revenue_pct'] ?? 0) >= 0 ? '▲' : '▼' }} {{ abs($totals['revenue_pct'] ?? 0) }}%
            </div>
        </div>
    </div>
    @endif

    <div class="bg-crm-card rounded-xl p-5 border border-crm-border">
        <h3 class="text-sm font-semibold mb-4">Daily Comparison — This Week vs Last Week</h3>
        <div style="height: 360px; position: relative;">
            <canvas id="weeklyComparisonChart"
                    wire:ignore
                    x-data
                    x-init="
                        (async () => {
                            if (typeof window.Chart === 'undefined') {
                                await new Promise((resolve, reject) => {
                                    const s = document.createElement('script');
                                    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                                    s.onload = resolve; s.onerror = reject;
                                    document.head.appendChild(s);
                                });
                            }
                            const ctx = $el.getContext('2d');
                            if (window._weeklyChart) window._weeklyChart.destroy();
                            window._weeklyChart = new window.Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: @js($chartData['labels']),
                                    datasets: [
                                        {
                                            label: 'This Week — Deals',
                                            data: @js($chartData['this_week']['deals']),
                                            backgroundColor: '#3b82f6',
                                            borderRadius: 6,
                                        },
                                        {
                                            label: 'Last Week — Deals',
                                            data: @js($chartData['last_week']['deals']),
                                            backgroundColor: '#94a3b8',
                                            borderRadius: 6,
                                        },
                                    ],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'top', labels: { font: { size: 12 } } },
                                        tooltip: { mode: 'index', intersect: false },
                                    },
                                    scales: {
                                        y: { beginAtZero: true, ticks: { precision: 0 } },
                                    },
                                },
                            });
                        })();
                    "></canvas>
        </div>
    </div>
    @endif

</div>
