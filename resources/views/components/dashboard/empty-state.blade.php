@props([
    'icon' => '📋',
    'title' => 'No data available.',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'p-8 text-center']) }}>
    <div class="text-2xl opacity-30 mb-2">{{ $icon }}</div>
    <p class="text-xs text-crm-t3 font-medium">{{ $title }}</p>
    @if($subtitle)
        <p class="text-[10px] text-crm-t3 mt-1">{{ $subtitle }}</p>
    @endif
</div>
