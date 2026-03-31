<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Statistics</h2>
        <p class="text-xs text-crm-t3 mt-1">Performance analytics and revenue breakdown</p>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @foreach(['revenue' => 'Revenue Periods', 'fronters' => 'Fronter Stats', 'closers' => 'Closer Stats', 'deals' => 'All Deals'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Revenue Periods Tab --}}
    @if($tab === 'revenue')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach([
                ['Weekly', $periodData['weekly'] ?? null, 'blue'],
                ['Monthly', $periodData['monthly'] ?? null, 'purple'],
                ['Quarterly', $periodData['quarterly'] ?? null, 'emerald'],
                ['Yearly', $periodData['yearly'] ?? null, 'amber'],
            ] as [$periodLabel, $pd, $color])
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-{{ $color }}-500">
                    <div class="text-sm font-bold mb-3">{{ $periodLabel }}</div>
                    @if($pd)
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-crm-t3">Charged Revenue</span>
                                <span class="font-mono font-bold text-emerald-500">${{ number_format($pd['charged'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-crm-t3">Chargebacks</span>
                                <span class="font-mono font-bold text-red-500">-${{ number_format($pd['chargebacks'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-t border-crm-border pt-2">
                                <span class="text-xs font-semibold">Net Revenue</span>
                                <span class="font-mono font-extrabold text-{{ $color }}-500">${{ number_format(($pd['charged'] ?? 0) - ($pd['chargebacks'] ?? 0)) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-[10px] text-crm-t3">
                                <span>{{ $pd['deal_count'] ?? 0 }} deals</span>
                                <span>{{ $pd['cb_count'] ?? 0 }} chargebacks</span>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-crm-t3">No data available</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Fronter Stats Tab --}}
    @if($tab === 'fronters')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @if(isset($fronterStats) && count($fronterStats))
                @foreach($fronterStats as $fs)
                    @php $fUser = $users->firstWhere('id', $fs['user_id'] ?? null); @endphp
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                        <div class="flex items-center gap-3 mb-3 pb-3 border-b border-crm-border">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white" style="background: {{ $fUser->color ?? '#ec4899' }}">{{ $fUser->avatar ?? substr($fUser->name ?? '?', 0, 2) }}</div>
                            <div>
                                <div class="text-sm font-bold">{{ $fUser->name ?? 'Unknown' }}</div>
                                <div class="text-[10px] text-crm-t3">Fronter</div>
                            </div>
                        </div>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Total Leads</span>
                                <span class="font-semibold font-mono">{{ $fs['leads'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Transfers</span>
                                <span class="font-semibold font-mono">{{ $fs['transfers'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Charged</span>
                                <span class="font-semibold font-mono text-emerald-500">${{ number_format($fs['charged'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Chargebacks</span>
                                <span class="font-semibold font-mono text-red-500">${{ number_format($fs['chargebacks'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-crm-border pt-1.5">
                                <span class="text-crm-t3">Fronting Rate</span>
                                <span class="font-bold text-blue-600">{{ number_format($fs['fronting_rate'] ?? 0, 1) }}%</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-span-full bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                    <p class="text-sm text-crm-t3">No fronter data available</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Closer Stats Tab --}}
    @if($tab === 'closers')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @if(isset($closerStats) && count($closerStats))
                @foreach($closerStats as $cs)
                    @php $cUser = $users->firstWhere('id', $cs['user_id'] ?? null); @endphp
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                        <div class="flex items-center gap-3 mb-3 pb-3 border-b border-crm-border">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white" style="background: {{ $cUser->color ?? '#8b5cf6' }}">{{ $cUser->avatar ?? substr($cUser->name ?? '?', 0, 2) }}</div>
                            <div>
                                <div class="text-sm font-bold">{{ $cUser->name ?? 'Unknown' }}</div>
                                <div class="text-[10px] text-crm-t3">Closer</div>
                            </div>
                        </div>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Received</span>
                                <span class="font-semibold font-mono">{{ $cs['received'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Self-Sourced</span>
                                <span class="font-semibold font-mono">{{ $cs['self_sourced'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Total Deals</span>
                                <span class="font-semibold font-mono">{{ $cs['deals'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Charged</span>
                                <span class="font-semibold font-mono text-emerald-500">${{ number_format($cs['charged'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Chargebacks</span>
                                <span class="font-semibold font-mono text-red-500">${{ number_format($cs['cb'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Net Revenue</span>
                                <span class="font-bold font-mono text-emerald-500">${{ number_format($cs['revenue'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-crm-border pt-1.5">
                                <span class="text-crm-t3">Close Rate</span>
                                <span class="font-bold text-purple-600">{{ number_format($cs['close_rate'] ?? 0, 1) }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-crm-t3">Charged Rate</span>
                                <span class="font-bold text-emerald-600">{{ number_format($cs['charged_rate'] ?? 0, 1) }}%</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-span-full bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                    <p class="text-sm text-crm-t3">No closer data available</p>
                </div>
            @endif
        </div>
    @endif

    {{-- All Deals Tab --}}
    @if($tab === 'deals')
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Resort</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fee</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fronter</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closer</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Charged</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">CB</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deals as $deal)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-3 py-2 font-semibold">{{ $deal->owner_name }}</td>
                                <td class="px-3 py-2 text-crm-t2">{{ $deal->resort_name }}</td>
                                <td class="px-3 py-2 font-mono font-bold text-emerald-500">${{ number_format($deal->fee, 2) }}</td>
                                <td class="px-3 py-2 text-crm-t2">{{ $users->firstWhere('id', $deal->fronter)?->name ?? '--' }}</td>
                                <td class="px-3 py-2 text-crm-t2">{{ $users->firstWhere('id', $deal->closer)?->name ?? '--' }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ match(true) {
                                        $deal->charged_back === 'yes' => 'bg-red-50 text-red-500',
                                        $deal->charged === 'yes' => 'bg-emerald-50 text-emerald-600',
                                        $deal->status === 'cancelled' => 'bg-gray-100 text-gray-500',
                                        default => 'bg-amber-50 text-amber-600',
                                    } }}">{{ match(true) {
                                        $deal->charged_back === 'yes' => 'CB',
                                        $deal->charged === 'yes' => 'Charged',
                                        $deal->status === 'cancelled' => 'Cancelled',
                                        default => ucfirst(str_replace('_', ' ', $deal->status ?? 'pending')),
                                    } }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs">{{ $deal->charged === 'yes' ? 'Yes' : 'No' }}</td>
                                <td class="px-3 py-2 text-xs">{{ $deal->charged_back === 'yes' ? 'Yes' : 'No' }}</td>
                                <td class="px-3 py-2 text-crm-t3 text-xs font-mono">{{ $deal->timestamp?->format('n/j/Y') ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-8 text-center text-crm-t3 text-sm">No deals found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
