@props([
    'deals' => collect(),
    'limit' => 8,
])

<x-dashboard.card-shell
    title="At-Risk Deals"
    subtitle="Deals requiring immediate attention"
    tooltip="Deals flagged by AI as likely to stall or be lost."
    :count="$deals->count() > 0 ? $deals->count() : null"
    countVariant="at_risk"
    :noPadding="true"
>
    @if($deals->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-white">
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Deal name">Deal</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Assigned user">Closer</th>
                        <th class="text-right px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Deal value">Value</th>
                        <th class="text-center px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="AI close probability">Close %</th>
                        <th class="text-center px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Risk severity">Risk</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold" title="Recommended next step">Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deals->take($limit) as $d)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition cursor-pointer"
                            x-data @click="$dispatch('open-drilldown', { type: 'deal', id: {{ $d['id'] }}, score: {{ $d['close_pct'] }}, label: '{{ $d['label'] }}', risks: {{ json_encode($d['risks'] ?? []) }}, nextAction: '{{ addslashes($d['next_action'] ?? '') }}' })">
                            <td class="px-3 py-2.5 font-semibold truncate max-w-[140px]" title="{{ $d['owner_name'] }}">{{ Str::limit($d['owner_name'], 22) }}</td>
                            <td class="px-3 py-2.5 text-crm-t3">{{ $d['closer_name'] }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold">${{ number_format($d['fee']) }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <x-dashboard.status-badge :variant="$d['close_pct'] < 20 ? 'at_risk' : ($d['close_pct'] < 40 ? 'weak' : 'moderate')">
                                    {{ $d['close_pct'] }}%
                                </x-dashboard.status-badge>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <x-dashboard.status-badge :variant="$d['label'] === 'at_risk' ? 'high' : ($d['label'] === 'weak' ? 'medium' : 'low')">
                                    {{ ucfirst(str_replace('_', ' ', $d['label'])) }}
                                </x-dashboard.status-badge>
                            </td>
                            <td class="px-3 py-2.5 text-[10px] text-blue-600 font-medium truncate max-w-[160px]" title="{{ $d['next_action'] }}">{{ Str::limit($d['next_action'], 30) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-dashboard.empty-state icon="✓" title="No at-risk deals" subtitle="Your pipeline is healthy." />
    @endif
</x-dashboard.card-shell>
