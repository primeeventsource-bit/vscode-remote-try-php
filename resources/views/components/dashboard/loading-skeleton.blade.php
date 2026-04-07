@props([
    'lines' => 3,
    'type' => 'card', // card, table, chart
])

<div {{ $attributes->merge(['class' => 'animate-pulse']) }}>
    @if($type === 'card')
        <div class="bg-crm-card border border-crm-border rounded-xl p-5">
            <div class="h-3 w-24 bg-gray-200 rounded mb-3"></div>
            <div class="h-8 w-16 bg-gray-200 rounded mb-2"></div>
            <div class="h-2 w-32 bg-gray-100 rounded"></div>
        </div>
    @elseif($type === 'table')
        <div class="bg-crm-card border border-crm-border rounded-xl overflow-hidden">
            <div class="h-10 bg-crm-surface border-b border-crm-border"></div>
            @for($i = 0; $i < $lines; $i++)
                <div class="flex items-center gap-4 px-4 py-3 border-b border-crm-border">
                    <div class="h-3 w-24 bg-gray-200 rounded"></div>
                    <div class="h-3 w-16 bg-gray-100 rounded"></div>
                    <div class="h-3 w-12 bg-gray-200 rounded"></div>
                    <div class="h-3 w-20 bg-gray-100 rounded"></div>
                </div>
            @endfor
        </div>
    @elseif($type === 'chart')
        <div class="bg-crm-card border border-crm-border rounded-xl p-5">
            <div class="h-3 w-32 bg-gray-200 rounded mb-4"></div>
            <div class="flex items-end gap-2 h-40">
                @for($i = 0; $i < 5; $i++)
                    <div class="flex-1 bg-gray-100 rounded-t" style="height: {{ rand(30, 100) }}%"></div>
                @endfor
            </div>
        </div>
    @endif
</div>
