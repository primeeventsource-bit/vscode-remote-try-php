<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Payroll Batches</h2>
            <p class="text-xs text-crm-t3 mt-1">Weekly payroll batch management and employee payouts</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/payroll-v2', 'label' => 'Dashboard'],
            ['href' => '/payroll-v2/deals', 'label' => 'Deals'],
            ['href' => '/payroll-v2/batches', 'label' => 'Batches', 'active' => true],
            ['href' => '/payroll-v2/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- Flash Messages --}}
    @if(session()->has('payroll_success'))
        <div class="mb-4 px-4 py-3 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
            {{ session('payroll_success') }}
        </div>
    @endif
    @if(session()->has('payroll_error'))
        <div class="mb-4 px-4 py-3 rounded-lg text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
            {{ session('payroll_error') }}
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- BATCH LIST VIEW                                     --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    @if(($tab ?? 'list') === 'list')

        {{-- Build Batch Button --}}
        <div class="mb-4">
            <button wire:click="buildWeeklyBatch" class="px-4 py-2 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                Build Weekly Batch
            </button>
        </div>

        {{-- Batches Table --}}
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Batch Name</th>
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Period</th>
                            <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                            <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Gross</th>
                            <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Commissions</th>
                            <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Company Net</th>
                            <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches ?? [] as $batch)
                            <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                <td class="px-2 py-2 font-semibold">{{ $batch->batch_name }}</td>
                                <td class="px-2 py-2 text-crm-t3">{{ \Carbon\Carbon::parse($batch->period_start)->format('M d') }} &mdash; {{ \Carbon\Carbon::parse($batch->period_end)->format('M d, Y') }}</td>
                                <td class="px-2 py-2 text-center">
                                    @php $bs = $batch->batch_status ?? 'draft'; @endphp
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full
                                        @if($bs === 'paid') bg-emerald-100 text-emerald-700
                                        @elseif($bs === 'approved') bg-blue-100 text-blue-700
                                        @elseif($bs === 'locked') bg-purple-100 text-purple-700
                                        @elseif($bs === 'void') bg-red-100 text-red-700
                                        @else bg-gray-100 text-gray-700
                                        @endif
                                    ">{{ ucfirst($bs) }}</span>
                                </td>
                                <td class="px-2 py-2 text-right font-mono">${{ number_format($batch->total_gross ?? 0, 2) }}</td>
                                <td class="px-2 py-2 text-right font-mono">${{ number_format($batch->total_commissions ?? 0, 2) }}</td>
                                <td class="px-2 py-2 text-right font-mono font-bold {{ ($batch->total_company_net ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">${{ number_format($batch->total_company_net ?? 0, 2) }}</td>
                                <td class="px-2 py-2 text-center">
                                    <button wire:click="selectBatch({{ $batch->id }})" class="px-3 py-1 text-[10px] font-semibold bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                        View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-2 py-4 text-crm-t3 text-center">No batches found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- BATCH DETAIL VIEW                                   --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    @elseif(($tab ?? 'list') === 'detail' && $selectedBatch)

        {{-- Back Button --}}
        <div class="mb-4">
            <button wire:click="backToList" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Batches
            </button>
        </div>

        {{-- Batch Header --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">{{ $selectedBatch->batch_name }}</h3>
                    <p class="text-xs text-crm-t3 mt-0.5">{{ \Carbon\Carbon::parse($selectedBatch->period_start)->format('M d, Y') }} &mdash; {{ \Carbon\Carbon::parse($selectedBatch->period_end)->format('M d, Y') }}</p>
                </div>
                @php $bs = $selectedBatch->batch_status ?? 'draft'; @endphp
                <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full
                    @if($bs === 'paid') bg-emerald-100 text-emerald-700
                    @elseif($bs === 'approved') bg-blue-100 text-blue-700
                    @elseif($bs === 'locked') bg-purple-100 text-purple-700
                    @elseif($bs === 'void') bg-red-100 text-red-700
                    @else bg-gray-100 text-gray-700
                    @endif
                ">{{ ucfirst($bs) }}</span>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
            @foreach([
                ['label' => 'Total Gross', 'value' => $selectedBatch->total_gross ?? 0, 'color' => 'blue'],
                ['label' => 'Total Commissions', 'value' => $selectedBatch->total_commissions ?? 0, 'color' => 'orange'],
                ['label' => 'Total Processing', 'value' => $selectedBatch->total_processing ?? 0, 'color' => 'gray'],
                ['label' => 'Total Reserve', 'value' => $selectedBatch->total_reserve ?? 0, 'color' => 'purple'],
                ['label' => 'Total Marketing', 'value' => $selectedBatch->total_marketing ?? 0, 'color' => 'amber'],
                ['label' => 'Total Company Net', 'value' => $selectedBatch->total_company_net ?? 0, 'color' => ($selectedBatch->total_company_net ?? 0) >= 0 ? 'emerald' : 'red'],
            ] as $card)
                <div class="bg-crm-card border border-crm-border rounded-lg p-3 border-t-[3px] border-t-{{ $card['color'] }}-500">
                    <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">{{ $card['label'] }}</div>
                    <div class="text-lg font-extrabold text-{{ $card['color'] }}-600 mt-1 font-mono">${{ number_format($card['value'], 2) }}</div>
                </div>
            @endforeach
        </div>

        {{-- Detail Tabs --}}
        <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-4">
            @foreach(['summary' => 'Summary', 'deals' => 'Deals', 'employees' => 'Employees'] as $key => $label)
                <button wire:click="$set('batchTab', '{{ $key }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ ($batchTab ?? 'summary') === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Tab Content: Summary --}}
        @if(($batchTab ?? 'summary') === 'summary')
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-bold mb-3">Batch Summary</div>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-crm-t3">Batch Name:</span>
                        <span class="font-semibold ml-1">{{ $selectedBatch->batch_name }}</span>
                    </div>
                    <div>
                        <span class="text-crm-t3">Status:</span>
                        <span class="font-semibold ml-1">{{ ucfirst($selectedBatch->batch_status ?? 'draft') }}</span>
                    </div>
                    <div>
                        <span class="text-crm-t3">Period Start:</span>
                        <span class="font-semibold ml-1">{{ \Carbon\Carbon::parse($selectedBatch->period_start)->format('M d, Y') }}</span>
                    </div>
                    <div>
                        <span class="text-crm-t3">Period End:</span>
                        <span class="font-semibold ml-1">{{ \Carbon\Carbon::parse($selectedBatch->period_end)->format('M d, Y') }}</span>
                    </div>
                    <div>
                        <span class="text-crm-t3">Total Gross:</span>
                        <span class="font-semibold font-mono ml-1">${{ number_format($selectedBatch->total_gross ?? 0, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-crm-t3">Company Net:</span>
                        <span class="font-semibold font-mono ml-1 {{ ($selectedBatch->total_company_net ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">${{ number_format($selectedBatch->total_company_net ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Tab Content: Deals --}}
        @if(($batchTab ?? 'summary') === 'deals')
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-crm-border bg-crm-surface">
                                <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deal ID</th>
                                <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Client</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Gross</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fronter Comm</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closer Comm</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Admin Comm</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Company Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($batchDeals ?? [] as $bd)
                                <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                    <td class="px-2 py-2 font-semibold">#{{ $bd->deal_id }}</td>
                                    <td class="px-2 py-2">{{ $bd->owner_name ?? '--' }}</td>
                                    <td class="px-2 py-2 text-right font-mono">${{ number_format($bd->gross ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-right font-mono">${{ number_format($bd->fronter_comm ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-right font-mono">${{ number_format($bd->closer_comm ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-right font-mono">${{ number_format($bd->admin_comm ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-right font-mono font-bold {{ ($bd->net ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">${{ number_format($bd->net ?? 0, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-2 py-4 text-crm-t3 text-center">No deals in this batch.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Tab Content: Employees --}}
        @if(($batchTab ?? 'summary') === 'employees')
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-crm-border bg-crm-surface">
                                <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Employee</th>
                                <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Gross Volume</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deals</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Base Commission</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Hold Amount</th>
                                <th class="text-right px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Final Payout</th>
                                <th class="text-center px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($batchItems ?? [] as $item)
                                <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                    <td class="px-2 py-2 font-semibold">{{ $item->user->name ?? '--' }}</td>
                                    <td class="px-2 py-2 text-center">
                                        @php $rc = $item->role_code ?? 'other'; @endphp
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full
                                            @if($rc === 'closer') bg-blue-100 text-blue-700
                                            @elseif($rc === 'fronter') bg-amber-100 text-amber-700
                                            @elseif($rc === 'admin') bg-purple-100 text-purple-700
                                            @else bg-gray-100 text-gray-700
                                            @endif
                                        ">{{ ucfirst($rc) }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-right font-mono">${{ number_format($item->gross_volume ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-right font-mono">{{ number_format($item->deal_count ?? 0) }}</td>
                                    <td class="px-2 py-2 text-right font-mono">${{ number_format($item->base_commission ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-right font-mono text-amber-600">${{ number_format($item->hold_amount ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-right font-mono font-bold text-emerald-600">${{ number_format($item->final_payout ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-center">
                                        @php $pst = $item->payout_status ?? 'pending'; @endphp
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full
                                            @if($pst === 'paid') bg-emerald-100 text-emerald-700
                                            @elseif($pst === 'approved') bg-blue-100 text-blue-700
                                            @elseif($pst === 'void') bg-red-100 text-red-700
                                            @else bg-amber-100 text-amber-700
                                            @endif
                                        ">{{ ucfirst($pst) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-2 py-4 text-crm-t3 text-center">No employee payouts in this batch.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ═══ BATCH ACTION BUTTONS (Master Only) ═══ --}}
        @if($isMaster ?? false)
            <div class="mt-5 flex flex-wrap gap-2">
                @if(in_array($selectedBatch->batch_status ?? 'draft', ['draft', 'calculated']))
                    <button wire:click="approveBatch" class="px-4 py-2 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Approve Batch
                    </button>
                @endif
                @if(in_array($selectedBatch->batch_status ?? 'draft', ['draft', 'calculated', 'approved']))
                    <button wire:click="lockBatch" class="px-4 py-2 text-xs font-semibold bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                        Lock Batch
                    </button>
                @endif
                @if(in_array($selectedBatch->batch_status ?? 'draft', ['approved', 'locked']))
                    <button wire:click="markBatchPaid" class="px-4 py-2 text-xs font-semibold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                        Mark Paid
                    </button>
                @endif
            </div>
        @endif

    @endif
</div>
