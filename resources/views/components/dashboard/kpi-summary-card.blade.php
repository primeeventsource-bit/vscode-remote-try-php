@props([
    'title',
    'subtitle' => null,
    'value' => 0,
    'prefix' => '',        // "$" for currency
    'tooltip' => null,
    'badge' => null,
    'badgeVariant' => 'default',
    'accentColor' => 'blue', // blue, red, emerald, amber, purple, gray
    'footer' => null,
    'state' => 'ready',    // ready, loading, empty, stale, updating
])

@php
    $borderColor = match($accentColor) {
        'red'     => 'border-t-red-500',
        'emerald' => 'border-t-emerald-500',
        'amber'   => 'border-t-amber-500',
        'purple'  => 'border-t-purple-500',
        'gray'    => 'border-t-gray-400',
        default   => 'border-t-blue-500',
    };
    $valueColor = match($accentColor) {
        'red'     => 'text-red-500',
        'emerald' => 'text-emerald-500',
        'amber'   => 'text-amber-500',
        'purple'  => 'text-purple-500',
        'gray'    => 'text-gray-400',
        default   => 'text-blue-600',
    };
    $isEmpty = $state === 'empty' || ($value === 0 && $state === 'ready');
@endphp

<div class="bg-crm-card border border-crm-border rounded-xl p-4 border-t-[3px] {{ $borderColor }} relative"
     @if($tooltip) title="{{ $tooltip }}" @endif>

    {{-- Updating indicator --}}
    @if($state === 'updating')
        <div class="absolute top-1.5 right-1.5">
            <span class="flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span></span>
        </div>
    @endif

    {{-- Header row --}}
    <div class="flex items-center justify-between">
        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">{{ $title }}</div>
        @if($badge)
            <x-dashboard.status-badge :variant="$badgeVariant">{{ $badge }}</x-dashboard.status-badge>
        @endif
    </div>

    {{-- Value --}}
    @if($state === 'loading')
        <div class="h-8 w-16 bg-gray-200 rounded animate-pulse mt-1"></div>
    @else
        <div class="text-2xl font-extrabold {{ $isEmpty ? 'text-gray-300' : $valueColor }} mt-1">
            {{ $prefix }}{{ is_numeric($value) ? number_format($value) : $value }}
        </div>
    @endif

    {{-- Subtitle / footer --}}
    @if($subtitle)
        <div class="text-[9px] text-crm-t3 mt-1">{{ $subtitle }}</div>
    @endif
    @if($footer)
        <div class="text-[9px] text-crm-t3 mt-0.5 opacity-70">{{ $footer }}</div>
    @endif
</div>
