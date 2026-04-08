{{-- Performance Leaderboard Widget --}}
@props(['topAgents' => [], 'canSeeStats' => false])

@if(!empty($topAgents))
<div class="bg-crm-card border border-crm-border rounded-lg mb-6">
    <div class="flex items-center justify-between p-4 border-b border-crm-border">
        <div>
            <div class="text-sm font-bold">Performance Leaderboard</div>
            <div class="text-[10px] text-crm-t3">Top agents ranked by revenue</div>
        </div>
        @if($canSeeStats)
        <a href="/stats" class="text-xs text-blue-500 hover:underline">Full Stats</a>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-crm-border bg-crm-surface">
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold w-8">#</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Agent</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Location</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Revenue</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                    <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Badge</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topAgents as $idx => $agent)
                    <tr class="border-b border-crm-border hover:bg-crm-hover transition {{ $idx < 3 ? 'bg-amber-50/20' : '' }}">
                        <td class="px-3 py-2 font-bold {{ $idx === 0 ? 'text-amber-500' : ($idx === 1 ? 'text-gray-400' : ($idx === 2 ? 'text-orange-400' : 'text-crm-t3')) }}">
                            {{ $idx + 1 }}
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold text-white" style="background: {{ $agent['color'] ?? '#6b7280' }}">{{ $agent['avatar'] ?? '--' }}</div>
                                <span class="font-semibold">{{ $agent['name'] }}</span>
                            </div>
                        </td>
                        <td class="text-center px-2 py-2">
                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full {{ ($agent['role'] ?? '') === 'fronter' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ ucfirst($agent['role'] ?? '--') }}
                            </span>
                        </td>
                        <td class="text-center px-2 py-2">
                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full {{ ($agent['location'] ?? 'US') === 'Panama' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $agent['location'] ?? 'US' }}
                            </span>
                        </td>
                        <td class="text-center px-2 py-2 font-mono font-bold text-emerald-600">{{ $agent['deals_closed'] ?? 0 }}</td>
                        <td class="text-center px-2 py-2 font-mono font-bold text-green-600">${{ number_format($agent['revenue'] ?? 0, 0) }}</td>
                        <td class="text-center px-2 py-2">
                            @php $cr = $agent['close_rate'] ?? 0; @endphp
                            <span class="font-bold {{ $cr >= 50 ? 'text-emerald-600' : ($cr >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($cr, 1) }}%</span>
                        </td>
                        <td class="text-center px-2 py-2">
                            @if($agent['badge'] ?? null)
                                @php
                                    $bc = match($agent['badge']) {
                                        'High Performer' => 'bg-emerald-100 text-emerald-700',
                                        'Top Revenue' => 'bg-green-100 text-green-700',
                                        'Fast Responder' => 'bg-blue-100 text-blue-700',
                                        'Needs Improvement' => 'bg-red-100 text-red-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full {{ $bc }}">{{ $agent['badge'] }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
