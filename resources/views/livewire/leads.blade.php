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
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Leads</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ $leads->count() }} total leads</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="$set('showAddModal', true)" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">+ Add Lead</button>
            <button wire:click="$set('showImportModal', true)" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">Import CSV</button>
        </div>
    </div>

    {{-- Search + Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search leads..." class="flex-1 min-w-[200px] px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
        <select wire:model.live="resortFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All Resorts</option>
            @foreach($resorts as $r)
                <option value="{{ $r }}">{{ $r }}</option>
            @endforeach
        </select>
        <select wire:model.live="fronterFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All Fronter Lists</option>
            <option value="unassigned">Unassigned Leads</option>
            @foreach($fronters as $f)
                <option value="{{ $f->id }}">{{ $f->name }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5">
            @foreach(['all' => 'All', 'undisposed' => 'Undisposed', 'transferred' => 'Transferred'] as $key => $label)
                <button wire:click="$set('filter', '{{ $key }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $filter === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-crm-border bg-crm-card p-2">
        <button @click="selectAllVisibleLocal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-crm-border hover:bg-crm-hover transition">Select All Visible</button>
        <button @click="clearSelectionLocal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-crm-border hover:bg-crm-hover transition">Clear Selection</button>
        <span class="text-xs text-crm-t3">{{ count($selectedLeads) }} selected</span>
        <select wire:model="bulkFronter" class="px-3 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="">Assign selected to fronter...</option>
            @foreach($fronters as $f)
                <option value="{{ $f->id }}">{{ $f->name }}</option>
            @endforeach
        </select>
        <button wire:click="assignSelectedToFronter" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Assign Selected</button>
        <button wire:click="unassignSelectedLeads" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition">Take Selected Off Rep</button>
        @error('bulkFronter')
            <span class="text-xs font-semibold text-red-600">{{ $message }}</span>
        @enderror
    </div>

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
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-4">
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
                        <tr wire:click="selectLead({{ $lead->id }})" class="border-b border-crm-border cursor-pointer transition {{ $selectedLead === $lead->id || in_array($lead->id, $selectedLeads) ? 'bg-blue-50 border-l-2 border-l-blue-500' : 'hover:bg-crm-hover' }}">
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
                                    <a href="sip:{{ $lead->phone1 }}" class="text-blue-600 font-semibold font-mono" wire:click.stop>{{ $lead->phone1 }}</a>
                                @else
                                    <span class="text-crm-t3">--</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($lead->phone2)
                                    <a href="sip:{{ $lead->phone2 }}" class="text-blue-600 font-semibold font-mono" wire:click.stop>{{ $lead->phone2 }}</a>
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

    {{-- Detail Panel --}}
    @if($active)
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold">{{ $active->owner_name }}</h3>
                <button wire:click="selectLead({{ $active->id }})" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Resort</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->resort }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 1</div>
                    <div class="mt-0.5">
                        @if($active->phone1)
                            <a href="sip:{{ $active->phone1 }}" class="text-blue-600 font-semibold font-mono text-sm">{{ $active->phone1 }}</a>
                        @else <span class="text-crm-t3 text-sm">--</span> @endif
                    </div>
                </div>
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 2</div>
                    <div class="mt-0.5">
                        @if($active->phone2)
                            <a href="sip:{{ $active->phone2 }}" class="text-blue-600 font-semibold font-mono text-sm">{{ $active->phone2 }}</a>
                        @else <span class="text-crm-t3 text-sm">--</span> @endif
                    </div>
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
                @if($active->callback_date)
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Callback Date</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $active->callback_date->format('n/j/Y g:i A') }}</div>
                </div>
                @endif
                <div>
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Source</div>
                    <div class="text-sm mt-0.5">{{ $active->source ?? 'N/A' }}</div>
                </div>
            </div>

            {{-- Disposition Buttons --}}
            <div class="border-t border-crm-border pt-4">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-3">Set Disposition</div>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="setDisposition({{ $active->id }}, 'Wrong Number')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">Wrong Number</button>
                    <button wire:click="setDisposition({{ $active->id }}, 'Disconnected')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition">Disconnected</button>
                    <button wire:click="setDisposition({{ $active->id }}, 'Right Number')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 transition">Right Number</button>
                    <button wire:click="setDisposition({{ $active->id }}, 'Left Voice Mail')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-50 text-sky-600 border border-sky-200 hover:bg-sky-100 transition">Left Voice Mail</button>
                </div>

                {{-- Callback --}}
                <div class="flex items-center gap-2 mt-3">
                    <input wire:model="callbackDateTime" type="datetime-local" class="px-3 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none">
                    <button wire:click="doCallback({{ $active->id }})" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 transition">Callback</button>
                </div>

                {{-- Transfer to Closer --}}
                <div class="flex items-center gap-2 mt-3">
                    <select wire:model="transferCloser" class="px-3 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none">
                        <option value="">Select Closer...</option>
                        @foreach($closers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <button wire:click="transferToCloser({{ $active->id }})" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-50 text-purple-600 border border-purple-200 hover:bg-purple-100 transition">Transfer to Closer</button>
                </div>
            </div>
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
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Owner Name</label>
                            <input wire:model="newLead.owner_name" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Resort</label>
                            <input wire:model="newLead.resort" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 1</label>
                            <input wire:model="newLead.phone1" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Phone 2</label>
                            <input wire:model="newLead.phone2" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">City</label>
                            <input wire:model="newLead.city" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">State</label>
                            <input wire:model="newLead.st" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Zip</label>
                            <input wire:model="newLead.zip" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider">Resort Location</label>
                        <input wire:model="newLead.resort_location" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showAddModal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="saveLead" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Lead</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Import CSV Modal --}}
    @if($showImportModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showImportModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">Import Leads</h3>
                    <button wire:click="$set('showImportModal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>

                <p class="text-xs text-crm-t3 mb-3">Upload a CSV file (max 10,000 rows). Expected columns: Resort, Owner Name, Phone 1, Phone 2, City, State, Zip, Resort Location</p>

                {{-- Error display --}}
                @if($importError)
                    <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {{ $importError }}
                    </div>
                @endif

                @if($importStatus)
                    <div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                        {{ $importStatus }}
                    </div>
                @endif

                {{-- File Upload via Livewire --}}
                <div class="mb-3">
                    <label class="flex min-h-[120px] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-crm-border bg-crm-surface hover:border-blue-400 px-4 py-6 text-center transition">
                        <div class="text-sm font-semibold text-crm-t2">Click to select CSV file</div>
                        <div class="mt-1 text-xs text-crm-t3">or drag and drop here</div>
                        @if($leadFile)
                            <div class="mt-3 rounded bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700">
                                {{ $leadFile->getClientOriginalName() }} ({{ number_format($leadFile->getSize() / 1024, 1) }} KB)
                            </div>
                        @endif
                        <input type="file" wire:model="leadFile" accept=".csv,.txt" class="hidden">
                    </label>
                    @error('leadFile')
                        <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <details class="mb-3">
                    <summary class="cursor-pointer text-xs font-semibold text-crm-t2">Or paste CSV manually</summary>
                    <textarea wire:model="csvText" rows="6" class="mt-2 w-full px-3 py-2 text-sm font-mono bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Resort,Owner Name,Phone1,Phone2,City,St,Zip,Resort Location"></textarea>
                </details>

                <div class="flex justify-end gap-2">
                    <button wire:click="$set('showImportModal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="importLeads" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60 transition">
                        <span wire:loading.remove wire:target="importLeads">Import</span>
                        <span wire:loading wire:target="importLeads">Importing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
