<div class="p-5">
    {{-- Page Title --}}
    <div class="mb-5">
        <h2 class="text-xl font-bold">Statistics</h2>
        <p class="text-xs text-crm-t3 mt-1">Pipeline performance — fronters, closers, and verification across US & Panama</p>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         FILTER BAR
         ════════════════════════════════════════════════════════════ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-3 mb-5">
        <div class="flex flex-wrap items-center gap-4">
            {{-- View By --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">View By</span>
                <select id="fld-stats-range" wire:model.live="statsRange" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="live">Live</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>

            {{-- Location --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Location</span>
                <select id="fld-stats-location" wire:model.live="selectedLocation" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Locations</option>
                    <option value="US">US</option>
                    <option value="Panama">Panama</option>
                </select>
            </div>

            {{-- Fronter Agent --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Fronter</span>
                <select id="fld-stats-fronter" wire:model.live="selectedFronterId" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Fronters</option>
                    @foreach($fronterUsers as $fu)
                        <option value="{{ $fu->id }}">{{ $fu->name }} ({{ str_contains($fu->role, 'panama') ? 'Panama' : 'US' }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Closer Agent --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Closer</span>
                <select id="fld-stats-closer" wire:model.live="selectedCloserId" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Closers</option>
                    @foreach($closerUsers as $cu)
                        <option value="{{ $cu->id }}">{{ $cu->name }} ({{ str_contains($cu->role, 'panama') ? 'Panama' : 'US' }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Admin Agent --}}
            @if($canSeeAll)
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Admin</span>
                <select id="fld-stats-admin" wire:model.live="selectedAdminId" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Admins</option>
                    @foreach($adminUsers as $au)
                        <option value="{{ $au->id }}">{{ $au->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SUMMARY STAT CARDS — Fronter + Closer
         ════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {{-- Fronter Cards --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Total Leads</div>
            <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ number_format($agentSummary['fronter']['total_leads'] ?? 0) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">Assigned to fronters</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-cyan-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Qualified Leads</div>
            <div class="text-2xl font-extrabold text-cyan-500 mt-1">{{ number_format($agentSummary['fronter']['qualified_leads'] ?? 0) }}</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-indigo-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Transfer Rate</div>
            <div class="text-2xl font-extrabold text-indigo-500 mt-1">{{ number_format($agentSummary['fronter']['transfer_rate'] ?? 0, 1) }}%</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($agentSummary['fronter']['transferred'] ?? 0) }} transferred</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-violet-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Avg Contact Time</div>
            @php
                $avgSec = $agentSummary['fronter']['avg_contact_time'] ?? 0;
                $avgFormatted = $avgSec < 60 ? $avgSec . 's' : ($avgSec < 3600 ? round($avgSec / 60) . 'm' : floor($avgSec / 3600) . 'h ' . floor(($avgSec % 3600) / 60) . 'm');
            @endphp
            <div class="text-2xl font-extrabold text-violet-500 mt-1">{{ $avgFormatted }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {{-- Closer Cards --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deals Closed</div>
            <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ number_format($agentSummary['closer']['deals_closed'] ?? 0) }}</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-green-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Revenue</div>
            <div class="text-2xl font-extrabold text-green-500 mt-1">${{ number_format($agentSummary['closer']['revenue'] ?? 0, 0) }}</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Close Rate</div>
            <div class="text-2xl font-extrabold text-amber-500 mt-1">{{ number_format($agentSummary['closer']['close_rate'] ?? 0, 1) }}%</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($agentSummary['closer']['deals_received'] ?? 0) }} received</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-orange-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Avg Deal Value</div>
            <div class="text-2xl font-extrabold text-orange-500 mt-1">${{ number_format($agentSummary['closer']['avg_deal_value'] ?? 0, 0) }}</div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         PIPELINE SUMMARY (original)
         ════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Fronter Transfers</div>
            <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ number_format($summary['total_transfers'] ?? 0) }}</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deals Closed</div>
            <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ number_format($summary['total_deals_closed'] ?? 0) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($summary['transfer_to_deal_pct'] ?? 0, 1) }}% of transfers</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Sent to Verification</div>
            <div class="text-2xl font-extrabold text-amber-500 mt-1">{{ number_format($summary['total_sent_to_verification'] ?? 0) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($summary['deal_to_verification_pct'] ?? 0, 1) }}% of deals</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged / Green</div>
            <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ number_format($summary['total_charged_green'] ?? 0) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($summary['verification_charge_pct'] ?? 0, 1) }}% charge rate</div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         ROLE BREAKDOWN TABLE
         ════════════════════════════════════════════════════════════ --}}
    @if($canSeeAll && !empty($roleBreakdown))
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Role & Location Breakdown</div>
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Location</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Agents</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Leads</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Qualified</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Transfers</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals Closed</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Revenue</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roleBreakdown as $row)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-4 py-2.5 font-semibold">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full {{ $row['role'] === 'fronter' ? 'bg-blue-500' : 'bg-emerald-500' }}"></span>
                                    {{ ucfirst($row['role']) }}
                                </span>
                            </td>
                            <td class="text-center px-3 py-2.5">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $row['location'] === 'Panama' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $row['location'] }}
                                </span>
                            </td>
                            <td class="text-center px-3 py-2.5 font-mono">{{ $row['agent_count'] }}</td>
                            <td class="text-center px-3 py-2.5 font-mono font-bold">{{ number_format($row['leads']) }}</td>
                            <td class="text-center px-3 py-2.5 font-mono">{{ number_format($row['qualified']) }}</td>
                            <td class="text-center px-3 py-2.5 font-mono text-blue-600">{{ number_format($row['transfers']) }}</td>
                            <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ number_format($row['deals_closed']) }}</td>
                            <td class="text-center px-3 py-2.5 font-mono font-bold text-green-600">${{ number_format($row['revenue'], 0) }}</td>
                            <td class="text-center px-3 py-2.5">
                                @php $cr = $row['close_rate'] ?? 0; @endphp
                                <span class="font-bold {{ $cr >= 50 ? 'text-emerald-600' : ($cr >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($cr, 1) }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════
         PERFORMANCE LEADERBOARD
         ════════════════════════════════════════════════════════════ --}}
    @if(!empty($leaderboard))
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Performance Leaderboard</div>
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold w-8">#</th>
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Agent</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Location</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals Closed</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Revenue</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close Rate</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Badge</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaderboard as $idx => $agent)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition {{ $idx < 3 ? 'bg-amber-50/30' : '' }}">
                            <td class="px-4 py-2.5 font-bold text-crm-t3">
                                @if($idx === 0)
                                    <span class="text-amber-500 text-base">1</span>
                                @elseif($idx === 1)
                                    <span class="text-gray-400 text-base">2</span>
                                @elseif($idx === 2)
                                    <span class="text-orange-400 text-base">3</span>
                                @else
                                    {{ $idx + 1 }}
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $agent['color'] ?? '#6b7280' }}">{{ $agent['avatar'] ?? '--' }}</div>
                                    <span class="font-semibold">{{ $agent['name'] }}</span>
                                </div>
                            </td>
                            <td class="text-center px-3 py-2.5">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $agent['role'] === 'fronter' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ ucfirst($agent['role']) }}
                                </span>
                            </td>
                            <td class="text-center px-3 py-2.5">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ ($agent['location'] ?? 'US') === 'Panama' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $agent['location'] ?? 'US' }}
                                </span>
                            </td>
                            <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $agent['deals_closed'] ?? 0 }}</td>
                            <td class="text-center px-3 py-2.5 font-mono font-bold text-green-600">${{ number_format($agent['revenue'] ?? 0, 0) }}</td>
                            <td class="text-center px-3 py-2.5">
                                @php $agentCr = $agent['close_rate'] ?? 0; @endphp
                                <span class="font-bold {{ $agentCr >= 50 ? 'text-emerald-600' : ($agentCr >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($agentCr, 1) }}%</span>
                            </td>
                            <td class="text-center px-3 py-2.5">
                                @if($agent['badge'] ?? null)
                                    @php
                                        $badgeColors = [
                                            'High Performer' => 'bg-emerald-100 text-emerald-700',
                                            'Top Revenue' => 'bg-green-100 text-green-700',
                                            'Fast Responder' => 'bg-blue-100 text-blue-700',
                                            'Needs Improvement' => 'bg-red-100 text-red-700',
                                        ];
                                    @endphp
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $badgeColors[$agent['badge']] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $agent['badge'] }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════
         AI PERFORMANCE INSIGHTS
         ════════════════════════════════════════════════════════════ --}}
    @if($canSeeAll && !empty($aiInsights))
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">AI Performance Insights</div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Weakest Fronter Group --}}
            @if($aiInsights['weakest_fronter_group'] ?? null)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-l-[3px] border-l-red-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Weakest Fronter Group</div>
                <div class="text-sm font-bold text-red-600">{{ $aiInsights['weakest_fronter_group']['label'] }}</div>
                <div class="text-xs text-crm-t3 mt-1">{{ $aiInsights['weakest_fronter_group']['insight'] }}</div>
            </div>
            @endif

            {{-- Strongest Closer Group --}}
            @if($aiInsights['strongest_closer_group'] ?? null)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-l-[3px] border-l-emerald-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Strongest Closer Group</div>
                <div class="text-sm font-bold text-emerald-600">{{ $aiInsights['strongest_closer_group']['label'] }}</div>
                <div class="text-xs text-crm-t3 mt-1">{{ $aiInsights['strongest_closer_group']['insight'] }}</div>
            </div>
            @endif

            {{-- Slowest Follow-Up Team --}}
            @if($aiInsights['slowest_follow_up_team'] ?? null)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-l-[3px] border-l-amber-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Slowest Follow-Up Team</div>
                <div class="text-sm font-bold text-amber-600">{{ $aiInsights['slowest_follow_up_team']['label'] }}</div>
                <div class="text-xs text-crm-t3 mt-1">{{ $aiInsights['slowest_follow_up_team']['insight'] }}</div>
            </div>
            @endif

            {{-- Highest Converting Team --}}
            @if($aiInsights['highest_converting_team'] ?? null)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-l-[3px] border-l-blue-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Highest Converting Team</div>
                <div class="text-sm font-bold text-blue-600">{{ $aiInsights['highest_converting_team']['label'] }}</div>
                <div class="text-xs text-crm-t3 mt-1">{{ $aiInsights['highest_converting_team']['insight'] }}</div>
            </div>
            @endif
        </div>

        {{-- Top & Bottom Performers --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            @if($aiInsights['top_performer'] ?? null)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-l-[3px] border-l-emerald-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Top Performer</div>
                <div class="text-sm font-bold text-emerald-600">{{ $aiInsights['top_performer']['name'] }}</div>
                <div class="text-xs text-crm-t3">{{ $aiInsights['top_performer']['label'] }} &mdash; {{ $aiInsights['top_performer']['deals_closed'] }} deals &mdash; ${{ number_format($aiInsights['top_performer']['revenue'], 0) }} revenue</div>
            </div>
            @endif

            @if($aiInsights['bottom_performer'] ?? null)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-l-[3px] border-l-red-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Needs Coaching</div>
                <div class="text-sm font-bold text-red-600">{{ $aiInsights['bottom_performer']['name'] }}</div>
                <div class="text-xs text-crm-t3">{{ $aiInsights['bottom_performer']['label'] }} &mdash; ${{ number_format($aiInsights['bottom_performer']['revenue'], 0) }} revenue</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════
         FRONTER PERFORMANCE
         ════════════════════════════════════════════════════════════ --}}
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Fronter Performance</div>
        @if(!empty($fronterStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Location</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Transfers Sent</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No Deals</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No-Deal %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fronterStats as $fs)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $fs['color'] ?? '#6b7280' }}">{{ $fs['avatar'] ?? '--' }}</div>
                                        <span class="font-semibold">{{ $fs['name'] ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ ($fs['location'] ?? 'US') === 'Panama' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $fs['location'] ?? 'US' }}
                                    </span>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $fs['transfers_sent'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $fs['deals_closed'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $fs['no_deals'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5">
                                    @php $fClosePct = $fs['close_pct'] ?? 0; @endphp
                                    <span class="font-bold {{ $fClosePct >= 50 ? 'text-emerald-600' : ($fClosePct >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($fClosePct, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($fs['no_deal_pct'] ?? 0, 1) }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <p class="text-sm text-crm-t3">No fronter data available for this period</p>
            </div>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════════
         CLOSER PERFORMANCE
         ════════════════════════════════════════════════════════════ --}}
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Closer Performance</div>
        @if(!empty($closerStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Location</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Transfers Received</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Sent to Verification</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Not Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No-Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Verification %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($closerStats as $cs)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $cs['color'] ?? '#6b7280' }}">{{ $cs['avatar'] ?? '--' }}</div>
                                        <span class="font-semibold">{{ $cs['name'] ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ ($cs['location'] ?? 'US') === 'Panama' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $cs['location'] ?? 'US' }}
                                    </span>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $cs['transfers_received'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $cs['deals_closed'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-amber-600">{{ $cs['sent_to_verification'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $cs['not_closed'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5">
                                    @php $cClosePct = $cs['close_pct'] ?? 0; @endphp
                                    <span class="font-bold {{ $cClosePct >= 50 ? 'text-emerald-600' : ($cClosePct >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($cClosePct, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($cs['no_close_pct'] ?? 0, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-bold text-purple-600">{{ number_format($cs['verification_pct'] ?? 0, 1) }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <p class="text-sm text-crm-t3">No closer data available for this period</p>
            </div>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════════
         VERIFICATION / ADMIN PERFORMANCE
         ════════════════════════════════════════════════════════════ --}}
    @if($canSeeAll)
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Verification / Admin Performance</div>
        @if(!empty($adminStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Received</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Charged / Green</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Not Charged</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Charge %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Not Charged %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adminStats as $as)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $as['color'] ?? '#6b7280' }}">{{ $as['avatar'] ?? '--' }}</div>
                                        <span class="font-semibold">{{ $as['name'] ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $as['received'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $as['charged_green'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $as['not_charged'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5">
                                    @php $aChargePct = $as['charge_pct'] ?? 0; @endphp
                                    <span class="font-bold {{ $aChargePct >= 70 ? 'text-emerald-600' : ($aChargePct >= 40 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($aChargePct, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($as['not_charged_pct'] ?? 0, 1) }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <p class="text-sm text-crm-t3">No admin/verification data available for this period</p>
            </div>
        @endif
    </div>
    @endif
</div>
