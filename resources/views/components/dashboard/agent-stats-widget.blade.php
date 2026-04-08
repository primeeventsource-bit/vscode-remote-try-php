{{-- Agent Statistics Widget — Role + Location Performance --}}
@props(['agentSummary' => [], 'roleBreakdown' => [], 'topAgents' => []])

@if(!empty($agentSummary))
<div class="mb-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-bold">Agent Performance by Location</h3>
        <a href="/stats" class="text-xs text-blue-500 hover:underline">View Full Stats</a>
    </div>

    {{-- Fronter + Closer Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-blue-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Total Leads</div>
            <div class="text-lg font-extrabold text-blue-500">{{ number_format($agentSummary['fronter']['total_leads'] ?? 0) }}</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-indigo-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Transfer Rate</div>
            <div class="text-lg font-extrabold text-indigo-500">{{ number_format($agentSummary['fronter']['transfer_rate'] ?? 0, 1) }}%</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-emerald-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deals Closed</div>
            <div class="text-lg font-extrabold text-emerald-500">{{ number_format($agentSummary['closer']['deals_closed'] ?? 0) }}</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-green-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Close Rate</div>
            <div class="text-lg font-extrabold text-green-500">{{ number_format($agentSummary['closer']['close_rate'] ?? 0, 1) }}%</div>
        </div>
    </div>

    {{-- Role Breakdown Mini-Table --}}
    @if(!empty($roleBreakdown))
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-4">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-crm-border bg-crm-surface">
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Group</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Agents</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Leads</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Revenue</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roleBreakdown as $row)
                <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                    <td class="px-3 py-1.5 font-semibold">
                        <span class="inline-flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full {{ $row['role'] === 'fronter' ? 'bg-blue-500' : 'bg-emerald-500' }}"></span>
                            {{ $row['label'] }}
                        </span>
                    </td>
                    <td class="text-center px-2 py-1.5 font-mono">{{ $row['agent_count'] }}</td>
                    <td class="text-center px-2 py-1.5 font-mono">{{ number_format($row['leads']) }}</td>
                    <td class="text-center px-2 py-1.5 font-mono font-bold text-emerald-600">{{ number_format($row['deals_closed']) }}</td>
                    <td class="text-center px-2 py-1.5 font-mono text-green-600">${{ number_format($row['revenue'], 0) }}</td>
                    <td class="text-center px-2 py-1.5">
                        @php $cr = $row['close_rate'] ?? 0; @endphp
                        <span class="font-bold {{ $cr >= 50 ? 'text-emerald-600' : ($cr >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($cr, 1) }}%</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Top Agents Quick List --}}
    @if(!empty($topAgents))
    <div class="bg-crm-card border border-crm-border rounded-lg p-3">
        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Top Agents</div>
        <div class="space-y-2">
            @foreach(array_slice($topAgents, 0, 5) as $idx => $agent)
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-crm-t3 w-4">{{ $idx + 1 }}</span>
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold text-white" style="background: {{ $agent['color'] ?? '#6b7280' }}">{{ $agent['avatar'] ?? '--' }}</div>
                    <span class="text-xs font-semibold">{{ $agent['name'] }}</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full {{ ($agent['location'] ?? 'US') === 'Panama' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">{{ $agent['location'] ?? 'US' }}</span>
                </div>
                <div class="text-xs font-mono font-bold text-green-600">${{ number_format($agent['revenue'] ?? 0, 0) }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endif
