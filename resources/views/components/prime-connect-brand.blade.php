{{-- Prime Connect Brand Elements --}}
@props(['size' => 'md'])

@php
    $sizes = [
        'sm' => ['logo' => 'text-sm', 'icon' => 'w-5 h-5', 'dot' => 'w-1 h-1'],
        'md' => ['logo' => 'text-lg', 'icon' => 'w-7 h-7', 'dot' => 'w-1.5 h-1.5'],
        'lg' => ['logo' => 'text-2xl', 'icon' => 'w-10 h-10', 'dot' => 'w-2 h-2'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
@endphp

<div class="flex items-center gap-2">
    {{-- Signal Icon --}}
    <div class="{{ $s['icon'] }} relative flex items-center justify-center rounded-xl bg-gradient-to-br from-pc-primary to-pc-accent shadow-lg shadow-pc-primary/20">
        <span class="text-lg">🔗</span>
    </div>
    {{-- Wordmark --}}
    <span class="{{ $s['logo'] }} font-extrabold tracking-tight">
        <span class="bg-gradient-to-r from-pc-primary to-pc-accent bg-clip-text text-transparent">Prime</span>
        <span class="text-white ml-0.5">Connect</span>
    </span>
</div>
