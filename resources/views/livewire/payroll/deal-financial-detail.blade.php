<div class="p-5">
    {{-- Back Link --}}
    <a href="/payroll-v2/deals" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline mb-4">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Deals
    </a>

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

    {{-- Header --}}
    <div class="mb-5">
        <h2 class="text-xl font-bold">Deal #{{ $deal->id }} &mdash; {{ $deal->owner_name }}</h2>
        <p class="text-xs text-crm-t3 mt-1">Financial detail and commission breakdown</p>
    </div>

    {{-- ═══ ASSIGNED USERS ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
        <div class="text-sm font-bold mb-3">Assigned Users</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Fronter</div>
                <div class="text-sm font-semibold">{{ $users[$deal->fronter_user_id ?? $deal->fronter]->name ?? '--' }}</div>
            </div>
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Closer</div>
                <div class="text-sm font-semibold">{{ $users[$deal->closer_user_id_payroll ?? $deal->closer]->name ?? '--' }}</div>
            </div>
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Admin</div>
                <div class="text-sm font-semibold">{{ $users[$deal->admin_user_id_payroll ?? $deal->assigned_admin]->name ?? '--' }}</div>
            </div>
        </div>
    </div>

    {{-- ═══ AMOUNT SNAPSHOT ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
        <div class="text-sm font-bold mb-3">Amount Snapshot</div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Gross Amount</div>
                <div class="text-lg font-extrabold font-mono text-blue-600">${{ number_format($financial->gross_amount, 2) }}</div>
            </div>
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Collected Amount</div>
                <div class="text-lg font-extrabold font-mono">${{ number_format($financial->collected_amount ?? 0, 2) }}</div>
            </div>
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Refunded Amount</div>
                <div class="text-lg font-extrabold font-mono text-red-600">${{ number_format($financial->refunded_amount ?? 0, 2) }}</div>
            </div>
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Chargeback Amount</div>
                <div class="text-lg font-extrabold font-mono text-red-600">${{ number_format($financial->chargeback_amount ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- ═══ COMMISSION RATES ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
        <div class="text-sm font-bold mb-3">Commission Rates</div>
        <div class="flex flex-wrap gap-4">
            @foreach([
                'Fronter %' => $financial->fronter_percent ?? 0,
                'Closer %' => $financial->closer_percent ?? 0,
                'Admin %' => $financial->admin_percent ?? 0,
                'Processing %' => $financial->processing_percent ?? 0,
                'Reserve %' => $financial->reserve_percent ?? 0,
                'Marketing %' => $financial->marketing_percent ?? 0,
            ] as $label => $val)
                <div class="bg-crm-surface border border-crm-border rounded-lg px-3 py-2 text-center">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">{{ $label }}</div>
                    <div class="text-sm font-extrabold font-mono mt-0.5">{{ number_format($val, 2) }}%</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ═══ CALCULATED VALUES ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
        <div class="text-sm font-bold mb-3">Calculated Values</div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach([
                'Fronter Commission' => $financial->fronter_commission,
                'Closer Commission' => $financial->closer_commission,
                'Admin Commission' => $financial->admin_commission,
                'Processing Fee' => $financial->processing_fee,
                'Reserve Fee' => $financial->reserve_fee,
                'Marketing Cost' => $financial->marketing_cost,
            ] as $label => $val)
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">{{ $label }}</div>
                    <div class="text-sm font-bold font-mono">${{ number_format($val, 2) }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ═══ COMPANY RESULT ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
        <div class="text-sm font-bold mb-3">Company Result</div>
        <div class="flex items-center gap-6">
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Company Net</div>
                <div class="text-3xl font-extrabold font-mono {{ $financial->company_net >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    ${{ number_format($financial->company_net, 2) }}
                </div>
            </div>
            <div>
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Company Net %</div>
                <div class="text-2xl font-extrabold font-mono {{ $financial->company_net >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ number_format($financial->company_net_percent, 1) }}%
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ STATUS & FLAGS ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
        <div class="text-sm font-bold mb-3">Status & Flags</div>
        <div class="flex flex-wrap items-center gap-3">
            {{-- Payroll Status --}}
            @php $ps = $deal->payroll_status ?? 'pending'; @endphp
            <div class="flex items-center gap-1.5">
                <span class="text-[10px] text-crm-t3 font-semibold">Payroll:</span>
                <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full
                    @if($ps === 'paid') bg-emerald-100 text-emerald-700
                    @elseif($ps === 'approved') bg-blue-100 text-blue-700
                    @elseif($ps === 'calculated') bg-blue-100 text-blue-700
                    @elseif($ps === 'disputed') bg-red-100 text-red-700
                    @elseif($ps === 'void') bg-red-100 text-red-700
                    @else bg-amber-100 text-amber-700
                    @endif
                ">{{ ucfirst($ps) }}</span>
            </div>

            {{-- Commission Status --}}
            @php $cs = $financial->commission_status ?? 'pending'; @endphp
            <div class="flex items-center gap-1.5">
                <span class="text-[10px] text-crm-t3 font-semibold">Commission:</span>
                <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full
                    @if($cs === 'paid') bg-emerald-100 text-emerald-700
                    @elseif($cs === 'approved') bg-blue-100 text-blue-700
                    @elseif($cs === 'calculated') bg-blue-100 text-blue-700
                    @elseif($cs === 'disputed') bg-red-100 text-red-700
                    @else bg-amber-100 text-amber-700
                    @endif
                ">{{ ucfirst($cs) }}</span>
            </div>

            {{-- Flags --}}
            @if($financial->is_locked)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 text-purple-700">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    Locked
                </span>
            @endif
            @if($financial->is_disputed)
                <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full bg-red-100 text-red-700">Disputed</span>
            @endif
            @if($financial->is_reversed)
                <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full bg-red-100 text-red-700">Reversed</span>
            @endif
        </div>
    </div>

    {{-- ═══ ACTIONS (Admin Only) ═══ --}}
    @if($isAdmin ?? false)
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
            <div class="text-sm font-bold mb-3">Actions</div>
            <div class="flex flex-wrap gap-2">
                <button wire:click="recalculate" class="px-4 py-2 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Recalculate
                </button>
                <button wire:click="toggleLock" class="px-4 py-2 text-xs font-semibold bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    {{ $financial->is_locked ? 'Unlock' : 'Lock' }}
                </button>
                <button wire:click="markDisputed" class="px-4 py-2 text-xs font-semibold bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    Mark Disputed
                </button>
            </div>
        </div>
    @endif

    {{-- ═══ MANUAL ADJUSTMENT (Master/Admin) ═══ --}}
    @if(($isMaster ?? false) || ($isAdmin ?? false))
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
            <div class="text-sm font-bold mb-3">Manual Adjustment</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div>
                    <label for="fld-adjustmentAmount" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Amount ($)</label>
                    <input id="fld-adjustmentAmount" wire:model="adjustmentAmount" type="number" step="0.01"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400"
                        placeholder="0.00">
                    @error('adjustmentAmount')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="fld-adjustmentReason" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Reason</label>
                    <input id="fld-adjustmentReason" wire:model="adjustmentReason" type="text"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400"
                        placeholder="Reason for adjustment">
                    @error('adjustmentReason')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <button wire:click="addAdjustment" class="w-full px-4 py-2 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Add Adjustment
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ REVERSE DEAL (Master Only) ═══ --}}
    @if($isMaster ?? false)
        <div class="bg-crm-card border border-red-200 rounded-lg p-4 mb-5">
            <div class="text-sm font-bold mb-1 text-red-700">Reverse Deal</div>
            <div class="text-[10px] text-crm-t3 mb-3">This will reverse all commissions and mark the deal as reversed. This action cannot be undone.</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div class="md:col-span-2">
                    <label for="fld-reverseReason" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Reason</label>
                    <input id="fld-reverseReason" wire:model="reverseReason" type="text"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-red-400"
                        placeholder="Reason for reversal">
                    @error('reverseReason')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <button wire:click="reverseDeal"
                        wire:confirm="Are you sure you want to reverse this deal? This action cannot be undone."
                        class="w-full px-4 py-2 text-xs font-semibold bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Reverse Deal
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ AUDIT HISTORY ═══ --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4">
        <div class="text-sm font-bold mb-3">Audit History</div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border">
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Action</th>
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Note</th>
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">User</th>
                        <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($audits ?? [] as $audit)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-2 py-2 font-semibold">{{ $audit->action }}</td>
                            <td class="px-2 py-2 text-crm-t3">{{ $audit->note ?? '--' }}</td>
                            <td class="px-2 py-2">{{ $users[$audit->user_id]->name ?? 'System' }}</td>
                            <td class="px-2 py-2 text-crm-t3">{{ \Carbon\Carbon::parse($audit->created_at)->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-2 py-4 text-crm-t3 text-center">No audit history.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
