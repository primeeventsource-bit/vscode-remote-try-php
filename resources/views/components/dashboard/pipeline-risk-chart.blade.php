@props([
    'risks' => [], // ['risk label' => count, ...]
])

@php
    $maxRisk = max(1, max($risks ?: [1]));
    $barColors = ['bg-red-400', 'bg-amber-400', 'bg-orange-400', 'bg-yellow-500', 'bg-gray-400', 'bg-gray-300'];
@endphp

<x-dashboard.card-shell>
    <div class="mb-4">
        <h3 class="text-sm font-bold">Pipeline Risk Breakdown</h3>
        <p class="text-[10px] text-crm-t3 mt-0.5" title="Highlights the most common reasons deals are at risk.">Where deal risk is coming from</p>
    </div>

    @if(count($risks) > 0)
        <div class="space-y-3">
            @foreach(array_slice($risks, 0, 6, true) as $label => $cnt)
                @php $ci = $loop->index; @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-medium text-crm-t2 truncate max-w-[200px]" title="{{ $label }}">{{ Str::limit($label, 35) }}</span>
                        <span class="text-[10px] font-bold text-crm-t1 flex-shrink-0 ml-2">{{ $cnt }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $barColors[$ci] ?? 'bg-gray-400' }} transition-all duration-500" style="width: {{ $cnt / $maxRisk * 100 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state icon="✓" title="No at-risk deals right now" subtitle="Your pipeline looks healthy." />
    @endif
</x-dashboard.card-shell>
