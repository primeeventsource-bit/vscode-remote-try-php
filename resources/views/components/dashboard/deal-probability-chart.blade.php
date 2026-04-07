@props([
    'bins' => [],       // ['80-100' => count, ...]
    'revenue' => [],    // ['80-100' => revenue, ...]
])

@php
    $maxBin = max(1, max($bins ?: [1]));
    $config = [
        '80-100' => ['color' => 'bg-emerald-500', 'text' => 'text-emerald-500', 'label' => 'Very Strong'],
        '60-79'  => ['color' => 'bg-blue-500',    'text' => 'text-blue-500',    'label' => 'Strong'],
        '40-59'  => ['color' => 'bg-amber-500',   'text' => 'text-amber-500',   'label' => 'Moderate'],
        '20-39'  => ['color' => 'bg-orange-500',  'text' => 'text-orange-500',  'label' => 'Weak'],
        '0-19'   => ['color' => 'bg-red-500',     'text' => 'text-red-500',     'label' => 'At Risk'],
    ];
@endphp

<x-dashboard.card-shell>
    <div class="mb-4">
        <h3 class="text-sm font-bold">Deal Close Probability</h3>
        <p class="text-[10px] text-crm-t3 mt-0.5" title="Shows how your deals are distributed across probability ranges.">Distribution of deals by likelihood to close</p>
    </div>
    <div class="space-y-2.5">
        @foreach($config as $bin => $cfg)
            @php $count = $bins[$bin] ?? 0; $rev = $revenue[$bin] ?? 0; @endphp
            <div class="flex items-center gap-3">
                <div class="w-16 text-right flex-shrink-0">
                    <span class="text-[10px] font-semibold text-crm-t2">{{ $bin }}%</span>
                </div>
                <div class="flex-1 bg-gray-100 rounded-full h-7 relative overflow-hidden">
                    <div class="{{ $cfg['color'] }} h-full rounded-full transition-all duration-700 flex items-center"
                         style="width: {{ $maxBin > 0 ? max(($count > 0 ? 8 : 0), $count / $maxBin * 100) : 0 }}%">
                        @if($count > 0)
                            <span class="text-[9px] font-bold text-white ml-2.5">{{ $count }} {{ Str::plural('deal', $count) }}</span>
                        @endif
                    </div>
                </div>
                <div class="w-28 text-left flex-shrink-0">
                    <span class="text-[9px] font-bold {{ $cfg['text'] }}">{{ $cfg['label'] }}</span>
                    @if($rev > 0)
                        <div class="text-[8px] text-crm-t3">${{ number_format($rev) }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-dashboard.card-shell>
