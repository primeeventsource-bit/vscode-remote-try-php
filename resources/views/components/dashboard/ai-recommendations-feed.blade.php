@props([
    'items' => collect(),
])

<x-dashboard.card-shell
    title="AI Recommendations"
    subtitle="Suggested next actions"
    tooltip="Actions recommended by AI based on current data."
    :noPadding="true"
>
    @if($items->count() > 0)
        <div class="divide-y divide-crm-border max-h-64 overflow-y-auto">
            @foreach($items as $rec)
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <span class="text-blue-500 text-xs flex-shrink-0 mt-0.5">💡</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] font-semibold text-crm-t1">{{ $rec->title }}</div>
                        <p class="text-[9px] text-crm-t3 mt-0.5">{{ Str::limit($rec->message, 60) }}</p>
                    </div>
                    <span class="text-[9px] text-crm-t3 flex-shrink-0">{{ $rec->created_at?->diffForHumans(short: true) ?? '' }}</span>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state icon="📌" title="No recommendations at this time" subtitle="The AI will generate them as you work." />
    @endif
</x-dashboard.card-shell>
