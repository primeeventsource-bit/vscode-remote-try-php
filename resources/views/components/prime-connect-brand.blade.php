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
        <svg class="w-[60%] h-[60%] text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0"/>
            <circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/>
        </svg>
    </div>
    {{-- Wordmark --}}
    <span class="{{ $s['logo'] }} font-extrabold tracking-tight">
        <span class="bg-gradient-to-r from-pc-primary to-pc-accent bg-clip-text text-transparent">Prime</span>
        <span class="text-white ml-0.5">Connect</span>
    </span>
</div>
