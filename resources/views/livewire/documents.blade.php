<div class="flex h-[calc(100vh-3rem)]">
    {{-- Left: Document List --}}
    <div class="w-80 border-r border-crm-border bg-crm-surface flex flex-col flex-shrink-0">
        <div class="px-4 py-3 border-b border-crm-border flex items-center justify-between">
            <h3 class="text-sm font-bold">Documents</h3>
            @if($canEdit)
                <div class="flex gap-1">
                    <button wire:click="createDocument" class="px-2.5 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">+ New</button>
                    <label class="px-2.5 py-1.5 text-xs font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition cursor-pointer">
                        Upload
                        <input type="file" wire:model="fileUpload" accept=".pdf,.doc,.docx,.txt,.rtf" class="hidden">
                    </label>
                </div>
            @endif
        </div>

        @if($fileUpload)
            <div class="px-4 py-2 border-b border-crm-border bg-blue-50">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-blue-700 truncate">{{ $fileUpload->getClientOriginalName() }}</span>
                    <button wire:click="uploadDocument" class="text-xs font-semibold text-white bg-blue-600 rounded px-2 py-1">Save</button>
                </div>
            </div>
        @endif

        {{-- Search --}}
        <div class="px-3 py-2 border-b border-crm-border">
            <input id="fld-search" wire:model.live.debounce.300ms="search" type="text" placeholder="Search documents..." class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
        </div>

        {{-- Tabs --}}
        <div class="flex border-b border-crm-border text-xs">
            @foreach(['all' => 'All', 'my' => 'Mine', 'shared' => 'Shared', 'recent' => 'Recent'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')" class="flex-1 py-2 font-semibold transition {{ $tab === $key ? 'text-blue-600 border-b-2 border-blue-600' : 'text-crm-t3 hover:text-crm-t1' }}">{{ $label }}</button>
            @endforeach
        </div>

        {{-- List --}}
        <div class="flex-1 overflow-y-auto">
            @forelse($documents as $doc)
                <button wire:click="openDocument({{ $doc->id }})" class="w-full text-left px-4 py-3 border-b border-crm-border transition hover:bg-crm-hover {{ $editingId === $doc->id ? 'bg-blue-50' : '' }}">
                    <div class="flex items-center gap-2">
                        <span class="text-base">{{ $doc->is_uploaded ? '📎' : '📄' }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold truncate">{{ $doc->title }}</div>
                            <div class="text-[10px] text-crm-t3">
                                {{ $doc->owner?->name ?? 'Unknown' }} · {{ $doc->updated_at?->diffForHumans() ?? '' }}
                            </div>
                        </div>
                        @if($doc->permissions->count() > 0)
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 font-semibold">Shared</span>
                        @endif
                    </div>
                </button>
            @empty
                <div class="flex items-center justify-center h-40">
                    <p class="text-sm text-crm-t3">No documents found.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Right: Editor / Viewer --}}
    <div class="flex-1 flex flex-col bg-crm-bg">
        @if($editingId)
            @php
                $currentDoc = $documents->firstWhere('id', $editingId) ?? \App\Models\CrmDocument::find($editingId);
                $isReadOnly = !$canEdit || ($currentDoc && !$currentDoc->userCan(auth()->user(), 'edit'));
            @endphp

            {{-- Editor Header --}}
            <div class="px-4 py-3 border-b border-crm-border bg-crm-surface flex items-center gap-3">
                <button wire:click="closeEditor" class="flex h-7 w-7 items-center justify-center rounded text-crm-t3 hover:bg-crm-hover transition text-sm">←</button>
                @if($isReadOnly)
                    <span class="px-2 py-0.5 text-[10px] font-bold text-amber-700 bg-amber-100 rounded-full uppercase">Read Only</span>
                @endif
                <input id="doc-edit-title" wire:model.blur="editTitle" @if($isReadOnly) disabled @endif
                    class="flex-1 text-sm font-bold bg-transparent border-0 focus:outline-none {{ $isReadOnly ? 'text-crm-t2 cursor-default' : '' }}" placeholder="Document title...">
                <div class="flex items-center gap-2">
                    @if(!$isReadOnly)
                        <button wire:click="saveDocument" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save</button>
                    @endif
                    @if($canEdit && $currentDoc)
                        <button wire:click="openShareModal({{ $currentDoc->id }})" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Share</button>
                        <button wire:click="deleteDocument({{ $currentDoc->id }})" wire:confirm="Delete this document?" class="px-2 py-1.5 text-xs text-red-500 hover:text-red-600 transition">Delete</button>
                    @endif
                </div>
            </div>

            {{-- Document Body --}}
            <div class="flex-1 overflow-y-auto p-6">
                @if($currentDoc?->is_uploaded)
                    <div class="max-w-2xl mx-auto text-center py-12">
                        <div class="text-4xl mb-4">📎</div>
                        <h3 class="text-lg font-bold">{{ $currentDoc->original_filename }}</h3>
                        <p class="text-sm text-crm-t3 mt-1">{{ number_format(($currentDoc->file_size ?? 0) / 1024, 1) }} KB · {{ $currentDoc->mime_type }}</p>
                        @if($currentDoc->stored_path)
                            <a href="{{ asset('storage/' . $currentDoc->stored_path) }}" target="_blank" class="inline-block mt-4 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Download File</a>
                        @endif
                    </div>
                @else
                    <div class="max-w-3xl mx-auto">
                        <textarea id="doc-edit-content" wire:model.blur="editContent" @if($isReadOnly) disabled @endif
                            class="w-full min-h-[60vh] text-sm leading-relaxed bg-white border border-crm-border rounded-xl p-6 focus:outline-none focus:border-blue-400 resize-none {{ $isReadOnly ? 'bg-gray-50 cursor-default' : '' }}"
                            placeholder="Start writing..."></textarea>
                    </div>
                @endif
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="text-4xl opacity-20 mb-3">📄</div>
                    <p class="text-sm text-crm-t3">Select a document or create a new one</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Share Modal --}}
    @if($showShareModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showShareModal', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-sm font-bold mb-4">Share Document</h3>
                <div class="mb-3">
                    <label for="fld-sharePermission" class="text-xs text-crm-t3 uppercase font-semibold">Permission</label>
                                <select id="fld-sharePermission" wire:model="sharePermission" class="w-full mt-1 rounded-lg border border-crm-border px-3 py-2 text-sm">
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
                    <button wire:click="saveSharing" class="flex-1 px-3 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
