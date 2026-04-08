<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Merchant Accounts</h2>
            <p class="text-xs text-crm-t3 mt-1">Manage MID registry and monitor account health</p>
        </div>
        <button wire:click="$toggle('showForm')" class="px-4 py-1.5 text-sm font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            {{ $showForm ? 'Cancel' : '+ Add MID' }}
        </button>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/finance', 'label' => 'Dashboard'],
            ['href' => '/finance/accounts', 'label' => 'Merchant Accounts', 'active' => true],
            ['href' => '/finance/statements', 'label' => 'Statements'],
            ['href' => '/finance/transactions', 'label' => 'Transactions'],
            ['href' => '/finance/chargebacks', 'label' => 'Chargebacks'],
            ['href' => '/finance/entries', 'label' => 'Financial Entries'],
            ['href' => '/finance/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- Notifications --}}
    @if($successMessage)
        <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-400 text-emerald-800 text-sm rounded-lg shadow-sm">&#10003; {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-400 text-red-800 text-sm rounded-lg shadow-sm">&#10007; {{ $errorMessage }}</div>
    @endif

    {{-- Add MID Form --}}
    @if($showForm)
    <div class="bg-crm-card border border-crm-border rounded-lg p-5 mb-5">
        <div class="text-sm font-bold mb-4">{{ isset($editingId) && $editingId ? 'Edit' : 'Add New' }} Merchant Account</div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Account Name</label>
                <input type="text" wire:model="account_name" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. Primary Processing">
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">MID Number</label>
                <input type="text" wire:model="mid_number" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. 4445556667778">
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Processor Name</label>
                <input type="text" wire:model="processor_name" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. Stripe, NMI">
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Gateway Name</label>
                <input type="text" wire:model="gateway_name" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. Authorize.net">
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Descriptor</label>
                <input type="text" wire:model="descriptor" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Statement descriptor">
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Business Name</label>
                <input type="text" wire:model="business_name" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Legal business name">
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Account Status</label>
                <select wire:model="account_status" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Currency</label>
                <input type="text" wire:model="currency" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. USD">
            </div>
            <div class="md:col-span-2 lg:col-span-1">
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Notes</label>
                <textarea wire:model="notes" rows="2" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Optional notes..."></textarea>
            </div>
        </div>
        <div class="flex items-center gap-3 mt-4">
            <button wire:click="save" class="px-4 py-1.5 text-sm font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Save Account</button>
            <button wire:click="$toggle('showForm')" class="px-4 py-1.5 text-sm font-semibold bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Accounts Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-crm-surface">
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Account Name</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">MID Number</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Processor</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Status</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Transactions</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Approved Volume</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Chargebacks</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">CB Amount</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Fees</th>
                    <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Statements</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-crm-border">
                @forelse($accounts as $account)
                <tr class="hover:bg-crm-hover transition">
                    <td class="px-3 py-2 font-semibold">{{ $account->account_name }}</td>
                    <td class="px-3 py-2 text-xs font-mono text-crm-t3">{{ $account->mid_number }}</td>
                    <td class="px-3 py-2 text-xs">{{ $account->processor_name }}</td>
                    <td class="px-3 py-2 text-center">
                        @if($account->account_status === 'active')
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-emerald-100 text-emerald-700">Active</span>
                        @elseif($account->account_status === 'suspended')
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-amber-100 text-amber-700">Suspended</span>
                        @else
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-red-100 text-red-700">Closed</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right text-xs">{{ number_format($account->txn_count ?? 0) }}</td>
                    <td class="px-3 py-2 text-right text-xs font-semibold">${{ number_format($account->approved_volume ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-right text-xs">{{ number_format($account->cb_count ?? 0) }}</td>
                    <td class="px-3 py-2 text-right text-xs text-red-600">${{ number_format($account->cb_amount ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-right text-xs text-orange-600">${{ number_format($account->fee_total ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-right text-xs">{{ number_format($account->statement_count ?? 0) }}</td>
                    <td class="px-3 py-2 text-center">
                        <button wire:click="edit({{ $account->id }})" class="px-2 py-0.5 text-[9px] font-semibold bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition">Edit</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="px-3 py-8 text-center text-sm text-crm-t3">No merchant accounts found. Click "Add MID" to create one.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
