<div class="flex h-[calc(100vh-3rem)]">
    {{-- Left: Sheet List --}}
    <div class="w-80 border-r border-crm-border bg-crm-surface flex flex-col flex-shrink-0">
        <div class="px-4 py-3 border-b border-crm-border flex items-center justify-between">
            <h3 class="text-sm font-bold">Spreadsheets</h3>
            @if($canEdit)
                <div class="flex gap-1">
                    <button wire:click="createSheet" class="px-2.5 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition">+ New</button>
                    <label class="px-2.5 py-1.5 text-xs font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition cursor-pointer">
                        Upload
                        <input type="file" wire:model="csvUpload" accept=".csv,.txt,.xlsx,.xls" class="hidden">
                    </label>
                </div>
            @endif
        </div>

        @if($csvUpload)
            <div class="px-4 py-2 border-b border-crm-border bg-green-50">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-green-700 truncate">{{ $csvUpload->getClientOriginalName() }}</span>
                    <button wire:click="importFile" class="text-xs font-semibold text-white bg-green-600 rounded px-2 py-1">Import</button>
                </div>
            </div>
        @endif

        <div class="px-3 py-2 border-b border-crm-border">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search sheets..." class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
        </div>

        <div class="flex border-b border-crm-border text-xs">
            @foreach(['all' => 'All', 'my' => 'Mine', 'shared' => 'Shared', 'recent' => 'Recent'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')" class="flex-1 py-2 font-semibold transition {{ $tab === $key ? 'text-green-600 border-b-2 border-green-600' : 'text-crm-t3 hover:text-crm-t1' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="flex-1 overflow-y-auto">
            @forelse($sheets as $sheet)
                <button wire:click="openSheet({{ $sheet->id }})" class="w-full text-left px-4 py-3 border-b border-crm-border transition hover:bg-crm-hover {{ $editingId === $sheet->id ? 'bg-green-50' : '' }}">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🧮</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold truncate">{{ $sheet->title }}</div>
                            <div class="text-[10px] text-crm-t3">
                                {{ $sheet->owner?->name ?? 'Unknown' }} · {{ count($sheet->data ?? []) }} rows · {{ $sheet->updated_at?->diffForHumans() ?? '' }}
                            </div>
                        </div>
                        @if($sheet->permissions->count() > 0)
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 font-semibold">Shared</span>
                        @endif
                    </div>
                </button>
            @empty
                <div class="flex items-center justify-center h-40">
                    <p class="text-sm text-crm-t3">No spreadsheets found.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Right: Sheet Grid / Viewer --}}
    <div class="flex-1 flex flex-col bg-crm-bg">
        @if($editingId)
            @php
                $currentSheet = $sheets->firstWhere('id', $editingId) ?? \App\Models\CrmSheet::find($editingId);
                $isReadOnly = !$canEdit || ($currentSheet && !$currentSheet->userCan(auth()->user(), 'edit'));
            @endphp

            <div class="px-4 py-3 border-b border-crm-border bg-crm-surface flex items-center gap-3">
                <button wire:click="closeEditor" class="flex h-7 w-7 items-center justify-center rounded text-crm-t3 hover:bg-crm-hover transition text-sm">←</button>
                @if($isReadOnly)
                    <span class="px-2 py-0.5 text-[10px] font-bold text-amber-700 bg-amber-100 rounded-full uppercase">Read Only</span>
                @endif
                <input wire:model.blur="editTitle" @if($isReadOnly) disabled @endif
                    class="flex-1 text-sm font-bold bg-transparent border-0 focus:outline-none {{ $isReadOnly ? 'text-crm-t2 cursor-default' : '' }}" placeholder="Sheet title...">
                <div class="flex items-center gap-2">
                    @if(!$isReadOnly)
                        <button wire:click="addRow" class="px-2 py-1 text-[10px] font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded hover:bg-crm-hover transition">+ Row</button>
                        <button wire:click="addColumn" class="px-2 py-1 text-[10px] font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded hover:bg-crm-hover transition">+ Col</button>
                        <button wire:click="saveSheet" class="px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition">Save</button>
                    @endif
                    @if($canEdit && $currentSheet)
                        <button wire:click="openShareModal({{ $currentSheet->id }})" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Share</button>
                        <button wire:click="deleteSheet({{ $currentSheet->id }})" wire:confirm="Delete this sheet?" class="px-2 py-1.5 text-xs text-red-500 hover:text-red-600 transition">Delete</button>
                    @endif
                </div>
            </div>

            {{-- Spreadsheet Grid --}}
            <div class="flex-1 overflow-auto p-4">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="w-10 bg-crm-surface border border-crm-border px-2 py-1.5 text-[10px] text-crm-t3 font-semibold">#</th>
                            @if(!empty($editData) && !empty($editData[0]))
                                @foreach($editData[0] as $ci => $cell)
                                    <th class="bg-crm-surface border border-crm-border px-2 py-1.5 text-[10px] text-crm-t3 font-semibold min-w-[120px]">
                                        {{ chr(65 + ($ci % 26)) }}{{ $ci >= 26 ? ($ci / 26) : '' }}
                                    </th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($editData as $ri => $row)
                            <tr>
                                <td class="bg-crm-surface border border-crm-border px-2 py-1 text-[10px] text-crm-t3 text-center font-mono">
                                    {{ $ri + 1 }}
                                    @if(!$isReadOnly)
                                        <button wire:click="deleteRow({{ $ri }})" class="ml-0.5 text-red-400 hover:text-red-600 text-[8px]">x</button>
                                    @endif
                                </td>
                                @foreach($row as $ci => $cell)
                                    <td class="border border-crm-border p-0">
                                        @if($isReadOnly)
                                            <div class="px-2 py-1.5 text-sm min-h-[32px]">{{ $cell }}</div>
                                        @else
                                            <input type="text" value="{{ $cell }}"
                                                wire:change="updateCell({{ $ri }}, {{ $ci }}, $event.target.value)"
                                                class="w-full px-2 py-1.5 text-sm border-0 focus:outline-none focus:bg-blue-50">
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(empty($editData))
                    <div class="text-center py-12 text-sm text-crm-t3">
                        This sheet is empty.
                        @if(!$isReadOnly) Click <strong>+ Row</strong> and <strong>+ Col</strong> to start building. @endif
                    </div>
                @endif
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="text-4xl opacity-20 mb-3">🧮</div>
                    <p class="text-sm text-crm-t3">Select a spreadsheet or create a new one</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Share Modal --}}
    @if($showShareModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showShareModal', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-sm font-bold mb-4">Share Sheet</h3>
                <div class="mb-3">
                    <label class="text-xs text-crm-t3 uppercase font-semibold">Permission</label>
                    <select wire:model="sharePermission" class="w-full mt-1 rounded-lg border border-crm-border px-3 py-2 text-sm">
                        <option value="view">View Only</option>
                        <option value="edit">Can Edit</option>
                    </select>
                </div>
                <div class="max-h-48 overflow-y-auto border border-crm-border rounded-lg">
                    @foreach($users as $u)
                        @if($u->id !== auth()->id())
                            <label class="flex items-center gap-3 border-b border-crm-border px-3 py-2.5 last:border-0 cursor-pointer hover:bg-crm-hover text-sm">
                                <input type="checkbox" wire:model="shareUserIds" value="{{ $u->id }}" class="h-4 w-4 rounded">
                                <span>{{ $u->name }}</span>
                                <span class="ml-auto text-[10px] text-crm-t3 capitalize">{{ str_replace('_', ' ', $u->role ?? '') }}</span>
                            </label>
                        @endif
                    @endforeach
                </div>
                <div class="flex gap-2 mt-4">
                    <button wire:click="$set('showShareModal', false)" class="flex-1 px-3 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover">Cancel</button>
                    <button wire:click="saveSharing" class="flex-1 px-3 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
