<div class="p-5">
    {{-- Page Title --}}
    <div class="mb-5">
        <h2 class="text-xl font-bold">Statistics</h2>
        <p class="text-xs text-crm-t3 mt-1">Pipeline performance — fronters, closers, and verification</p>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         FILTER BAR
         ════════════════════════════════════════════════════════════ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-3 mb-5">
        <div class="flex flex-wrap items-center gap-4">
            {{-- View By --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">View By</span>
                <select id="fld-stats-range" wire:model.live="statsRange" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="live">Live</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>

            {{-- Fronter Agent --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Fronter Agent</span>
                <select id="fld-stats-fronter" wire:model.live="selectedFronterId" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Fronters</option>
                    @foreach($fronterUsers as $fu)
                        <option value="{{ $fu->id }}">{{ $fu->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Closer Agent --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Closer Agent</span>
                <select id="fld-stats-closer" wire:model.live="selectedCloserId" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Closers</option>
                    @foreach($closerUsers as $cu)
                        <option value="{{ $cu->id }}">{{ $cu->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Admin Agent --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Admin Agent</span>
                <select id="fld-stats-admin" wire:model.live="selectedAdminId" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="all">All Admins</option>
                    @foreach($adminUsers as $au)
                        <option value="{{ $au->id }}">{{ $au->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SUMMARY CARDS
         ════════════════════════════════════════════════════════════ --}}
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

    {{-- ════════════════════════════════════════════════════════════
         FRONTER PERFORMANCE
         ════════════════════════════════════════════════════════════ --}}
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Fronter Performance</div>
        @if(!empty($fronterStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Transfers Sent</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No Deals</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No-Deal %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fronterStats as $fs)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $fs['color'] ?? '#6b7280' }}">{{ $fs['avatar'] ?? '--' }}</div>
                                        <span class="font-semibold">{{ $fs['name'] ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $fs['transfers_sent'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $fs['deals_closed'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $fs['no_deals'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5">
                                    @php $fClosePct = $fs['close_pct'] ?? 0; @endphp
                                    <span class="font-bold {{ $fClosePct >= 50 ? 'text-emerald-600' : ($fClosePct >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($fClosePct, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($fs['no_deal_pct'] ?? 0, 1) }}%</span>
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
    </div>

    {{-- ════════════════════════════════════════════════════════════
         CLOSER PERFORMANCE
         ════════════════════════════════════════════════════════════ --}}
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Closer Performance</div>
        @if(!empty($closerStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Transfers Received</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Sent to Verification</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Not Closed</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">No-Close %</th>
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Verification %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($closerStats as $cs)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $cs['color'] ?? '#6b7280' }}">{{ $cs['avatar'] ?? '--' }}</div>
                                        <span class="font-semibold">{{ $cs['name'] ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $cs['transfers_received'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $cs['deals_closed'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-amber-600">{{ $cs['sent_to_verification'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $cs['not_closed'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5">
                                    @php $cClosePct = $cs['close_pct'] ?? 0; @endphp
                                    <span class="font-bold {{ $cClosePct >= 50 ? 'text-emerald-600' : ($cClosePct >= 25 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($cClosePct, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($cs['no_close_pct'] ?? 0, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-bold text-purple-600">{{ number_format($cs['verification_pct'] ?? 0, 1) }}%</span>
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
    </div>

    {{-- ════════════════════════════════════════════════════════════
         VERIFICATION / ADMIN PERFORMANCE
         ════════════════════════════════════════════════════════════ --}}
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Verification / Admin Performance</div>
        @if(!empty($adminStats))
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
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
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $as['color'] ?? '#6b7280' }}">{{ $as['avatar'] ?? '--' }}</div>
                                        <span class="font-semibold">{{ $as['name'] ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold">{{ $as['received'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono font-bold text-emerald-600">{{ $as['charged_green'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5 font-mono text-red-500">{{ $as['not_charged'] ?? 0 }}</td>
                                <td class="text-center px-3 py-2.5">
                                    @php $aChargePct = $as['charge_pct'] ?? 0; @endphp
                                    <span class="font-bold {{ $aChargePct >= 70 ? 'text-emerald-600' : ($aChargePct >= 40 ? 'text-amber-600' : 'text-red-500') }}">{{ number_format($aChargePct, 1) }}%</span>
                                </td>
                                <td class="text-center px-3 py-2.5">
                                    <span class="font-semibold text-crm-t3">{{ number_format($as['not_charged_pct'] ?? 0, 1) }}%</span>
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
    </div>
</div>
