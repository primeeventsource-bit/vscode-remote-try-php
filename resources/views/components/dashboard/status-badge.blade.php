@props([
    'variant' => 'default', // hot, warm, cold, ghost, strong, moderate, weak, at_risk, high, medium, low, due_now, overdue, due_soon, waiting, replied, coaching, improving, performer
    'size' => 'sm',         // xs, sm
    'pulse' => false,
])

@php
    $colors = match($variant) {
        'hot', 'due_now', 'overdue', 'high', 'at_risk' => 'bg-red-100 text-red-600',
        'warm', 'medium', 'due_soon', 'moderate'        => 'bg-amber-100 text-amber-600',
        'cold', 'waiting'                                 => 'bg-blue-100 text-blue-600',
        'ghost'                                           => 'bg-purple-100 text-purple-600',
        'strong', 'very_strong', 'performer', 'replied'  => 'bg-emerald-100 text-emerald-600',
        'weak', 'coaching'                                => 'bg-orange-100 text-orange-600',
        'improving'                                       => 'bg-sky-100 text-sky-600',
        'low'                                             => 'bg-gray-100 text-gray-500',
        'urgent'                                          => 'bg-red-100 text-red-600',
        'info'                                            => 'bg-blue-100 text-blue-600',
        default                                           => 'bg-gray-100 text-gray-500',
    };

    $sizeClass = match($size) {
        'xs' => 'text-[7px] px-1.5 py-0.5',
        default => 'text-[8px] px-2 py-0.5',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-0.5 font-bold rounded-full uppercase tracking-wider whitespace-nowrap {$sizeClass} {$colors}"]) }}>
    @if($pulse)
        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70 animate-pulse"></span>
    @endif
    {{ $slot }}
</span>
