<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Script Editor</h2>
            <p class="text-xs text-crm-t3 mt-0.5">Full script management with versioning and integrity checks</p>
        </div>
        @if($isAdmin)
            <button wire:click="startCreate" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">+ New Script</button>
        @endif
    </div>

    {{-- Flash --}}
    @if($flashMsg)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm font-semibold {{ $flashType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
            {{ $flashMsg }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        {{-- Left: Script List --}}
        <div class="lg:col-span-1 space-y-1">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2 px-1">All Scripts</div>
            @foreach(['fronter', 'closer', 'verification'] as $stg)
                @php $stageScripts = $scripts->where('stage', $stg); @endphp
                @if($stageScripts->isNotEmpty())
                    <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-bold mt-3 mb-1 px-2">{{ ucfirst($stg) }}</div>
                    @foreach($stageScripts as $s)
                        <button wire:click="selectScript({{ $s->id }})"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm transition {{ $scriptId === $s->id ? 'bg-blue-50 text-blue-600 border border-blue-200' : 'bg-crm-card border border-crm-border hover:bg-crm-hover' }}">
                            <div class="flex items-center gap-1.5">
                                <span class="font-semibold truncate">{{ $s->name }}</span>
                                @if($s->is_default)
                                    <span class="text-[7px] font-bold px-1 py-0.5 rounded bg-emerald-100 text-emerald-700 uppercase shrink-0">Default</span>
                                @endif
                                @if(!$s->is_active)
                                    <span class="text-[7px] font-bold px-1 py-0.5 rounded bg-gray-100 text-gray-500 uppercase shrink-0">Off</span>
                                @endif
                            </div>
                            <div class="text-[9px] text-crm-t3">{{ ucfirst($s->category) }} · {{ $s->character_count ?? mb_strlen($s->content ?? '') }} chars</div>
                        </button>
                    @endforeach
                @endif
            @endforeach

            {{-- Uncategorized --}}
            @php $other = $scripts->whereNotIn('stage', ['fronter','closer','verification']); @endphp
            @if($other->isNotEmpty())
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-bold mt-3 mb-1 px-2">Other</div>
                @foreach($other as $s)
                    <button wire:click="selectScript({{ $s->id }})"
                        class="w-full text-left px-3 py-2 rounded-lg text-sm transition {{ $scriptId === $s->id ? 'bg-blue-50 text-blue-600 border border-blue-200' : 'bg-crm-card border border-crm-border hover:bg-crm-hover' }}">
                        <span class="font-semibold truncate">{{ $s->name }}</span>
                    </button>
                @endforeach
            @endif
        </div>

        {{-- Right: Editor Panel --}}
        <div class="lg:col-span-3">
            @if($scriptId || $showCreate)
                {{-- Metadata Bar --}}
                <div class="bg-crm-card border border-crm-border rounded-t-lg px-5 py-3 flex items-center justify-between">
                    <div>
                        <div class="text-sm font-bold">{{ $scriptId ? $name : 'New Script' }}</div>
                        <div class="flex items-center gap-2 mt-1 text-[9px]">
                            @if($scriptId)
                                <span class="text-crm-t3">ID: {{ $scriptId }}</span>
                                <span class="text-crm-t3">·</span>
                                @if($currentScript?->version_number)
                                    <span class="text-crm-t3">v{{ $currentScript->version_number }}</span>
                                    <span class="text-crm-t3">·</span>
                                @endif
                                <span class="text-crm-t3">{{ $charCount }} chars</span>
                                <span class="text-crm-t3">·</span>
                                <span class="text-crm-t3">Hash: {{ $contentHash }}</span>
                                <span class="text-crm-t3">·</span>
                                <span class="font-bold {{ $integrityOk ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $integrityOk ? 'Integrity OK' : 'INTEGRITY FAILED' }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($isAdmin)
                            <button wire:click="saveScript" class="px-4 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                                Save Script
                            </button>
                        @endif
                        @if($scriptId && $isAdmin)
                            <button wire:click="duplicateScript" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Duplicate</button>
                        @endif
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="bg-crm-surface border-x border-crm-border px-5 py-1 flex gap-1">
                    @foreach(['editor' => 'Editor', 'preview' => 'Preview', 'raw' => 'Raw Text', 'versions' => 'Version History'] as $tKey => $tLabel)
                        <button wire:click="$set('tab', '{{ $tKey }}')"
                            class="px-3 py-1.5 text-[10px] font-semibold rounded-md transition {{ $tab === $tKey ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                            {{ $tLabel }}
                        </button>
                    @endforeach
                </div>

                <div class="bg-crm-card border border-crm-border rounded-b-lg">
                    {{-- Editor Tab --}}
                    @if($tab === 'editor')
                        <div class="p-5 space-y-4">
                            {{-- Script metadata fields --}}
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div>
                                    <label for="fld-script-name" class="text-[10px] text-crm-t3 uppercase font-semibold">Name</label>
                                    <input id="fld-script-name" name="name" type="text" wire:model="name" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:border-blue-400 focus:outline-none">
                                </div>
                                <div>
                                    <label for="fld-script-slug" class="text-[10px] text-crm-t3 uppercase font-semibold">Slug</label>
                                    <input id="fld-script-slug" name="slug" type="text" wire:model="slug" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:border-blue-400 focus:outline-none">
                                </div>
                                <div>
                                    <label for="fld-script-category" class="text-[10px] text-crm-t3 uppercase font-semibold">Category</label>
                                    <select id="fld-script-category" name="category" wire:model="category" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                        @foreach(['fronter','closer','verification','voicemail','bridge','closing'] as $c)
                                            <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="fld-script-stage" class="text-[10px] text-crm-t3 uppercase font-semibold">Stage</label>
                                    <select id="fld-script-stage" name="stage" wire:model="stage" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                                        @foreach(['fronter','closer','verification'] as $st)
                                            <option value="{{ $st }}">{{ ucfirst($st) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <label for="fld-script-active" class="flex items-center gap-2 text-sm">
                                    <input id="fld-script-active" name="isActive" type="checkbox" wire:model="isActive"> Active
                                </label>
                                <label for="fld-script-default" class="flex items-center gap-2 text-sm">
                                    <input id="fld-script-default" name="isDefault" type="checkbox" wire:model="isDefault"> Default for stage
                                </label>
                            </div>

                            {{-- Full body editor --}}
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label for="fld-script-body" class="text-[10px] text-crm-t3 uppercase font-semibold">Script Body</label>
                                    <span class="text-[10px] text-crm-t3 font-mono" x-data x-text="$wire.body.length + ' chars'"></span>
                                </div>
                                <textarea id="fld-script-body" name="body" wire:model="body" rows="30"
                                    class="w-full px-4 py-3 text-sm bg-white border border-crm-border rounded-lg focus:border-blue-400 focus:outline-none font-mono leading-relaxed resize-y"
                                    style="min-height: 500px;"
                                    placeholder="Paste or type the full script here..."></textarea>
                            </div>
                        </div>
                    @endif

                    {{-- Preview Tab --}}
                    @if($tab === 'preview')
                        <div class="p-5 max-h-[75vh] overflow-y-auto">
                            <div class="text-sm text-crm-t1 whitespace-pre-wrap leading-relaxed">{{ $body }}</div>
                        </div>
                    @endif

                    {{-- Raw Text Tab --}}
                    @if($tab === 'raw')
                        <div class="p-5 max-h-[75vh] overflow-y-auto">
                            <pre class="text-xs text-crm-t2 font-mono bg-gray-50 border border-crm-border rounded-lg p-4 whitespace-pre-wrap break-words">{{ $body }}</pre>
                        </div>
                    @endif

                    {{-- Version History Tab --}}
                    @if($tab === 'versions')
                        <div class="p-5">
                            @if($versions->isEmpty())
                                <p class="text-sm text-crm-t3 text-center py-8">No version history available yet.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($versions as $v)
                                        <div class="flex items-center justify-between bg-crm-surface border border-crm-border rounded-lg px-4 py-3">
                                            <div>
                                                <div class="text-sm font-semibold">v{{ $v->version_number }} — {{ $v->title_snapshot }}</div>
                                                <div class="text-[10px] text-crm-t3">
                                                    {{ $v->character_count }} chars ·
                                                    {{ $v->source_type ?? 'unknown' }} ·
                                                    {{ $v->created_at?->format('M j, Y g:i A') }}
                                                    @if($v->editor) · by {{ $v->editor->name }} @endif
                                                </div>
                                            </div>
                                            @if($isMaster)
                                                <button wire:click="restoreVersion({{ $v->id }})"
                                                    wire:confirm="Restore this version? Current content will be versioned first."
                                                    class="px-3 py-1.5 text-[10px] font-semibold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                                                    Restore
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty state --}}
                <div class="bg-crm-card border border-crm-border rounded-lg p-16 text-center">
                    <div class="text-4xl mb-3">📜</div>
                    <div class="text-sm font-bold mb-1">Select a script to edit</div>
                    <p class="text-xs text-crm-t3">Choose from the list on the left, or create a new script.</p>
                </div>
            @endif
        </div>
    </div>
</div>
