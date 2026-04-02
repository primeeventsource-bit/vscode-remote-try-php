<div class="p-5" x-data="{ ratesOpen: false }">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Payroll</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ $weekLabel }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="prevWeek" class="px-3 py-1.5 text-xs font-semibold bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">&larr; Prev</button>
            <button wire:click="thisWeek" class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">This Week</button>
            <button wire:click="nextWeek" class="px-3 py-1.5 text-xs font-semibold bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Next &rarr;</button>
        </div>
    </div>

    @if($isMaster)
        {{-- Tabs for Master Admin --}}
        <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-4">
            @foreach(['inputs' => 'Payroll Inputs', 'closers' => 'Closers', 'fronters' => 'Fronters', 'admins' => 'Admins', 'history' => 'Sent History'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($tab === 'inputs')
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-700">
            Payroll rates and rules were moved to the Settings page under <span class="font-semibold">Commission Rates & Payroll Rules</span>.
            <a href="{{ route('settings') }}" class="ml-2 font-semibold underline">Open Settings</a>
        </div>

        {{-- Per-User Payroll Inputs --}}
        <div class="mb-4 bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="flex items-center justify-between gap-2 mb-1">
                <div class="text-sm font-semibold">User Payroll Inputs</div>
                <button wire:click="saveAllUserPayrollInfo" class="px-3 py-1 text-[10px] font-semibold bg-blue-600 text-white rounded hover:bg-blue-700 transition">Save All</button>
            </div>
            <div class="text-[10px] text-crm-t3 mb-3">Master admin can set commission %, SNR %, and hourly rate per user.</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-crm-border">
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">User</th>
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Commission %</th>
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">SNR %</th>
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Hourly Rate</th>
                            <th class="text-left px-2 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($editableUsers as $eu)
                            <tr class="border-b border-crm-border">
                                <td class="px-2 py-2 font-semibold">{{ $eu->name }}</td>
                                <td class="px-2 py-2 text-crm-t2 capitalize">{{ str_replace('_', ' ', $eu->role) }}</td>
                                <td class="px-2 py-2">
                                    <input id="fld-userPayrollInputs-{{ $eu->id }}-comm_pct" wire:model.defer="userPayrollInputs.{{ $eu->id }}.comm_pct" type="number" step="0.01" class="w-24 px-2 py-1 border border-crm-border rounded bg-white font-mono">
                                    @error("userPayrollInputs.$eu->id.comm_pct")<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input id="fld-userPayrollInputs-{{ $eu->id }}-snr_pct" wire:model.defer="userPayrollInputs.{{ $eu->id }}.snr_pct" type="number" step="0.01" class="w-24 px-2 py-1 border border-crm-border rounded bg-white font-mono">
                                    @error("userPayrollInputs.$eu->id.snr_pct")<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input id="fld-userPayrollInputs-{{ $eu->id }}-hourly_rate" wire:model.defer="userPayrollInputs.{{ $eu->id }}.hourly_rate" type="number" step="0.01" class="w-24 px-2 py-1 border border-crm-border rounded bg-white font-mono">
                                    @error("userPayrollInputs.$eu->id.hourly_rate")<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                                </td>
                                <td class="px-2 py-2">
                                    <button wire:click="saveUserPayrollInfo({{ $eu->id }})" class="px-3 py-1 text-[10px] font-semibold bg-blue-600 text-white rounded hover:bg-blue-700 transition">Save</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-2 py-4 text-crm-t3">No users available for payroll input.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Sent History Tab --}}
        @if($tab === 'history')
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-semibold mb-3">Sent Payroll History</div>
                @if(isset($sentSheets) && count($sentSheets))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-crm-border">
                                    <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Week</th>
                                    <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">User</th>
                                    <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                                    <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Total Pay</th>
                                    <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Sent Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sentSheets as $sheet)
                                    <tr class="border-b border-crm-border hover:bg-crm-hover">
                                        <td class="px-4 py-2.5 text-xs font-mono">{{ $sheet->week_label ?? '--' }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-xs">{{ $users->firstWhere('id', $sheet->user_id)?->name ?? '--' }}</td>
                                        <td class="px-4 py-2.5 text-xs text-crm-t2">{{ ucfirst($sheet->role ?? '--') }}</td>
                                        <td class="px-4 py-2.5 font-mono font-bold text-emerald-500">${{ number_format($sheet->total_pay ?? 0, 2) }}</td>
                                        <td class="px-4 py-2.5 text-xs text-crm-t3 font-mono">{{ $sheet->created_at?->format('n/j/Y') ?? '--' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-crm-t3 text-center py-6">No sent payroll sheets</p>
                @endif
            </div>
        @else
            {{-- Pay Cards --}}
            @if(isset($payCards) && count($payCards))
                <div class="space-y-4">
                    @foreach($payCards as $card)
                        @php $cardUser = $users->firstWhere('id', $card['user_id'] ?? null); @endphp
                        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                            {{-- Header --}}
                            <div class="flex items-center gap-3 mb-3 pb-3 border-b border-crm-border">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background: {{ $cardUser->color ?? '#6b7280' }}">{{ $cardUser->avatar ?? substr($cardUser->name ?? '?', 0, 2) }}</div>
                                <div class="flex-1">
                                    <div class="text-sm font-bold">{{ $cardUser->name ?? 'Unknown' }}</div>
                                    <div class="text-[10px] text-crm-t3 capitalize">{{ str_replace('_', ' ', $cardUser->role ?? '') }} | {{ $weekLabel }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-extrabold font-mono text-emerald-500">${{ number_format($card['final_pay'] ?? 0, 2) }}</div>
                                    <div class="text-[10px] text-crm-t3">Final Pay</div>
                                </div>
                            </div>

                            {{-- Deals --}}
                            @if(isset($card['deals']) && count($card['deals']))
                                <div class="mb-3">
                                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Deals ({{ count($card['deals']) }})</div>
                                    <div class="space-y-1">
                                        @foreach($card['deals'] as $d)
                                            <div class="flex items-center justify-between text-xs bg-white border border-crm-border rounded p-2">
                                                <span class="font-semibold">{{ $d['owner_name'] ?? $d['name'] ?? '--' }}</span>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono font-bold text-emerald-500">${{ number_format($d['fee'] ?? 0, 2) }}</span>
                                                    @if(isset($d['charged_back']) && $d['charged_back'] === 'yes')
                                                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-500">CB</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Commission Calculations --}}
                            <div class="mb-3">
                                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Commission Breakdown</div>
                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between"><span class="text-crm-t3">Gross Revenue</span><span class="font-mono font-semibold">${{ number_format($card['gross_revenue'] ?? 0, 2) }}</span></div>
                                    <div class="flex justify-between"><span class="text-crm-t3">Commission Rate</span><span class="font-mono">{{ $card['commission_rate'] ?? 0 }}%</span></div>
                                    <div class="flex justify-between"><span class="text-crm-t3">Commission</span><span class="font-mono font-semibold text-emerald-500">${{ number_format($card['commission'] ?? 0, 2) }}</span></div>
                                    @if(isset($card['chargebacks']) && $card['chargebacks'] > 0)
                                        <div class="flex justify-between"><span class="text-crm-t3">Chargebacks</span><span class="font-mono text-red-500">-${{ number_format($card['chargebacks'], 2) }}</span></div>
                                    @endif
                                    @if(isset($card['bonus']) && $card['bonus'] > 0)
                                        <div class="flex justify-between"><span class="text-crm-t3">Bonus</span><span class="font-mono text-blue-600">+${{ number_format($card['bonus'], 2) }}</span></div>
                                    @endif
                                    @if(isset($card['deductions']) && $card['deductions'] > 0)
                                        <div class="flex justify-between"><span class="text-crm-t3">Deductions</span><span class="font-mono text-red-500">-${{ number_format($card['deductions'], 2) }}</span></div>
                                    @endif
                                    @if(isset($card['hours']) && $card['hours'] > 0)
                                        <div class="flex justify-between"><span class="text-crm-t3">Hours</span><span class="font-mono">{{ $card['hours'] }}h @ ${{ $card['hourly_rate'] ?? 0 }}/hr</span></div>
                                    @endif
                                    <div class="flex justify-between border-t border-crm-border pt-1 font-bold"><span>Final Pay</span><span class="font-mono text-emerald-500">${{ number_format($card['final_pay'] ?? 0, 2) }}</span></div>
                                </div>
                            </div>

                            {{-- Admin Corrections --}}
                            <div class="flex flex-wrap gap-2 pt-2 border-t border-crm-border">
                                <button wire:click="addBonus({{ $card['user_id'] ?? 0 }})" class="px-2 py-1 text-[10px] font-semibold bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition">+ Bonus</button>
                                <button wire:click="addDeduction({{ $card['user_id'] ?? 0 }})" class="px-2 py-1 text-[10px] font-semibold bg-red-50 text-red-500 rounded hover:bg-red-100 transition">+ Deduction</button>
                                <button wire:click="addManualDeal({{ $card['user_id'] ?? 0 }})" class="px-2 py-1 text-[10px] font-semibold bg-emerald-50 text-emerald-600 rounded hover:bg-emerald-100 transition">+ Deal</button>
                                <button wire:click="addPayNote({{ $card['user_id'] ?? 0 }})" class="px-2 py-1 text-[10px] font-semibold bg-amber-50 text-amber-600 rounded hover:bg-amber-100 transition">+ Note</button>
                                <button wire:click="sendPaysheet({{ $card['user_id'] ?? 0 }})" class="ml-auto px-3 py-1 text-[10px] font-semibold bg-emerald-500 text-white rounded hover:bg-emerald-600 transition">Send Paysheet</button>
                            </div>

                            {{-- Notes --}}
                            @if(isset($card['notes']) && count($card['notes']))
                                <div class="mt-2 pt-2 border-t border-crm-border">
                                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Notes</div>
                                    @foreach($card['notes'] as $note)
                                        <div class="text-xs text-crm-t2 bg-white border border-crm-border rounded p-1.5 mb-1">{{ is_string($note) ? $note : ($note['text'] ?? '') }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                    <p class="text-sm text-crm-t3">No payroll data for this period</p>
                </div>
            @endif
        @endif
    @else
        {{-- Non-master: Own Paysheet Only --}}
        @if(isset($payCards) && count($payCards))
            @php $card = $payCards[0] ?? []; $cardUser = auth()->user(); @endphp
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="flex items-center gap-3 mb-3 pb-3 border-b border-crm-border">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background: {{ $cardUser->color ?? '#6b7280' }}">{{ $cardUser->avatar ?? substr($cardUser->name, 0, 2) }}</div>
                    <div class="flex-1">
                        <div class="text-sm font-bold">{{ $cardUser->name }}</div>
                        <div class="text-[10px] text-crm-t3 capitalize">{{ str_replace('_', ' ', $cardUser->role) }} | {{ $weekLabel }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-extrabold font-mono text-emerald-500">${{ number_format($card['final_pay'] ?? 0, 2) }}</div>
                        <div class="text-[10px] text-crm-t3">Final Pay</div>
                    </div>
                </div>

                @if(isset($card['deals']) && count($card['deals']))
                    <div class="mb-3">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Your Deals</div>
                        @foreach($card['deals'] as $d)
                            <div class="flex items-center justify-between text-xs bg-white border border-crm-border rounded p-2 mb-1">
                                <span class="font-semibold">{{ $d['owner_name'] ?? '--' }}</span>
                                <span class="font-mono font-bold text-emerald-500">${{ number_format($d['fee'] ?? 0, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-1 font-semibold">Breakdown</div>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between"><span class="text-crm-t3">Gross Revenue</span><span class="font-mono font-semibold">${{ number_format($card['gross_revenue'] ?? 0, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-crm-t3">Commission</span><span class="font-mono text-emerald-500">${{ number_format($card['commission'] ?? 0, 2) }}</span></div>
                    @if(isset($card['chargebacks']) && $card['chargebacks'] > 0)
                        <div class="flex justify-between"><span class="text-crm-t3">Chargebacks</span><span class="font-mono text-red-500">-${{ number_format($card['chargebacks'], 2) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-crm-border pt-1 font-bold"><span>Final Pay</span><span class="font-mono text-emerald-500">${{ number_format($card['final_pay'] ?? 0, 2) }}</span></div>
                </div>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <p class="text-sm text-crm-t3">No payroll data for this period</p>
            </div>
        @endif
    @endif
</div>
