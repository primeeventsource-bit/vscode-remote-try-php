@props([
    'items' => collect(),
])

<x-dashboard.card-shell
    title="Recent AI Updates"
    subtitle="Latest score and recommendation changes"
    tooltip="Shows recent AI decisions and updates."
    :noPadding="true"
>
    @if($items->count() > 0)
        <div class="divide-y divide-crm-border max-h-64 overflow-y-auto">
            @foreach($items as $sc)
                <div class="flex items-center gap-3 px-4 py-2.5">
                    <x-dashboard.status-badge :variant="$sc['entity_type'] === 'lead' ? 'info' : 'improving'" size="xs">
                        {{ $sc['entity_type'] }}
                    </x-dashboard.status-badge>
                    <div class="flex-1 min-w-0">
                        <span class="text-[10px] text-crm-t2 font-medium">#{{ $sc['entity_id'] }} — {{ ucfirst(str_replace('_', ' ', $sc['score_type'])) }}</span>
                    </div>
                    <x-dashboard.status-badge :variant="match($sc['label'] ?? '') { 'hot', 'strong' => 'strong', 'warm', 'medium' => 'warm', 'cold', 'weak' => 'cold', default => 'at_risk' }">
                        {{ $sc['score'] }} {{ ucfirst($sc['label'] ?? '') }}
                    </x-dashboard.status-badge>
                    <span class="text-[9px] text-crm-t3 flex-shrink-0">{{ $sc['calculated_at']?->diffForHumans(short: true) ?? '' }}</span>
                </div>
            @endforeach
        </div>
    @else
        <x-dashboard.empty-state icon="📡" title="No recent updates" subtitle="Open leads or deals to trigger AI scoring." />
    @endif
</x-dashboard.card-shell>
