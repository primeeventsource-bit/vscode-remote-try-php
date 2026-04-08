<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Payroll Settings</h2>
            <p class="text-xs text-crm-t3 mt-1">Configure commission rates, deductions, and payroll rules</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/payroll-v2', 'label' => 'Dashboard'],
            ['href' => '/payroll-v2/deals', 'label' => 'Deals'],
            ['href' => '/payroll-v2/batches', 'label' => 'Batches'],
            ['href' => '/payroll-v2/settings', 'label' => 'Settings', 'active' => true],
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- ═══ COMMISSION PERCENTAGES ═══ --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-bold mb-1">Commission Percentages</div>
            <div class="text-[10px] text-crm-t3 mb-4">Set the default commission split for each role</div>

            <div class="space-y-3">
                <div>
                    <label for="fld-fronter_percent" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Fronter %</label>
                    <input id="fld-fronter_percent" wire:model.live="fronter_percent" type="number" step="0.01" min="0" max="100"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                    @error('fronter_percent')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="fld-closer_percent" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Closer %</label>
                    <input id="fld-closer_percent" wire:model.live="closer_percent" type="number" step="0.01" min="0" max="100"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                    @error('closer_percent')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="fld-admin_percent" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Admin %</label>
                    <input id="fld-admin_percent" wire:model.live="admin_percent" type="number" step="0.01" min="0" max="100"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                    @error('admin_percent')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- ═══ BUSINESS DEDUCTIONS ═══ --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-bold mb-1">Business Deductions</div>
            <div class="text-[10px] text-crm-t3 mb-4">Overhead deductions applied before company net calculation</div>

            <div class="space-y-3">
                <div>
                    <label for="fld-processing_percent" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Processing %</label>
                    <input id="fld-processing_percent" wire:model.live="processing_percent" type="number" step="0.01" min="0" max="100"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                    @error('processing_percent')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="fld-reserve_percent" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Reserve %</label>
                    <input id="fld-reserve_percent" wire:model.live="reserve_percent" type="number" step="0.01" min="0" max="100"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                    @error('reserve_percent')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="fld-marketing_percent" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Marketing %</label>
                    <input id="fld-marketing_percent" wire:model.live="marketing_percent" type="number" step="0.01" min="0" max="100"
                        class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                    @error('marketing_percent')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ LIVE PREVIEW ═══ --}}
    <div class="mt-5 bg-crm-card border border-crm-border rounded-lg p-4">
        <div class="text-sm font-bold mb-3">Live Preview</div>
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="text-xs text-crm-t3">Total Deduction:</span>
                <span class="text-sm font-extrabold font-mono">{{ number_format($totalPct ?? 0, 2) }}%</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-crm-t3">Company Retained:</span>
                @php $cr = $companyRetained ?? 0; @endphp
                <span class="text-sm font-extrabold font-mono px-2 py-0.5 rounded
                    @if($cr >= 50) bg-emerald-50 text-emerald-700
                    @elseif($cr >= 40) bg-amber-50 text-amber-700
                    @else bg-red-50 text-red-700
                    @endif
                ">{{ number_format($cr, 2) }}%</span>
            </div>
        </div>
    </div>

    {{-- ═══ COMMISSION HOLD ═══ --}}
    <div class="mt-5 bg-crm-card border border-crm-border rounded-lg p-4">
        <div class="text-sm font-bold mb-1">Commission Hold</div>
        <div class="text-[10px] text-crm-t3 mb-4">Hold back a percentage of commissions for a configurable period</div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-center gap-3">
                <input id="fld-hold_enabled" wire:model.live="hold_enabled" type="checkbox" class="rounded border-crm-border text-blue-600 focus:ring-blue-500">
                <label for="fld-hold_enabled" class="text-xs font-semibold">Enable Commission Hold</label>
            </div>
            <div>
                <label for="fld-hold_percent" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Hold %</label>
                <input id="fld-hold_percent" wire:model.live="hold_percent" type="number" step="0.01" min="0" max="100"
                    class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                @error('hold_percent')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="fld-hold_days" class="block text-[10px] font-semibold text-crm-t3 uppercase tracking-wider mb-1">Hold Days</label>
                <input id="fld-hold_days" wire:model.live="hold_days" type="number" step="1" min="0"
                    class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg font-mono focus:outline-none focus:border-blue-400">
                @error('hold_days')<div class="text-[10px] text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- ═══ SYSTEM ═══ --}}
    <div class="mt-5 bg-crm-card border border-crm-border rounded-lg p-4">
        <div class="text-sm font-bold mb-1">System</div>
        <div class="text-[10px] text-crm-t3 mb-4">System-wide payroll behavior settings</div>

        <div class="flex flex-wrap gap-6">
            <div class="flex items-center gap-3">
                <input id="fld-allow_admin_adjustments" wire:model.live="allow_admin_adjustments" type="checkbox" class="rounded border-crm-border text-blue-600 focus:ring-blue-500">
                <label for="fld-allow_admin_adjustments" class="text-xs font-semibold">Allow Admin Adjustments</label>
            </div>
            <div class="flex items-center gap-3">
                <input id="fld-auto_calculate" wire:model.live="auto_calculate" type="checkbox" class="rounded border-crm-border text-blue-600 focus:ring-blue-500">
                <label for="fld-auto_calculate" class="text-xs font-semibold">Auto Calculate on Deal Close</label>
            </div>
        </div>
    </div>

    {{-- ═══ ACTION BUTTONS ═══ --}}
    <div class="mt-5 flex items-center gap-3">
        <button wire:click="save" class="px-5 py-2 text-sm font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Save Settings
        </button>
        <button wire:click="resetDefaults" class="px-5 py-2 text-sm font-semibold bg-crm-card border border-crm-border text-crm-t2 rounded-lg hover:bg-crm-hover transition">
            Reset to Default
        </button>
    </div>
</div>
