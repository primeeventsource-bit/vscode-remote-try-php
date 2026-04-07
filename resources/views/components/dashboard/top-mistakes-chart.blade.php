@props([
    'mistakes' => collect(), // collection with ->mistake_type and ->cnt
])

<x-dashboard.card-shell>
    <div class="mb-4">
        <h3 class="text-sm font-bold">Top Mistake Patterns</h3>
        <p class="text-[10px] text-crm-t3 mt-0.5">Most common behavior issues this period</p>
    </div>

    @if($mistakes->count() > 0)
        @php $maxMistake = max(1, $mistakes->max('cnt')); @endphp
        <div class="space-y-2.5">
            @foreach($mistakes as $tm)
                <div>
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="text-[10px] font-medium text-crm-t2">{{ ucfirst(str_replace('_', ' ', $tm->mistake_type)) }}</span>
                        <span class="text-[10px] font-bold text-crm-t1">{{ $tm->cnt }}</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-red-400 transition-all duration-500" style="width: {{ $tm->cnt / $maxMistake * 100 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state icon="📋" title="No mistake data yet" />
    @endif
</x-dashboard.card-shell>
