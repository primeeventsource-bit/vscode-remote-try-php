<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Statistics</h2>
            <p class="text-xs text-crm-t3 mt-1">Pipeline performance — fronters, closers, and verification</p>
        </div>

        {{-- View By Dropdown --}}
        <div class="flex items-center gap-2">
            <span class="text-xs text-crm-t3 font-semibold">View By:</span>
            <select wire:model.live="statsRange" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                <option value="live">Live</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
            </select>
        </div>
    </div>

    {{-- Section Tabs --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @foreach(['summary' => 'Pipeline Summary', 'fronters' => 'Fronter Stats', 'closers' => 'Closer Stats', 'admins' => 'Admin / Verification'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SECTION 1: PIPELINE SUMMARY
         ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'summary')
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Fronter Transfers</div>
                <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ number_format($summary['total_transfers'] ?? 0) }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Deals Closed</div>
                <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ number_format($summary['total_deals_closed'] ?? 0) }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($summary['transfer_to_deal_pct'] ?? 0, 1) }}% of transfers</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-amber-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Sent to Verification</div>
                <div class="text-2xl font-extrabold text-amber-500 mt-1">{{ number_format($summary['total_sent_to_verification'] ?? 0) }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($summary['deal_to_verification_pct'] ?? 0, 1) }}% of deals</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged / Green</div>
                <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ number_format($summary['total_charged_green'] ?? 0) }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ number_format($summary['verification_charge_pct'] ?? 0, 1) }}% charge rate</div>
            </div>
        </div>

        {{-- Conversion funnel --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-5">
            <div class="text-sm font-bold mb-4">Conversion Funnel</div>
            @php
                $funnelSteps = [
                    ['label' => 'Transfers Sent', 'value' => $summary['total_transfers'] ?? 0, 'color' => 'bg-blue-500'],
                    ['label' => 'Deals Closed', 'value' => $summary['total_deals_closed'] ?? 0, 'color' => 'bg-purple-500'],
                    ['label' => 'Sent to Verification', 'value' => $summary['total_sent_to_verification'] ?? 0, 'color' => 'bg-amber-500'],
                    ['label' => 'Charged Green', 'value' => $summary['total_charged_green'] ?? 0, 'color' => 'bg-emerald-500'],
                ];
                $maxVal = max(1, $summary['total_transfers'] ?? 1);
            @endphp
            <div class="space-y-3">
                @foreach($funnelSteps as $step)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-crm-t3">{{ $step['label'] }}</span>
                            <span class="font-bold font-mono">{{ number_format($step['value']) }}</span>
                        </div>
                        <div class="h-5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full {{ $step['color'] }} rounded-full transition-all duration-500"
                                 style="width: {{ $maxVal > 0 ? round($step['value'] / $maxVal * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 pt-3 border-t border-crm-border flex justify-between text-xs">
                <span class="text-crm-t3">Overall Conversion (Transfer &rarr; Charged)</span>
                <span class="font-extrabold text-emerald-600">{{ number_format($summary['overall_conversion_pct'] ?? 0, 1) }}%</span>
            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════
         SECTION 2: FRONTER STATS
         ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'fronters')
        @if(!empty($fronterStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fronter</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Transfers Sent</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closed Into Deals</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No Deal</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No-Deal %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fronterStats as $fs)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $fs['color'] }}">{{ $fs['avatar'] }}</div>
                                        <span class="font-semibold">{{ $fs['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $fs['transfers_sent'] }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $fs['deals_closed'] }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $fs['no_deals'] }}</td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-bold {{ $fs['close_pct'] >= 50 ? 'text-emerald-600' : ($fs['close_pct'] >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($fs['close_pct'], 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($fs['no_deal_pct'], 1) }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <p class="text-sm text-crm-t3">No fronter data available for this period</p>
            </div>
        @endif
    @endif

    {{-- ════════════════════════════════════════════════════════════
         SECTION 3: CLOSER STATS
         ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'closers')
        @if(!empty($closerStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closer</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Received</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">To Verification</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Not Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No-Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Verif %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($closerStats as $cs)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $cs['color'] }}">{{ $cs['avatar'] }}</div>
                                        <span class="font-semibold">{{ $cs['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $cs['transfers_received'] }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $cs['deals_closed'] }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-amber-600">{{ $cs['sent_to_verification'] }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $cs['not_closed'] }}</td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-bold {{ $cs['close_pct'] >= 50 ? 'text-emerald-600' : ($cs['close_pct'] >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($cs['close_pct'], 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($cs['no_close_pct'], 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-bold text-purple-600">{{ number_format($cs['verification_pct'], 1) }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <p class="text-sm text-crm-t3">No closer data available for this period</p>
            </div>
        @endif
    @endif

    {{-- ════════════════════════════════════════════════════════════
         SECTION 4: ADMIN / VERIFICATION STATS
         ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'admins')
        @if(!empty($adminStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Admin</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Received</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Charged / Green</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Not Charged</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Charge %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Not Charged %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adminStats as $as)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $as['color'] }}">{{ $as['avatar'] }}</div>
                                        <span class="font-semibold">{{ $as['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $as['received'] }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $as['charged_green'] }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $as['not_charged'] }}</td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-bold {{ $as['charge_pct'] >= 70 ? 'text-emerald-600' : ($as['charge_pct'] >= 40 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($as['charge_pct'], 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($as['not_charged_pct'], 1) }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <p class="text-sm text-crm-t3">No admin/verification data available for this period</p>
            </div>
        @endif
    @endif
</div>
