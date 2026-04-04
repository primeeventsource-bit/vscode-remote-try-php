<div class="bg-crm-card border border-crm-border rounded-lg p-4 mt-4">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <div class="text-sm font-semibold">Chargeback Trends & Stats</div>
            <div class="text-[10px] text-crm-t3 mt-1">Live metrics · {{ $rangeLabel }}</div>
        </div>
        <a href="{{ route('chargebacks', $managementQuery) }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
            View All Chargebacks
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-3 mb-4">
        <select id="fld-period" wire:model.live.debounce.200ms="period" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="today">Today</option>
            <option value="last_7_days">Last 7 Days</option>
            <option value="this_month">This Month</option>
            <option value="last_3_months">Last 3 Months</option>
            <option value="last_6_months">Last 6 Months</option>
            <option value="last_12_months">Last 12 Months</option>
            <option value="custom">Custom</option>
        </select>

        <input id="fld-startDate" wire:model.live.debounce.500ms="startDate" type="date" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg" />
        <input id="fld-endDate" wire:model.live.debounce.500ms="endDate" type="date" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg" />

        <select id="fld-processorId" wire:model.live.debounce.200ms="processorId" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="">All Processors</option>
            @foreach($options['processors'] as $opt)
                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
            @endforeach
        </select>

        <select id="fld-salesRepId" wire:model.live.debounce.200ms="salesRepId" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="">All Reps</option>
            @foreach($options['sales_reps'] as $opt)
                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
            @endforeach
        </select>

        <select id="fld-merchantAccountId" wire:model.live.debounce.200ms="merchantAccountId" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="">All MIDs</option>
            @foreach($options['merchant_accounts'] as $opt)
                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
            @endforeach
        </select>

        <select id="fld-status" wire:model.live.debounce.200ms="status" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="">All Statuses</option>
            @foreach($options['statuses'] as $opt)
                <option value="{{ $opt }}">{{ ucfirst($opt) }}</option>
            @endforeach
        </select>

        <select id="fld-reasonCode" wire:model.live.debounce.200ms="reasonCode" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="">All Reason Codes</option>
            @foreach($options['reason_codes'] as $opt)
                <option value="{{ $opt }}">{{ $opt }}</option>
            @endforeach
        </select>

        <select id="fld-cardBrand" wire:model.live.debounce.200ms="cardBrand" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="">All Card Brands</option>
            @foreach($options['card_brands'] as $opt)
                <option value="{{ $opt }}">{{ $opt }}</option>
            @endforeach
        </select>

        <select id="fld-paymentMethod" wire:model.live.debounce.200ms="paymentMethod" class="px-3 py-2 text-xs bg-white border border-crm-border rounded-lg">
            <option value="">All Payment Methods</option>
            @foreach($options['payment_methods'] as $opt)
                <option value="{{ $opt }}">{{ $opt }}</option>
            @endforeach
        </select>

        <button wire:click="resetFilters" class="px-3 py-2 text-xs font-semibold rounded-lg border border-crm-border bg-white hover:bg-crm-hover transition">
            Reset
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <div class="border border-crm-border rounded-lg p-3 bg-white">
            <div class="text-[10px] uppercase tracking-wider text-crm-t3">Total Chargebacks</div>
            <div class="text-lg font-bold mt-1">{{ number_format($summary['total_chargebacks']['current'] ?? 0) }}</div>
            <div class="text-[10px] mt-1 {{ ($summary['total_chargebacks']['change_pct'] ?? 0) >= 0 ? 'text-red-600' : 'text-emerald-600' }}">
                {{ number_format($summary['total_chargebacks']['change_pct'] ?? 0, 1) }}% vs previous period
            </div>
        </div>

        <div class="border border-crm-border rounded-lg p-3 bg-white">
            <div class="text-[10px] uppercase tracking-wider text-crm-t3">Chargeback Amount</div>
            <div class="text-lg font-bold mt-1">${{ number_format($summary['total_chargeback_amount']['current'] ?? 0, 2) }}</div>
            <div class="text-[10px] mt-1 {{ ($summary['total_chargeback_amount']['change_pct'] ?? 0) >= 0 ? 'text-red-600' : 'text-emerald-600' }}">
                {{ number_format($summary['total_chargeback_amount']['change_pct'] ?? 0, 1) }}% vs previous period
            </div>
        </div>

        <div class="border border-crm-border rounded-lg p-3 bg-white">
            <div class="text-[10px] uppercase tracking-wider text-crm-t3">Chargeback Ratio</div>
            <div class="text-lg font-bold mt-1">{{ number_format($summary['chargeback_ratio_pct']['current'] ?? 0, 2) }}%</div>
            <div class="text-[10px] mt-1 text-crm-t3">Pending: {{ number_format($summary['pending_chargebacks']['current'] ?? 0) }}</div>
        </div>

        <div class="border border-crm-border rounded-lg p-3 bg-white">
            <div class="text-[10px] uppercase tracking-wider text-crm-t3">Representment Success</div>
            <div class="text-lg font-bold mt-1">{{ number_format($summary['representment_success_rate_pct']['current'] ?? 0, 1) }}%</div>
            <div class="text-[10px] mt-1 text-crm-t3">
                Won: {{ number_format($summary['won_chargebacks']['current'] ?? 0) }} · Lost: {{ number_format($summary['lost_chargebacks']['current'] ?? 0) }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 border border-crm-border rounded-lg p-3 bg-white">
            <div class="text-xs font-semibold mb-2">Daily Trend</div>
            @php
                $series = $trends['series'] ?? [];
                $maxAmount = collect($series)->max('amount') ?: 1;
            @endphp
            <div class="space-y-2 max-h-64 overflow-auto pr-1">
                @forelse($series as $point)
                    @php $pct = min(100, (($point['amount'] ?? 0) / $maxAmount) * 100); @endphp
                    <div>
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span>{{ \Carbon\Carbon::parse($point['date'])->format('M j') }}</span>
                            <span class="font-semibold">${{ number_format($point['amount'] ?? 0, 2) }} · {{ $point['count'] ?? 0 }} cases</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-2 rounded-full bg-red-500" @style(['width: ' . number_format($pct, 2) . '%'])></div>
                        </div>
                    </div>
                @empty
                    <div class="text-xs text-crm-t3">No trend data for selected filters.</div>
                @endforelse
            </div>
        </div>

        <div class="border border-crm-border rounded-lg p-3 bg-white">
            <div class="text-xs font-semibold mb-2">Top Breakdown</div>
            @php $reasons = $breakdowns['by_reason_code'] ?? []; @endphp
            <div class="space-y-2">
                @forelse($reasons as $item)
                    <div class="flex items-center justify-between text-[11px] border-b border-crm-border pb-1">
                        <span class="truncate pr-2">{{ $item['label'] }}</span>
                        <span class="font-semibold">{{ $item['count'] }} · ${{ number_format($item['amount'], 2) }}</span>
                    </div>
                @empty
                    <div class="text-xs text-crm-t3">No breakdown data for selected filters.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
