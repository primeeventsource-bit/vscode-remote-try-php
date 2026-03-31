<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Tracker</h2>
            <p class="text-xs text-crm-t3 mt-1">Weekly deal tracker</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="prevWeek" class="px-3 py-1.5 text-xs font-semibold bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">&larr; Prev</button>
            <button wire:click="thisWeek" class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">This Week</button>
            <button wire:click="nextWeek" class="px-3 py-1.5 text-xs font-semibold bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Next &rarr;</button>
        </div>
    </div>

    <div class="text-sm font-semibold text-center mb-3 text-crm-t2">{{ $weekLabel }}</div>

    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold min-w-[120px]">Agent</th>
                        @foreach($days as $day)
                            <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold min-w-[90px]">
                                {{ is_object($day) ? $day->format('D n/j') : ($day['label'] ?? $day) }}
                            </th>
                        @endforeach
                        <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold bg-blue-50/50 min-w-[80px]">Week Total</th>
                        <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold bg-gray-50 min-w-[80px]">Prev Week</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Fronters Section --}}
                    @if(count($fronters))
                        <tr class="bg-pink-50/50">
                            <td colspan="{{ count($days) + 3 }}" class="px-3 py-1.5 text-[10px] uppercase tracking-wider font-bold text-pink-600">Fronters</td>
                        </tr>
                        @foreach($fronters as $fronter)
                            @php
                                $userData = $userDayData[$fronter->id] ?? [];
                                $weekTotal = $weekTotals[$fronter->id] ?? ['revenue' => 0, 'count' => 0];
                                $prevTotal = $prevTotals[$fronter->id] ?? ['revenue' => 0, 'count' => 0];
                            @endphp
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold text-white flex-shrink-0" style="background: {{ $fronter->color ?? '#ec4899' }}">{{ $fronter->avatar ?? substr($fronter->name, 0, 2) }}</div>
                                        <span class="text-xs font-semibold truncate">{{ $fronter->name }}</span>
                                    </div>
                                </td>
                                @foreach($days as $idx => $day)
                                    @php
                                        $dayKey = is_array($day) ? ($day['key'] ?? $idx) : $idx;
                                        $cell = $userData[$dayKey] ?? null;
                                    @endphp
                                    <td class="px-2 py-2 text-center">
                                        @if($cell && ($cell['rev'] ?? 0) > 0)
                                            <div class="text-xs font-bold font-mono text-emerald-500">${{ number_format($cell['rev']) }}</div>
                                            <div class="text-[9px] text-crm-t3">{{ $cell['count'] ?? 0 }} deal{{ ($cell['count'] ?? 0) !== 1 ? 's' : '' }}</div>
                                            @if(isset($cell['initials']) && count($cell['initials']))
                                                <div class="text-[8px] text-crm-t3 font-mono">{{ implode(', ', $cell['initials']) }}</div>
                                            @endif
                                        @else
                                            <span class="text-[10px] text-crm-t3">--</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-2 py-2 text-center bg-blue-50/30">
                                    <div class="text-xs font-bold font-mono {{ $weekTotal['rev'] > 0 ? 'text-blue-600' : 'text-crm-t3' }}">${{ number_format($weekTotal['rev']) }}</div>
                                    <div class="text-[9px] text-crm-t3">{{ $weekTotal['count'] }} deals</div>
                                </td>
                                <td class="px-2 py-2 text-center bg-gray-50/30">
                                    <div class="text-xs font-mono text-crm-t2">${{ number_format($prevTotal['rev']) }}</div>
                                    <div class="text-[9px] text-crm-t3">{{ $prevTotal['count'] }}</div>
                                </td>
                            </tr>
                        @endforeach
                    @endif

                    {{-- Closers Section --}}
                    @if(count($closers))
                        <tr class="bg-purple-50/50">
                            <td colspan="{{ count($days) + 3 }}" class="px-3 py-1.5 text-[10px] uppercase tracking-wider font-bold text-purple-600">Closers</td>
                        </tr>
                        @foreach($closers as $closer)
                            @php
                                $userData = $userDayData[$closer->id] ?? [];
                                $weekTotal = $weekTotals[$closer->id] ?? ['revenue' => 0, 'count' => 0];
                                $prevTotal = $prevTotals[$closer->id] ?? ['revenue' => 0, 'count' => 0];
                            @endphp
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold text-white flex-shrink-0" style="background: {{ $closer->color ?? '#8b5cf6' }}">{{ $closer->avatar ?? substr($closer->name, 0, 2) }}</div>
                                        <span class="text-xs font-semibold truncate">{{ $closer->name }}</span>
                                    </div>
                                </td>
                                @foreach($days as $idx => $day)
                                    @php
                                        $dayKey = is_array($day) ? ($day['key'] ?? $idx) : $idx;
                                        $cell = $userData[$dayKey] ?? null;
                                    @endphp
                                    <td class="px-2 py-2 text-center">
                                        @if($cell && ($cell['rev'] ?? 0) > 0)
                                            <div class="text-xs font-bold font-mono text-emerald-500">${{ number_format($cell['rev']) }}</div>
                                            <div class="text-[9px] text-crm-t3">{{ $cell['count'] ?? 0 }} deal{{ ($cell['count'] ?? 0) !== 1 ? 's' : '' }}</div>
                                            @if(isset($cell['initials']) && count($cell['initials']))
                                                <div class="text-[8px] text-crm-t3 font-mono">{{ implode(', ', $cell['initials']) }}</div>
                                            @endif
                                        @else
                                            <span class="text-[10px] text-crm-t3">--</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-2 py-2 text-center bg-blue-50/30">
                                    <div class="text-xs font-bold font-mono {{ $weekTotal['rev'] > 0 ? 'text-blue-600' : 'text-crm-t3' }}">${{ number_format($weekTotal['rev']) }}</div>
                                    <div class="text-[9px] text-crm-t3">{{ $weekTotal['count'] }} deals</div>
                                </td>
                                <td class="px-2 py-2 text-center bg-gray-50/30">
                                    <div class="text-xs font-mono text-crm-t2">${{ number_format($prevTotal['rev']) }}</div>
                                    <div class="text-[9px] text-crm-t3">{{ $prevTotal['count'] }}</div>
                                </td>
                            </tr>
                        @endforeach
                    @endif

                    {{-- Daily Totals --}}
                    <tr class="border-t-2 border-crm-border bg-crm-surface font-bold">
                        <td class="px-3 py-2.5 text-xs uppercase tracking-wider text-crm-t2">Daily Total</td>
                        @php $grandRevenue = 0; $grandCount = 0; @endphp
                        @foreach($days as $idx => $day)
                            @php
                                $dayKey = is_array($day) ? ($day['key'] ?? $idx) : $idx;
                                $dt = $dayTotals[$dayKey] ?? ['rev' => 0, 'count' => 0];
                                $grandRevenue += $dt['rev'];
                                $grandCount += $dt['count'];
                            @endphp
                            <td class="px-2 py-2.5 text-center">
                                <div class="text-xs font-bold font-mono text-crm-t1">${{ number_format($dt['rev']) }}</div>
                                <div class="text-[9px] text-crm-t3">{{ $dt['count'] }} deals</div>
                            </td>
                        @endforeach
                        <td class="px-2 py-2.5 text-center bg-blue-50/50">
                            <div class="text-sm font-extrabold font-mono text-blue-600">${{ number_format($grandRevenue) }}</div>
                            <div class="text-[9px] text-crm-t3">{{ $grandCount }} deals</div>
                        </td>
                        <td class="px-2 py-2.5 text-center bg-gray-50"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
