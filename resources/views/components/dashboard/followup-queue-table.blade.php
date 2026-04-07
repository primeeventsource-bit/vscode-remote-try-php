@props([
    'items' => collect(),
])

<x-dashboard.card-shell
    title="Follow-Up Queue"
    subtitle="Who needs contact right now"
    tooltip="Prioritized list of leads and deals requiring follow-up."
    :count="$items->count() > 0 ? $items->count() . ' pending' : null"
    countVariant="info"
    :noPadding="true"
>
    @if($items->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-white">
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Type</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Rep</th>
                        <th class="text-center px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Urgency level">Priority</th>
                        <th class="text-center px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Recommended contact method">Channel</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-right px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Age</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $fq)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition {{ $fq['priority'] === 'High' ? 'bg-red-50/30' : '' }}">
                            <td class="px-3 py-2.5 font-semibold truncate max-w-[140px]" title="{{ $fq['owner_name'] }}">{{ Str::limit($fq['owner_name'], 22) }}</td>
                            <td class="px-3 py-2.5 text-crm-t3">{{ $fq['type'] }}</td>
                            <td class="px-3 py-2.5 text-crm-t3">{{ $fq['assigned_name'] }}</td>
                            <td class="px-3 py-2.5 text-center">
                                @php $pVar = match($fq['priority']) { 'High' => 'high', 'Medium' => 'medium', default => 'low' }; @endphp
                                <x-dashboard.status-badge :variant="$pVar" :pulse="$fq['priority'] === 'High'">{{ $fq['priority'] }}</x-dashboard.status-badge>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600">{{ $fq['channel'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-[10px] text-crm-t3">{{ $fq['disposition'] }}</td>
                            <td class="px-3 py-2.5 text-right text-[10px] text-crm-t3">{{ $fq['age_days'] }}d</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-dashboard.empty-state icon="✓" title="No follow-ups required right now" subtitle="You're caught up." />
    @endif
</x-dashboard.card-shell>
