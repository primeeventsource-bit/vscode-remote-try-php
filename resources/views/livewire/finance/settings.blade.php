<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Finance Settings</h2>
            <p class="text-xs text-crm-t3 mt-1">Configure finance module behavior, formulas, and thresholds</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/finance', 'label' => 'Dashboard'],
            ['href' => '/finance/accounts', 'label' => 'Merchant Accounts'],
            ['href' => '/finance/statements', 'label' => 'Statements'],
            ['href' => '/finance/transactions', 'label' => 'Transactions'],
            ['href' => '/finance/chargebacks', 'label' => 'Chargebacks'],
            ['href' => '/finance/entries', 'label' => 'Financial Entries'],
            ['href' => '/finance/settings', 'label' => 'Settings', 'active' => true],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- Flash Messages --}}
    @if(session('finance_success'))
        <div class="mb-4 px-4 py-2 bg-emerald-50 border border-emerald-300 text-emerald-700 text-sm rounded-lg">{{ session('finance_success') }}</div>
    @endif
    @if(session('finance_error'))
        <div class="mb-4 px-4 py-2 bg-red-50 border border-red-300 text-red-700 text-sm rounded-lg">{{ session('finance_error') }}</div>
    @endif

    <div class="space-y-6">

        {{-- ═══ GENERAL SETTINGS ═══ --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-5">
            <div class="text-sm font-bold mb-4">General</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Default Date Range</label>
                    <select wire:model="default_date_range" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="month">This Month</option>
                        <option value="quarter">Last Quarter</option>
                        <option value="year">This Year</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Currency Format</label>
                    <input type="text" wire:model="currency_format" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. USD, $">
                </div>
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Timezone</label>
                    <input type="text" wire:model="timezone" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. America/New_York">
                </div>
            </div>
        </div>

        {{-- ═══ PROFITABILITY FORMULA ═══ --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-5">
            <div class="text-sm font-bold mb-4">Profitability Formula</div>
            <p class="text-xs text-crm-t3 mb-4">Configure which entries are included in net profitability calculations.</p>
            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="include_reserve_holds" class="w-4 h-4 rounded border-crm-border text-blue-600 focus:ring-blue-400">
                    <div>
                        <span class="text-sm font-semibold">Include Reserve Holds</span>
                        <p class="text-[10px] text-crm-t3">Subtract reserve holds from net profitability</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="include_reserve_releases" class="w-4 h-4 rounded border-crm-border text-blue-600 focus:ring-blue-400">
                    <div>
                        <span class="text-sm font-semibold">Include Reserve Releases</span>
                        <p class="text-[10px] text-crm-t3">Add reserve releases back into net profitability</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="include_adjustments" class="w-4 h-4 rounded border-crm-border text-blue-600 focus:ring-blue-400">
                    <div>
                        <span class="text-sm font-semibold">Include Adjustments</span>
                        <p class="text-[10px] text-crm-t3">Factor in manual adjustments to profitability</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- ═══ STATEMENT IMPORT SETTINGS ═══ --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-5">
            <div class="text-sm font-bold mb-4">Statement Import</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Max Upload Size (MB)</label>
                    <input type="number" wire:model="max_upload_size" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" min="1" max="100">
                </div>
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Low Confidence Threshold</label>
                    <input type="number" wire:model="low_confidence_threshold" step="0.1" min="0" max="1" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. 0.6">
                </div>
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Duplicate Handling</label>
                    <select wire:model="duplicate_handling" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        <option value="skip">Skip Duplicates</option>
                        <option value="overwrite">Overwrite</option>
                        <option value="flag">Flag for Review</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-3 cursor-pointer pb-1.5">
                        <input type="checkbox" wire:model="import_confirmation_required" class="w-4 h-4 rounded border-crm-border text-blue-600 focus:ring-blue-400">
                        <div>
                            <span class="text-sm font-semibold">Require Confirmation</span>
                            <p class="text-[10px] text-crm-t3">Preview before importing</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- ═══ CHARGEBACK WARNINGS ═══ --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-5">
            <div class="text-sm font-bold mb-4">Chargeback Warnings</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Due Soon Warning (days)</label>
                    <input type="number" wire:model="cb_due_soon_days" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" min="1" max="30" placeholder="e.g. 7">
                    <p class="text-[10px] text-crm-t3 mt-1">Chargebacks due within this many days will be flagged as "Due Soon"</p>
                </div>
                <div>
                    <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Overdue Threshold (days)</label>
                    <input type="number" wire:model="cb_overdue_days" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" min="0" max="30" placeholder="e.g. 0">
                    <p class="text-[10px] text-crm-t3 mt-1">Chargebacks past due date by this many days are marked overdue</p>
                </div>
            </div>
        </div>

    </div>

    {{-- Save Button --}}
    <div class="mt-6 flex items-center gap-3">
        <button wire:click="save" class="px-5 py-2 text-sm font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Save Settings
        </button>
        <span wire:loading wire:target="save" class="text-xs text-blue-500">Saving...</span>
    </div>
</div>
