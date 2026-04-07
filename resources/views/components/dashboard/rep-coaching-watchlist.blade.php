@props([
    'users' => collect(),
])

<x-dashboard.card-shell
    title="Rep Coaching Watchlist"
    subtitle="Users who need attention"
    tooltip="Highlights reps with performance issues or coaching needs."
    :noPadding="true"
>
    @if($users->count() > 0)
        <div class="divide-y divide-crm-border">
            @foreach($users as $cw)
                <div class="flex items-center gap-3 px-4 py-3 hover:bg-crm-hover transition">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[9px] font-bold text-white flex-shrink-0"
                         style="background: {{ $cw['color'] ?? '#6b7280' }}">{{ $cw['avatar'] ?? substr($cw['name'], 0, 2) }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold">{{ $cw['name'] }}</div>
                        <div class="text-[10px] text-crm-t3 capitalize">{{ str_replace('_', ' ', $cw['role']) }}</div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <x-dashboard.status-badge :variant="$cw['severity'] === 'high' ? 'high' : ($cw['severity'] === 'medium' ? 'medium' : 'low')">
                            {{ $cw['mistake_count'] }} {{ Str::plural('issue', $cw['mistake_count']) }}
                        </x-dashboard.status-badge>
                        <div class="text-[9px] text-crm-t3 mt-0.5">{{ $cw['top_weakness'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state icon="✓" title="No coaching issues detected" />
    @endif
</x-dashboard.card-shell>
