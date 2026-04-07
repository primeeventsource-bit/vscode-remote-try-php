@props([
    'title' => null,
    'subtitle' => null,
    'tooltip' => null,
    'badge' => null,       // slot or string
    'badgeVariant' => 'default',
    'count' => null,       // number badge in header
    'countVariant' => 'info',
    'noPadding' => false,  // for tables that need flush edges
])

<div {{ $attributes->merge(['class' => 'bg-crm-card border border-crm-border rounded-xl overflow-hidden']) }}
     @if($tooltip) title="{{ $tooltip }}" @endif>
    @if($title)
        <div class="px-4 py-3 border-b border-crm-border bg-crm-surface flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-crm-t1">{{ $title }}</h3>
                @if($subtitle)
                    <p class="text-[10px] text-crm-t3 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if($count !== null)
                    <x-dashboard.status-badge :variant="$countVariant">{{ $count }}</x-dashboard.status-badge>
                @endif
                @if($badge)
                    <x-dashboard.status-badge :variant="$badgeVariant">{{ $badge }}</x-dashboard.status-badge>
                @endif
                @if(isset($headerActions))
                    {{ $headerActions }}
                @endif
            </div>
        </div>
    @endif
    <div @class([$noPadding ? '' : 'p-4'])>
        {{ $slot }}
    </div>
</div>
