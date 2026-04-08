<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Finance Command Center</h2>
            <p class="text-xs text-crm-t3 mt-1">Executive financial overview across all merchant accounts</p>
        </div>
        <div class="flex items-center gap-3">
            <select id="fld-fin-mid" wire:model.live="midFilter" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                <option value="all">All MIDs</option>
                @foreach($mids as $mid)
                    <option value="{{ $mid->id }}">{{ $mid->account_name }} ({{ $mid->mid_number }})</option>
                @endforeach
            </select>
            <select id="fld-fin-range" wire:model.live="dateRange" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                <option value="today">Today</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
                <option value="month">This Month</option>
                <option value="quarter">Last Quarter</option>
                <option value="year">This Year</option>
                <option value="all">All Time</option>
            </select>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/finance', 'label' => 'Dashboard', 'active' => true],
            ['href' => '/finance/accounts', 'label' => 'Merchant Accounts'],
            ['href' => '/finance/statements', 'label' => 'Statements'],
            ['href' => '/finance/transactions', 'label' => 'Transactions'],
            ['href' => '/finance/chargebacks', 'label' => 'Chargebacks'],
            ['href' => '/finance/entries', 'label' => 'Financial Entries'],
            ['href' => '/finance/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- ═══ ROW 1 — EXECUTIVE SUMMARY CARDS ═══ --}}
    @if(!empty($summaryCards))
    <div class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-3 mb-6">
        @foreach($summaryCards as $card)
            <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-{{ $card['color'] }}-500">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">{{ $card['label'] }}</div>
                <div class="text-lg font-extrabold text-{{ $card['color'] }}-500 mt-1">
                    @if($card['format'] === 'currency')
                        ${{ number_format($card['value'], 0) }}
                    @else
                        {{ number_format($card['value']) }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ═══ ROW 2 — PROFITABILITY FORMULA ═══ --}}
    @if(!empty($profitability['overall'] ?? []))
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-6">
        <div class="text-sm font-bold mb-3">Profitability Summary</div>
        @php $o = $profitability['overall']; @endphp
        <div class="flex flex-wrap items-center gap-2 text-sm font-mono">
            <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded font-bold">${{ number_format($o['gross_volume'], 0) }}</span>
            <span class="text-crm-t3">Gross</span>
            <span class="text-red-500 font-bold">- ${{ number_format($o['refunds'], 0) }}</span>
            <span class="text-crm-t3">Refunds</span>
            <span class="text-red-500 font-bold">- ${{ number_format($o['chargebacks'], 0) }}</span>
            <span class="text-crm-t3">CB</span>
            <span class="text-orange-500 font-bold">- ${{ number_format($o['fees'], 0) }}</span>
            <span class="text-crm-t3">Fees</span>
            <span class="text-purple-500 font-bold">- ${{ number_format($o['reserve_holds'], 0) }}</span>
            <span class="text-crm-t3">Reserves</span>
            <span class="text-emerald-500 font-bold">+ ${{ number_format($o['reserve_releases'], 0) }}</span>
            <span class="text-crm-t3">Released</span>
            <span class="font-bold">=</span>
            <span class="px-3 py-1 rounded font-extrabold text-lg {{ $o['net_result'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                ${{ number_format($o['net_result'], 0) }}
            </span>
            <span class="text-crm-t3">Net Result</span>
        </div>
    </div>
    @endif

    {{-- ═══ ROW 3 — MID BREAKDOWN TABLE ═══ --}}
    @if(!empty($midBreakdown))
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-6">
        <div class="p-4 border-b border-crm-border">
            <div class="text-sm font-bold">MID Breakdown</div>
            <div class="text-[10px] text-crm-t3">Performance by merchant account</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">MID</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Account</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Processor</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Txns</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Approved Vol</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Refunds</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">CB</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Fees</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Reserves</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Payouts</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Net Result</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">CB Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($midBreakdown as $row)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-3 py-2 font-mono text-[10px]">{{ $row['mid_number'] }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $row['account_name'] }}</td>
                            <td class="px-3 py-2 text-crm-t3">{{ $row['processor'] }}</td>
                            <td class="text-center px-2 py-2 font-mono">{{ number_format($row['transaction_count']) }}</td>
                            <td class="text-right px-2 py-2 font-mono font-bold text-blue-600">${{ number_format($row['gross_volume'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono text-amber-600">${{ number_format($row['refunds'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono text-red-500">${{ number_format($row['chargebacks'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono text-orange-500">${{ number_format($row['fees'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono text-purple-500">${{ number_format($row['reserve_holds'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono text-green-600">${{ number_format($row['payouts'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono font-bold {{ ($row['net_result'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">${{ number_format($row['net_result'], 0) }}</td>
                            <td class="text-center px-2 py-2">
                                @php $cbr = $row['chargeback_rate'] ?? 0; @endphp
                                <span class="font-bold {{ $cbr >= 1.5 ? 'text-red-600' : ($cbr >= 0.75 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $cbr }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ═══ ROW 4 — CHARGEBACK COMMAND CENTER ═══ --}}
    @if(!empty($chargebackSummary))
    <div class="mb-6">
        <div class="text-sm font-bold mb-3">Chargeback Command Center</div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-red-500">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider">Open</div>
                <div class="text-lg font-extrabold text-red-500">{{ $chargebackSummary['open'] ?? 0 }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-amber-500">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider">Due Soon</div>
                <div class="text-lg font-extrabold text-amber-500">{{ $chargebackSummary['due_soon'] ?? 0 }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-emerald-500">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider">Won</div>
                <div class="text-lg font-extrabold text-emerald-500">{{ $chargebackSummary['won'] ?? 0 }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-red-400">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider">Lost</div>
                <div class="text-lg font-extrabold text-red-400">{{ $chargebackSummary['lost'] ?? 0 }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-l-[3px] border-l-purple-500">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider">Total Value</div>
                <div class="text-lg font-extrabold text-purple-500">${{ number_format($chargebackSummary['total_value'] ?? 0, 0) }}</div>
            </div>
        </div>

        {{-- Reason Code Trends --}}
        @if(!empty($chargebackSummary['reason_codes']))
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-xs font-bold mb-2">Reason Code Trends</div>
                @foreach($chargebackSummary['reason_codes'] as $rc)
                    <div class="flex items-center justify-between py-1 border-b border-crm-border last:border-0">
                        <span class="text-xs font-mono">{{ $rc['reason_code'] ?? '--' }}</span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-crm-t3">{{ $rc['cnt'] }} cases</span>
                            <span class="text-xs font-bold text-red-500">${{ number_format($rc['total'], 0) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- CB Value by MID --}}
            @if(!empty($chargebackSummary['value_by_mid']))
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-xs font-bold mb-2">Chargeback Value by MID</div>
                @foreach($chargebackSummary['value_by_mid'] as $cbm)
                    <div class="flex items-center justify-between py-1 border-b border-crm-border last:border-0">
                        <span class="text-xs">{{ $cbm['mid_name'] }}</span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-crm-t3">{{ $cbm['count'] }}</span>
                            <span class="text-xs font-bold text-red-500">${{ number_format($cbm['total'], 0) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ ROW 6 — FINANCIAL BURDEN / RISK ═══ --}}
    @if(!empty($financialBurden))
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-6">
        <div class="p-4 border-b border-crm-border">
            <div class="text-sm font-bold">Financial Burden / Risk by MID</div>
            <div class="text-[10px] text-crm-t3">Fees, reserves, and chargebacks weighted by account</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Account</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Processor</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Fees</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Reserves</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">CB</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">CB Rate</th>
                        <th class="text-right px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Total Burden</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Risk</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($financialBurden as $fb)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-3 py-2 font-semibold">{{ $fb['account_name'] }}</td>
                            <td class="px-3 py-2 text-crm-t3">{{ $fb['processor'] }}</td>
                            <td class="text-right px-2 py-2 font-mono text-orange-500">${{ number_format($fb['fees'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono text-purple-500">${{ number_format($fb['reserves'], 0) }}</td>
                            <td class="text-right px-2 py-2 font-mono text-red-500">${{ number_format($fb['chargebacks'], 0) }}</td>
                            <td class="text-center px-2 py-2 font-bold {{ $fb['chargeback_rate'] >= 1.5 ? 'text-red-600' : ($fb['chargeback_rate'] >= 0.75 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $fb['chargeback_rate'] }}%</td>
                            <td class="text-right px-2 py-2 font-mono font-bold text-red-600">${{ number_format($fb['total_burden'], 0) }}</td>
                            <td class="text-center px-2 py-2">
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $fb['risk_level'] === 'high' ? 'bg-red-100 text-red-700' : ($fb['risk_level'] === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">{{ ucfirst($fb['risk_level']) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ═══ ROW 7 — STATEMENT IMPORT HEALTH ═══ --}}
    @if(!empty($importHealth['recent'] ?? []))
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-6">
        <div class="flex items-center justify-between p-4 border-b border-crm-border">
            <div>
                <div class="text-sm font-bold">Statement Import Health</div>
                <div class="text-[10px] text-crm-t3">{{ $importHealth['pending_count'] ?? 0 }} pending &middot; {{ $importHealth['review_queue_count'] ?? 0 }} items in review queue</div>
            </div>
            <a href="/finance/statements" class="text-xs text-blue-500 hover:underline">Upload Statement</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">File</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">MID</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Period</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Confidence</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Imported</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Failed</th>
                        <th class="text-center px-2 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Review</th>
                        <th class="text-left px-3 py-2 text-[9px] uppercase tracking-wider text-crm-t3 font-semibold">Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($importHealth['recent'] as $ih)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-3 py-2 font-semibold truncate max-w-[150px]">{{ $ih['filename'] }}</td>
                            <td class="px-3 py-2 text-crm-t3">{{ $ih['mid_name'] }}</td>
                            <td class="px-3 py-2 font-mono text-[10px]">{{ $ih['period'] }}</td>
                            <td class="text-center px-2 py-2">
                                @php
                                    $sc = match($ih['status']) {
                                        'imported' => 'bg-emerald-100 text-emerald-700',
                                        'parsed' => 'bg-blue-100 text-blue-700',
                                        'processing' => 'bg-amber-100 text-amber-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $sc }}">{{ ucfirst($ih['status']) }}</span>
                            </td>
                            <td class="text-center px-2 py-2">
                                @if($ih['confidence'])
                                    <span class="font-bold {{ $ih['confidence'] >= 0.8 ? 'text-emerald-600' : ($ih['confidence'] >= 0.6 ? 'text-amber-600' : 'text-red-500') }}">{{ round($ih['confidence'] * 100) }}%</span>
                                @else
                                    <span class="text-crm-t3">--</span>
                                @endif
                            </td>
                            <td class="text-center px-2 py-2 font-mono text-emerald-600">{{ $ih['imported_rows'] }}</td>
                            <td class="text-center px-2 py-2 font-mono text-red-500">{{ $ih['failed_rows'] }}</td>
                            <td class="text-center px-2 py-2 font-mono {{ $ih['review_count'] > 0 ? 'text-amber-600 font-bold' : 'text-crm-t3' }}">{{ $ih['review_count'] }}</td>
                            <td class="px-3 py-2 text-crm-t3 text-[10px]">{{ $ih['uploaded_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
