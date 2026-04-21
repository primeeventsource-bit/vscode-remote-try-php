<div
    class="p-5"
    x-data="{
        showCallback: false,
        selected: @entangle('selectedLeads').live,
        visibleLeadIds: @js($leads->pluck('id')->map(fn($id) => (int) $id)->values()),
        lastSelectedIndex: null,
        normalizedSelected() {
            return (this.selected || []).map(v => Number(v));
        },
        isSelected(id) {
            return this.normalizedSelected().includes(Number(id));
        },
        selectAllVisibleLocal() {
            this.selected = [...this.visibleLeadIds];
            this.lastSelectedIndex = null;
        },
        clearSelectionLocal() {
            this.selected = [];
            this.lastSelectedIndex = null;
        },
        toggleLead(id, event) {
            const leadId = Number(id);
            const ids = this.visibleLeadIds.map(v => Number(v));
            const currentIndex = ids.indexOf(leadId);
            const selectedSet = new Set(this.normalizedSelected());

            if (event.shiftKey && this.lastSelectedIndex !== null && currentIndex !== -1) {
                const start = Math.min(this.lastSelectedIndex, currentIndex);
                const end = Math.max(this.lastSelectedIndex, currentIndex);
                for (let i = start; i <= end; i++) {
                    selectedSet.add(ids[i]);
                }
                this.selected = Array.from(selectedSet);
                return;
            }

            if (selectedSet.has(leadId)) {
                selectedSet.delete(leadId);
            } else {
                selectedSet.add(leadId);
            }

            this.selected = Array.from(selectedSet);
            this.lastSelectedIndex = currentIndex;
        }
    }"
>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Leads</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ number_format($totalLeads) }} total leads &middot; Page {{ $leads->currentPage() }} of {{ $leads->lastPage() }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="$set('showAddModal', true)" data-training="add-lead-btn" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">+ Add Lead</button>
            <button wire:click="$set('showImportModal', true)" data-training="import-csv-btn" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">Import CSV</button>
            @if($isAdmin)
                <a href="{{ route('lead-imports') }}" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">Import History</a>
                <a href="{{ route('duplicate-review') }}" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">
                    Duplicate Review
                    @if(($duplicateCounts['pending'] ?? 0) > 0)
                        <span class="ml-1 inline-flex items-center justify-center w-5 h-5 text-[9px] font-bold text-white bg-red-500 rounded-full">{{ $duplicateCounts['pending'] > 99 ? '99+' : $duplicateCounts['pending'] }}</span>
                    @endif
                </a>
            @endif
        </div>
    </div>

    {{-- Flash messages --}}
    @if($importStatus)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ $importStatus }}
        </div>
    @endif

    @if(session()->has('message'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('message') }}
        </div>
    @endif

    {{-- Duplicate Stats Widget (Admin only) --}}
    @if($isAdmin && $duplicateCounts && $duplicateCounts['total'] > 0)
    <div class="grid grid-cols-4 gap-3 mb-4">
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold">{{ number_format($duplicateCounts['total']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Total Duplicates</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-red-600">{{ number_format($duplicateCounts['exact']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Exact</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-amber-600">{{ number_format($duplicateCounts['possible']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Possible</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-blue-600">{{ number_format($duplicateCounts['pending']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Pending Review</div>
        </div>
    </div>
    @endif

    {{-- Lead Age Category Tabs --}}
    <div class="flex items-center gap-1 mb-4 bg-crm-card border border-crm-border rounded-lg p-0.5">
        <button wire:click="$set('ageFilter', 'all')"
            class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $ageFilter === 'all' ? 'bg-blue-600 text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            All Leads
        </button>
        <button wire:click="$set('ageFilter', 'new')"
            class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $ageFilter === 'new' ? 'bg-blue-600 text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            New Today
        </button>
        <button wire:click="$set('ageFilter', 'this_week')"
            class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $ageFilter === 'this_week' ? 'bg-blue-600 text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            This Week
        </button>
        <button wire:click="$set('ageFilter', 'last_month')"
            class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $ageFilter === 'last_month' ? 'bg-blue-600 text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            Last Month
        </button>
        <button wire:click="$set('ageFilter', 'old')"
            class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $ageFilter === 'old' ? 'bg-blue-600 text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            Older
        </button>
    </div>

    {{-- Search + Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input id="fld-search" wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name, resort, phone, or email..." class="flex-1 min-w-[220px] px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20">
        <select id="fld-resortFilter" wire:model.live="resortFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All Resorts</option>
            @foreach($resorts as $r)
                <option value="{{ $r }}">{{ $r }}</option>
            @endforeach
        </select>

        {{-- Role filter — pick a role, then see users in that role --}}
        <select id="fld-roleFilter" wire:model.live="roleFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All Roles</option>
            @foreach($roles as $role)
                <option value="{{ $role }}">{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
            @endforeach
        </select>

        {{-- User filter — shows all users, or only users in selected role --}}
        <select id="fld-fronterFilter" wire:model.live="fronterFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            @if($roleFilter === 'all')
                <option value="all">All Assigned Users</option>
                <option value="unassigned">Unassigned Leads</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }} ({{ ucfirst(str_replace('_', ' ', $u->role)) }})</option>
                @endforeach
            @else
                <option value="all">All {{ ucfirst(str_replace('_', ' ', $roleFilter)) }}s</option>
                @foreach($roleUsers as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            @endif
        </select>

        <div class="flex items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5">
            <button wire:click="$set('filter', 'all')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $filter === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                All
            </button>
            <button wire:click="$set('filter', 'undisposed')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $filter === 'undisposed' ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                Undisposed
            </button>
            <button wire:click="$set('filter', 'transferred')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $filter === 'transferred' ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                Transferred
            </button>
        </div>
        @if($isAdmin)
        <select id="fld-duplicateFilter" wire:model.live="duplicateFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All (Duplicates)</option>
            <option value="has_duplicates">Has Duplicates</option>
            <option value="exact_duplicates">Exact Duplicates</option>
            <option value="possible_duplicates">Possible Duplicates</option>
            <option value="pending_review">Pending Review</option>
            <option value="reviewed">Reviewed</option>
        </select>
        @endif
    </div>

    {{-- Bulk Actions Bar --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-crm-border bg-crm-card p-2">
        <button @click="selectAllVisibleLocal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-crm-border hover:bg-crm-hover transition">Select All Visible</button>
        <button @click="clearSelectionLocal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-crm-border hover:bg-crm-hover transition">Clear Selection</button>
        <span class="text-xs text-crm-t3">{{ count($selectedLeads) }} selected</span>
        <div class="w-56" data-training="bulk-assign-dropdown">
            <x-user-picker :users="$users" wire-model="bulkFronter" placeholder="Assign selected to user..." />
        </div>
        <button wire:click="assignSelectedToFronter" data-training="bulk-assign-btn" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Assign Selected</button>
        <button wire:click="unassignSelectedLeads" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition">Take Selected Off Rep</button>
        @if($isAdmin && count($selectedLeads) > 0)
            <button wire:click="bulkKeepSelected" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition">Keep Selected</button>
            <button wire:click="bulkMarkReviewed" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-500 text-white hover:bg-gray-600 transition">Mark Reviewed</button>
            <button wire:click="bulkDeleteSelected" onclick="return confirm('Delete {{ count($selectedLeads) }} selected leads? This action uses soft delete.')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-500 text-white hover:bg-red-600 transition">Delete Selected</button>
        @endif
        @error('bulkFronter')
            <span class="text-xs font-semibold text-red-600">{{ $message }}</span>
        @enderror
    </div>

    {{-- Fronter Stats (Admin only) --}}
    @if($isAdmin)
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-4">
        <div class="px-4 py-2.5 border-b border-crm-border bg-crm-surface">
            <h3 class="text-sm font-semibold">Fronter Lead Lists · Disposition / Status</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-white">
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Rep</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Total</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Undisposed</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Transferred</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Callback</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Right Number</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fronterStats as $s)
                        <tr class="border-b border-crm-border">
                            <td class="px-4 py-2.5 font-semibold">{{ $s['name'] }}</td>
                            <td class="px-4 py-2.5">{{ $s['total'] }}</td>
                            <td class="px-4 py-2.5">{{ $s['undisposed'] }}</td>
                            <td class="px-4 py-2.5">{{ $s['transferred'] }}</td>
                            <td class="px-4 py-2.5">{{ $s['callback'] }}</td>
                            <td class="px-4 py-2.5">{{ $s['right_number'] }}</td>
                            <td class="px-4 py-2.5">
                                <button wire:click="$set('fronterFilter', '{{ $s['id'] }}')" class="px-2 py-1 rounded border border-crm-border bg-white hover:bg-crm-hover transition">View List</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-4 text-crm-t3">No fronter reps found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Leads Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-4" data-training="leads-table">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold w-10">
                            <input
                                type="checkbox"
                                :checked="visibleLeadIds.length > 0 && visibleLeadIds.every(id => normalizedSelected().includes(Number(id)))"
                                @click.stop="if ($event.target.checked) { selectAllVisibleLocal(); } else { clearSelectionLocal(); }"
                                class="h-3.5 w-3.5 rounded border-crm-border"
                            >
                        </th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Resort</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner Name</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Phone 1</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Phone 2</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Location</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Assigned To</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Disposition</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Source</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr wire:click="selectLead({{ $lead->id }})" data-training="lead-row" class="border-b border-crm-border cursor-pointer transition {{ $selectedLead === $lead->id || in_array($lead->id, $selectedLeads) ? 'bg-blue-50 border-l-2 border-l-blue-500' : 'hover:bg-crm-hover' }}">
                            <td class="px-3 py-2.5" wire:click.stop>
                                <input
                                    type="checkbox"
                                    :checked="isSelected({{ $lead->id }})"
                                    @click.stop="toggleLead({{ $lead->id }}, $event)"
                                    class="h-3.5 w-3.5 rounded border-crm-border"
                                >
                            </td>
                            <td class="px-4 py-2.5 text-crm-t2">{{ $lead->resort }}</td>
                            <td class="px-4 py-2.5 font-semibold">{{ $lead->owner_name }}</td>
                            <td class="px-4 py-2.5">
                                @if($lead->phone1)
                                    <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                                        <button type="button" @click.stop="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $lead->phone1) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-xs hover:underline cursor-pointer" title="Click to copy">{{ $lead->phone1 }}</button>
                                        <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                                    </span>
                                @else
                                    <span class="text-crm-t3">--</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($lead->phone2)
                                    <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                                        <button type="button" @click.stop="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $lead->phone2) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-xs hover:underline cursor-pointer" title="Click to copy">{{ $lead->phone2 }}</button>
                                        <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                                    </span>
                                @else
                                    <span class="text-crm-t3">--</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-crm-t2">{{ $lead->city }}{{ $lead->st ? ', '.$lead->st : '' }} {{ $lead->zip }}</td>
                            <td class="px-4 py-2.5 text-crm-t2">
                                @php $assigned = $users->firstWhere('id', $lead->assigned_to); @endphp
                                {{ $assigned->name ?? '--' }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if($lead->disposition)
                                    @php
                                        $dColor = match(true) {
                                            str_contains($lead->disposition, 'Transferred') => 'bg-purple-50 text-purple-600',
                                            str_contains($lead->disposition, 'Right Number') => 'bg-emerald-50 text-emerald-600',
                                            str_contains($lead->disposition, 'Wrong') || str_contains($lead->disposition, 'Disconnected') => 'bg-red-50 text-red-500',
                                            str_contains($lead->disposition, 'Callback') => 'bg-amber-50 text-amber-600',
                                            str_contains($lead->disposition, 'Voice') => 'bg-sky-50 text-sky-600',
                                            default => 'bg-gray-50 text-gray-600',
                                        };
                                    @endphp
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $dColor }}">{{ $lead->disposition }}</span>
                                @else
                                    <span class="text-crm-t3 text-xs">Undisposed</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-crm-t3">{{ $lead->source ?? 'N/A' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-crm-t3 text-sm">No leads found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination Footer --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
            <span class="text-xs text-crm-t3">Per page:</span>
            <select id="fld-perPage" wire:model.live="perPage" class="px-2 py-1 text-xs bg-white border border-crm-border rounded-lg focus:outline-none">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
            </select>
        </div>
        <div>
            {{ $leads->links() }}
        </div>
    </div>

    {{-- Detail Panel --}}
    @if($active)
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-4" data-training="lead-detail-panel">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold">{{ $active->owner_name }}</h3>
                <div class="flex items-center gap-2">
                    @if($showEditModal && !empty($editForm) && ($editForm['id'] ?? 0) == $active->id)
                        <button wire:click="$set('showEditModal', false)" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-200 text-gray-700">Cancel Edit</button>
                    @else
                        <button wire:click="editLead({{ $active->id }})" data-training="edit-lead-btn" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Edit Lead</button>
                    @endif
                    @if($isAdmin)
                        <button wire:click="confirmDeleteLead({{ $active->id }})" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">Delete</button>
                    @endif
                    <button wire:click="selectLead({{ $active->id }})" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>
            </div>

            {{-- Save confirmation --}}
            @if($leadSaveMessage)
                <div class="mb-3 px-3 py-2 rounded-lg text-xs font-semibold {{ str_starts_with($leadSaveMessage, '✓') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                    {{ $leadSaveMessage }}
                </div>
            @endif

            {{-- Editable fields --}}
            @if($showEditModal && !empty($editForm) && ($editForm['id'] ?? 0) == $active->id)
            <div class="mb-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                    <div>
                        <label for="el-name" class="text-[10px] text-crm-t3 uppercase">Owner Name</label>
                        <input id="el-name" wire:model="editForm.owner_name" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-resort" class="text-[10px] text-crm-t3 uppercase">Resort</label>
                        <input id="el-resort" wire:model="editForm.resort" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-phone1" class="text-[10px] text-crm-t3 uppercase">Phone 1</label>
                        <input id="el-phone1" wire:model="editForm.phone1" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-phone2" class="text-[10px] text-crm-t3 uppercase">Phone 2</label>
                        <input id="el-phone2" wire:model="editForm.phone2" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-email" class="text-[10px] text-crm-t3 uppercase">Email</label>
                        <input id="el-email" wire:model="editForm.email" type="email" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-city" class="text-[10px] text-crm-t3 uppercase">City</label>
                        <input id="el-city" wire:model="editForm.city" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-st" class="text-[10px] text-crm-t3 uppercase">State</label>
                        <input id="el-st" wire:model="editForm.st" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-zip" class="text-[10px] text-crm-t3 uppercase">Zip</label>
                        <input id="el-zip" wire:model="editForm.zip" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                    <div>
                        <label for="el-rloc" class="text-[10px] text-crm-t3 uppercase">Resort Location</label>
                        <input id="el-rloc" wire:model="editForm.resort_location" type="text" class="w-full px-3 py-1.5 text-sm border border-crm-border rounded-lg">
                    </div>
                </div>
                <button wire:click="updateLead" class="px-5 py-2 text-sm font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow transition">Save Lead</button>
            </div>
            @else
            {{-- Read-only fields --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Resort</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->resort }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 1</div>
                    <div class="mt-0.5">
                        @if($active->phone1)
                            <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                                <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->phone1) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">{{ $active->phone1 }}</button>
                                <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                            </span>
                        @else <span class="text-crm-t3 text-sm">--</span> @endif
                    </div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 2</div>
                    <div class="mt-0.5">
                        @if($active->phone2)
                            <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                                <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->phone2) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">{{ $active->phone2 }}</button>
                                <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                            </span>
                        @else <span class="text-crm-t3 text-sm">--</span> @endif
                    </div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Email</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->email ?: '--' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Location</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->city }}{{ $active->st ? ', '.$active->st : '' }} {{ $active->zip }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Resort Location</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->resort_location ?: '--' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Assigned To</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $users->firstWhere('id', $active->assigned_to)?->name ?? 'Unassigned' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Original Fronter</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $users->firstWhere('id', $active->original_fronter)?->name ?? '--' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Disposition</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->disposition ?? 'Undisposed' }}</div>
                </div>
                @if($active->callback_date ?? null)
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Callback Date</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->callback_date?->format('n/j/Y g:i A') }}</div>
                </div>
                @endif
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Source</div>
                    <div class="text-sm mt-0.5">{{ $active->source ?? 'N/A' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Created</div>
                    <div class="text-sm mt-0.5">{{ $active->created_at?->format('n/j/Y g:i A') }}</div>
                </div>
            </div>
            @endif

            {{-- Duplicate Panel on Lead Detail --}}
            @if($activeDuplicates && $activeDuplicates->count() > 0)
                <div class="border-t border-crm-border pt-4 mb-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Duplicates Found</span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-[9px] font-bold text-white bg-red-500 rounded-full">{{ $activeDuplicates->count() }}</span>
                    </div>
                    <div class="space-y-2">
                        @foreach($activeDuplicates as $dup)
                            @php
                                $otherLead = $dup->lead_id == $active->id ? $dup->duplicateLead : $dup->lead;
                                $typeColor = $dup->duplicate_type === 'exact' ? 'bg-red-50 text-red-600 border-red-200' : 'bg-amber-50 text-amber-600 border-amber-200';
                                $statusColor = match($dup->review_status) {
                                    'pending' => 'bg-yellow-50 text-yellow-700',
                                    'kept_both' => 'bg-green-50 text-green-700',
                                    'deleted_duplicate' => 'bg-red-50 text-red-700',
                                    'ignored' => 'bg-gray-50 text-gray-600',
                                    default => 'bg-gray-50 text-gray-600',
                                };
                            @endphp
                            @if($otherLead)
                            <div class="flex items-center justify-between bg-crm-surface rounded-lg p-3 border border-crm-border">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold">{{ $otherLead->owner_name }}</span>
                                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded border {{ $typeColor }}">{{ ucfirst($dup->duplicate_type) }}</span>
                                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $statusColor }}">{{ str_replace('_', ' ', ucfirst($dup->review_status)) }}</span>
                                    </div>
                                    <div class="text-[10px] text-crm-t3 mt-1">{{ $dup->duplicate_reason }} &middot; {{ $otherLead->phone1 }} &middot; {{ $otherLead->resort }}</div>
                                </div>
                                @if($dup->review_status === 'pending')
                                <div class="flex items-center gap-1 ml-3">
                                    <button wire:click="keepBothDuplicates({{ $dup->id }})" class="px-2 py-1 text-[9px] font-semibold rounded bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100">Keep Both</button>
                                    <button wire:click="ignoreDuplicate({{ $dup->id }})" class="px-2 py-1 text-[9px] font-semibold rounded bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100">Ignore</button>
                                    @if($isAdmin)
                                        <button wire:click="confirmDeleteLead({{ $otherLead->id }})" class="px-2 py-1 text-[9px] font-semibold rounded bg-red-50 text-red-600 border border-red-200 hover:bg-red-100">Delete Dup</button>
                                    @endif
                                </div>
                                @endif
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Disposition Buttons --}}
            <div class="border-t border-crm-border pt-4" data-training="disposition-section">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-3">Set Disposition</div>
                <div class="flex flex-wrap gap-2" data-training="disposition-buttons">
                    <button wire:click="setDisposition({{ $active->id }}, 'Wrong Number')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">Wrong Number</button>
                    <button wire:click="setDisposition({{ $active->id }}, 'Disconnected')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">Disconnected</button>
                    <button wire:click="setDisposition({{ $active->id }}, 'Right Number')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 transition">Right Number</button>
                    <button wire:click="setDisposition({{ $active->id }}, 'Left Voice Mail')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-50 text-sky-600 border border-sky-200 hover:bg-sky-100 transition">Left Voice Mail</button>
                </div>

                {{-- Callback --}}
                <div class="flex items-center gap-2 mt-3">
                    <input id="fld-callbackDateTime" wire:model="callbackDateTime" type="datetime-local" class="px-3 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none">
                    <button wire:click="doCallback({{ $active->id }})" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 transition">Callback</button>
                </div>

                {{-- Transfer to Closer --}}
                <div class="flex items-center gap-2 mt-3">
                    <div class="flex-1" data-training="transfer-closer">
                        <x-user-picker :users="$users" wire-model="transferCloser" placeholder="Select user to transfer..." />
                    </div>
                    <button wire:click="transferToCloser({{ $active->id }})" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-50 text-purple-600 border border-purple-200 hover:bg-purple-100 transition">Transfer Lead</button>
                </div>

                {{-- Convert to Deal (Closer only) --}}
                @if(auth()->user()?->hasRole('closer', 'master_admin', 'admin') && $active->disposition === 'Transferred to Closer')
                    <div class="mt-4 pt-3 border-t border-crm-border">
                        <button wire:click="openConvertForm({{ $active->id }})" data-training="convert-deal-btn" class="w-full px-4 py-2.5 text-sm font-bold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">
                            Convert to Deal
                        </button>
                    </div>
                @endif

                {{-- Transfer Deal to Admin (only after conversion) --}}
                @if(auth()->user()?->hasRole('closer', 'master_admin', 'admin') && $active->disposition === 'Converted to Deal')
                    <div class="mt-4 pt-3 border-t border-crm-border">
                        <div class="text-[10px] text-emerald-600 uppercase tracking-wider font-semibold mb-2">Deal Created — Transfer for Verification</div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1" data-training="transfer-admin">
                                <x-user-picker :users="$users" wire-model="transferAdmin" placeholder="Select user..." />
                            </div>
                            <button wire:click="transferDealToAdmin" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Transfer</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- AI Trainer Coach Panel --}}
    @if($active)
        <div class="mb-4">
            @livewire('ai-trainer-panel', ['module' => 'leads', 'entityId' => $selectedLead], key('ai-trainer-' . $selectedLead))
        </div>
    @endif

    {{-- Add Lead Modal --}}
    @if($showAddModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showAddModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">Add Lead</h3>
                    <button wire:click="$set('showAddModal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="fld-newLead-owner_name" class="text-[10px] text-crm-t3 uppercase tracking-wider">Owner Name</label>
                                <input id="fld-newLead-owner_name" wire:model="newLead.owner_name" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label for="fld-newLead-resort" class="text-[10px] text-crm-t3 uppercase tracking-wider">Resort</label>
                                <input id="fld-newLead-resort" wire:model="newLead.resort" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="fld-newLead-phone1" class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 1</label>
                                <input id="fld-newLead-phone1" wire:model="newLead.phone1" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label for="fld-newLead-phone2" class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 2</label>
                                <input id="fld-newLead-phone2" wire:model="newLead.phone2" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div>
                        <label for="fld-newLead-email" class="text-[10px] text-crm-t3 uppercase tracking-wider">Email</label>
                            <input id="fld-newLead-email" wire:model="newLead.email" type="email" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="fld-newLead-city" class="text-[10px] text-crm-t3 uppercase tracking-wider">City</label>
                                <input id="fld-newLead-city" wire:model="newLead.city" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label for="fld-newLead-st" class="text-[10px] text-crm-t3 uppercase tracking-wider">State</label>
                                <input id="fld-newLead-st" wire:model="newLead.st" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label for="fld-newLead-zip" class="text-[10px] text-crm-t3 uppercase tracking-wider">Zip</label>
                                <input id="fld-newLead-zip" wire:model="newLead.zip" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div>
                        <label for="fld-newLead-resort_location" class="text-[10px] text-crm-t3 uppercase tracking-wider">Resort Location</label>
                                <input id="fld-newLead-resort_location" wire:model="newLead.resort_location" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showAddModal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="saveLead" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Lead</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Import CSV Modal (Enterprise) --}}
    @if($showImportModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showImportModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">Import Leads</h3>
                    <button wire:click="$set('showImportModal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>

                <p class="text-xs text-crm-t3 mb-3">Upload a CSV file. Expected columns: Resort, Owner Name, Phone 1, Phone 2, City, State, Zip, Resort Location, Email (optional)</p>

                {{-- Error display --}}
                @if($importError)
                    <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {{ $importError }}
                    </div>
                @endif

                {{-- Duplicate Strategy --}}
                <div class="mb-3">
                    <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Duplicate Handling</label>
                    <div class="flex items-center gap-3 mt-1">
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input id="fld-duplicateStrategy-flag" type="radio" wire:model="duplicateStrategy" value="flag" class="text-blue-600"> Flag & Import
                        </label>
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input id="fld-duplicateStrategy-skip" type="radio" wire:model="duplicateStrategy" value="skip" class="text-blue-600"> Skip Duplicates
                        </label>
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input id="fld-duplicateStrategy-import_all" type="radio" wire:model="duplicateStrategy" value="import_all" class="text-blue-600"> Import All
                        </label>
                    </div>
                </div>

                {{-- File Upload via Livewire --}}
                <div class="mb-3">
                    <label class="flex min-h-[120px] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-crm-border bg-crm-surface hover:border-blue-400 px-4 py-6 text-center transition">
                        <div class="text-sm font-semibold text-crm-t2">Click to select CSV file</div>
                        <div class="mt-1 text-xs text-crm-t3">or drag and drop here (up to 100MB)</div>
                        @if($leadFile)
                            <div class="mt-3 rounded bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700">
                                {{ $leadFile->getClientOriginalName() }} ({{ number_format($leadFile->getSize() / 1024, 1) }} KB)
                            </div>
                        @endif
                        <input id="fld-leadFile" type="file" wire:model="leadFile" accept=".csv,.txt" class="hidden">
                    </label>
                    @error('leadFile')
                        <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <details class="mb-3">
                    <summary class="cursor-pointer text-xs font-semibold text-crm-t2">Or paste CSV manually</summary>
                    <textarea id="fld-csvText" wire:model="csvText" rows="6" class="mt-2 w-full px-3 py-2 text-sm font-mono bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Resort,Owner Name,Phone1,Phone2,City,St,Zip,Resort Location,Email"></textarea>
                </details>

                <div class="flex justify-end gap-2">
                    <button wire:click="$set('showImportModal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="importLeads" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60 transition">
                        <span wire:loading.remove wire:target="importLeads">Import</span>
                        <span wire:loading wire:target="importLeads">Queuing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Convert to Deal Modal --}}
    @if($showConvertModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showConvertModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">Convert Lead to Deal</h3>
                    <button wire:click="$set('showConvertModal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>

                <div class="space-y-3">
                    {{-- Customer Info --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Customer Information</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cv-owner" class="text-[10px] text-crm-t3">Owner Name</label>
                                <input id="cv-owner" wire:model="convertForm.owner_name" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-email" class="text-[10px] text-crm-t3">Email</label>
                                <input id="cv-email" wire:model="convertForm.email" type="email" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-phone1" class="text-[10px] text-crm-t3">Primary Phone</label>
                                <input id="cv-phone1" wire:model="convertForm.primary_phone" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-phone2" class="text-[10px] text-crm-t3">Secondary Phone</label>
                                <input id="cv-phone2" wire:model="convertForm.secondary_phone" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-address" class="text-[10px] text-crm-t3">Mailing Address</label>
                                <input id="cv-address" wire:model="convertForm.mailing_address" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-csz" class="text-[10px] text-crm-t3">City / State / Zip</label>
                                <input id="cv-csz" wire:model="convertForm.city_state_zip" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                    </div>

                    {{-- Resort / Property --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mt-4">Property Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cv-resort" class="text-[10px] text-crm-t3">Resort Name</label>
                                <input id="cv-resort" wire:model="convertForm.resort_name" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-resort-loc" class="text-[10px] text-crm-t3">Resort City/State</label>
                                <input id="cv-resort-loc" wire:model="convertForm.resort_city_state" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-weeks" class="text-[10px] text-crm-t3">Weeks</label>
                                <input id="cv-weeks" wire:model="convertForm.weeks" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-bedbath" class="text-[10px] text-crm-t3">Bed/Bath</label>
                                <input id="cv-bedbath" wire:model="convertForm.bed_bath" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-usage" class="text-[10px] text-crm-t3">Usage</label>
                                <input id="cv-usage" wire:model="convertForm.usage" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-exchange" class="text-[10px] text-crm-t3">Exchange Group</label>
                                <input id="cv-exchange" wire:model="convertForm.exchange_group" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                    </div>

                    {{-- Pricing --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mt-4">Pricing</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="cv-fee" class="text-[10px] text-crm-t3">Fee</label>
                                <input id="cv-fee" wire:model="convertForm.fee" type="number" step="0.01" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-rental" class="text-[10px] text-crm-t3">Asking Rental</label>
                                <input id="cv-rental" wire:model="convertForm.asking_rental" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-sale" class="text-[10px] text-crm-t3">Asking Sale Price</label>
                                <input id="cv-sale" wire:model="convertForm.asking_sale_price" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                    </div>

                    {{-- Payment --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mt-4">Payment Information</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cv-cardholder" class="text-[10px] text-crm-t3">Name on Card</label>
                                <input id="cv-cardholder" wire:model="convertForm.name_on_card" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-cardtype" class="text-[10px] text-crm-t3">Card Type</label>
                                <input id="cv-cardtype" wire:model="convertForm.card_type" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-bank" class="text-[10px] text-crm-t3">Bank</label>
                                <input id="cv-bank" wire:model="convertForm.bank" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-cardnum" class="text-[10px] text-crm-t3">Card Number</label>
                                <input id="cv-cardnum" wire:model="convertForm.card_number" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-exp" class="text-[10px] text-crm-t3">Exp Date</label>
                                <input id="cv-exp" wire:model="convertForm.exp_date" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-cv2" class="text-[10px] text-crm-t3">CV2</label>
                                <input id="cv-cv2" wire:model="convertForm.cv2" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div class="col-span-2">
                            <label for="cv-billing" class="text-[10px] text-crm-t3">Billing Address</label>
                                <input id="cv-billing" wire:model="convertForm.billing_address" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mt-4">Additional</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cv-vernum" class="text-[10px] text-crm-t3">Verification #</label>
                                <input id="cv-vernum" wire:model="convertForm.verification_num" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                        <div>
                            <label for="cv-login" class="text-[10px] text-crm-t3">Login Info</label>
                                <input id="cv-login" wire:model="convertForm.login_info" type="text" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label for="cv-notes" class="text-[10px] text-crm-t3">Notes</label>
                                <textarea id="cv-notes" wire:model="convertForm.notes" rows="3" class="w-full px-3 py-2 text-sm border border-crm-border rounded-lg"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-crm-border">
                        <button wire:click="$set('showConvertModal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                        <button wire:click="convertToDeal" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition">Create Deal</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteConfirm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 mx-4">
                <h3 class="text-base font-bold text-red-600 mb-3">Confirm Delete</h3>
                <p class="text-sm text-crm-t2 mb-4">Are you sure you want to delete this lead? The lead will be soft-deleted and can be restored if needed.</p>
                <div class="flex justify-end gap-2">
                    <button wire:click="cancelDelete" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="deleteLead" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Delete Lead</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Recent Imports Widget --}}
    @if($recentImports->count() > 0)
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-4">
        <div class="px-4 py-2.5 border-b border-crm-border bg-crm-surface flex items-center justify-between">
            <h3 class="text-sm font-semibold">Recent Imports</h3>
            @if($isAdmin)
                <a href="{{ route('lead-imports') }}" class="text-xs text-blue-600 hover:underline">View All</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-crm-border bg-white">
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">File</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Status</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Progress</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Imported</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Duplicates</th>
                        <th class="text-left px-4 py-2 font-semibold text-crm-t3 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentImports as $import)
                        @php
                            $statusColor = match($import->status) {
                                'completed' => 'bg-emerald-50 text-emerald-600',
                                'processing' => 'bg-blue-50 text-blue-600',
                                'failed' => 'bg-red-50 text-red-600',
                                'cancelled' => 'bg-gray-50 text-gray-600',
                                default => 'bg-yellow-50 text-yellow-600',
                            };
                        @endphp
                        <tr class="border-b border-crm-border">
                            <td class="px-4 py-2.5 font-semibold">{{ \Illuminate\Support\Str::limit($import->original_filename, 30) }}</td>
                            <td class="px-4 py-2.5"><span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $statusColor }}">{{ ucfirst($import->status) }}</span></td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-500 rounded-full transition-all" style="width: {{ $import->progressPercent() }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-crm-t3">{{ $import->progressPercent() }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">{{ number_format($import->successful_rows) }}</td>
                            <td class="px-4 py-2.5">{{ number_format($import->duplicate_rows) }}</td>
                            <td class="px-4 py-2.5 text-crm-t3">{{ $import->created_at?->format('n/j g:i A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
