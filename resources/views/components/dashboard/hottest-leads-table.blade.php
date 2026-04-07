@props([
    'leads' => collect(),
    'limit' => 8,
])

<x-dashboard.card-shell
    title="Hottest Leads"
    subtitle="Leads most likely to convert"
    tooltip="Top leads ranked by AI score and engagement signals."
    :count="$leads->count() > 0 ? $leads->count() : null"
    countVariant="hot"
    :noPadding="true"
>
    @if($leads->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-white">
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Lead name">Lead</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Assigned user">Rep</th>
                        <th class="text-center px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="AI lead score">Score</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Recommended next step">Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads->take($limit) as $l)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition cursor-pointer"
                            x-data @click="$dispatch('open-drilldown', { type: 'lead', id: {{ $l['id'] }}, score: {{ $l['score'] }}, label: '{{ $l['label'] }}', reasons: {{ json_encode($l['reasons'] ?? []) }}, risks: {{ json_encode($l['risks'] ?? []) }}, nextAction: '{{ addslashes($l['next_action'] ?? '') }}' })">
                            <td class="px-3 py-2.5 font-semibold truncate max-w-[160px]" title="{{ $l['owner_name'] }}">{{ Str::limit($l['owner_name'], 22) }}</td>
                            <td class="px-3 py-2.5 text-crm-t3">{{ $l['assigned_name'] }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <x-dashboard.status-badge :variant="$l['label']">
                                    {{ $l['score'] }} {{ ucfirst($l['label']) }}
                                </x-dashboard.status-badge>
                            </td>
                            <td class="px-3 py-2.5 text-[10px] text-blue-600 font-medium truncate max-w-[180px]" title="{{ $l['next_action'] }}">{{ Str::limit($l['next_action'], 35) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-dashboard.empty-state icon="🔍" title="No high-priority leads available" subtitle="Keep working inbound activity and follow-ups." />
    @endif
</x-dashboard.card-shell>
