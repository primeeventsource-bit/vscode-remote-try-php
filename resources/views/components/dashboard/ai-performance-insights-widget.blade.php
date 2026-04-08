{{-- AI Performance Insights Widget --}}
@props(['aiInsights' => []])

@if(!empty($aiInsights))
<div class="bg-crm-card border border-crm-border rounded-lg mb-6">
    <div class="flex items-center justify-between p-4 border-b border-crm-border">
        <div>
            <div class="text-sm font-bold">AI Performance Insights</div>
            <div class="text-[10px] text-crm-t3">Automated analysis of team and agent performance</div>
        </div>
        <a href="/stats" class="text-xs text-blue-500 hover:underline">View Details</a>
    </div>

    <div class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            {{-- Weakest Fronter Group --}}
            @if($aiInsights['weakest_fronter_group'] ?? null)
            <div class="bg-white border border-crm-border rounded-lg p-3 border-l-[3px] border-l-red-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Weakest Fronter Group</div>
                <div class="text-sm font-bold text-red-600">{{ $aiInsights['weakest_fronter_group']['label'] }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $aiInsights['weakest_fronter_group']['insight'] }}</div>
            </div>
            @endif

            {{-- Strongest Closer Group --}}
            @if($aiInsights['strongest_closer_group'] ?? null)
            <div class="bg-white border border-crm-border rounded-lg p-3 border-l-[3px] border-l-emerald-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Strongest Closer Group</div>
                <div class="text-sm font-bold text-emerald-600">{{ $aiInsights['strongest_closer_group']['label'] }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $aiInsights['strongest_closer_group']['insight'] }}</div>
            </div>
            @endif

            {{-- Slowest Follow-Up --}}
            @if($aiInsights['slowest_follow_up_team'] ?? null)
            <div class="bg-white border border-crm-border rounded-lg p-3 border-l-[3px] border-l-amber-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Slowest Follow-Up</div>
                <div class="text-sm font-bold text-amber-600">{{ $aiInsights['slowest_follow_up_team']['label'] }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $aiInsights['slowest_follow_up_team']['insight'] }}</div>
            </div>
            @endif

            {{-- Highest Converting --}}
            @if($aiInsights['highest_converting_team'] ?? null)
            <div class="bg-white border border-crm-border rounded-lg p-3 border-l-[3px] border-l-blue-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Top Converting Team</div>
                <div class="text-sm font-bold text-blue-600">{{ $aiInsights['highest_converting_team']['label'] }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $aiInsights['highest_converting_team']['insight'] }}</div>
            </div>
            @endif
        </div>

        {{-- Top & Bottom Performer --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @if($aiInsights['top_performer'] ?? null)
            <div class="bg-white border border-crm-border rounded-lg p-3 border-l-[3px] border-l-emerald-500">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Top Performer</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-emerald-100 text-emerald-700">High Performer</span>
                </div>
                <div class="text-sm font-bold text-emerald-600">{{ $aiInsights['top_performer']['name'] }}</div>
                <div class="text-[10px] text-crm-t3">{{ $aiInsights['top_performer']['label'] }} &mdash; {{ $aiInsights['top_performer']['deals_closed'] }} deals &mdash; ${{ number_format($aiInsights['top_performer']['revenue'], 0) }}</div>
            </div>
            @endif

            @if($aiInsights['bottom_performer'] ?? null)
            <div class="bg-white border border-crm-border rounded-lg p-3 border-l-[3px] border-l-red-500">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Needs Coaching</span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-red-100 text-red-700">Needs Improvement</span>
                </div>
                <div class="text-sm font-bold text-red-600">{{ $aiInsights['bottom_performer']['name'] }}</div>
                <div class="text-[10px] text-crm-t3">{{ $aiInsights['bottom_performer']['label'] }} &mdash; ${{ number_format($aiInsights['bottom_performer']['revenue'], 0) }} revenue</div>
            </div>
            @endif
        </div>
    </div>
</div>
@endif
