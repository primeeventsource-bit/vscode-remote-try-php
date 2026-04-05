<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Sales Training & Live Close Assist</h2>
        <p class="text-xs text-crm-t3 mt-1">Real-time objection handling, rebuttals, and performance tracking</p>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @php
            $tabs = ['live_assist' => 'Live Close Assist', 'library' => 'Objection Library', 'analytics' => 'Sales Performance'];
            if ($isAdmin) $tabs['manage'] = 'Manage Rebuttals';
        @endphp
        @foreach($tabs as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════
         LIVE CLOSE ASSIST
    ═══════════════════════════════════════════════ --}}
    @if($tab === 'live_assist')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left: Detection + Quick Select --}}
            <div class="lg:col-span-1 space-y-3">
                {{-- Search / Detect --}}
                <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Detect Objection</div>
                    <textarea id="fld-st-search" wire:model.live.debounce.500ms="searchText" rows="2" placeholder="Type what the client said..." class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400"></textarea>
                    @if($detectedObjections->isNotEmpty())
                        <div class="mt-2 text-[10px] text-emerald-600 font-semibold">{{ $detectedObjections->count() }} match{{ $detectedObjections->count() > 1 ? 'es' : '' }} found</div>
                        <div class="mt-1 space-y-1">
                            @foreach($detectedObjections as $det)
                                <button wire:click="selectObjection({{ $det->id }})" class="w-full text-left px-3 py-2 text-xs bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition {{ $selectedObjectionId === $det->id ? 'ring-2 ring-blue-400' : '' }}">
                                    <span class="font-semibold">{{ $det->objection_text }}</span>
                                    <span class="text-[9px] text-blue-500 ml-1">({{ $det->category }})</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Quick Categories --}}
                <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Or Select Category</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(\App\Models\Objection::CATEGORIES as $cat => $label)
                            <button wire:click="$set('searchText', '{{ $cat }}')" class="px-2.5 py-1 text-[10px] font-semibold rounded-lg bg-gray-100 text-crm-t2 hover:bg-blue-50 hover:text-blue-600 transition">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Active Session --}}
                @if($activeSessionId)
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-bold text-emerald-700">Session Active</div>
                            <div class="flex gap-1">
                                <button wire:click="endSession('closed')" class="px-2 py-1 text-[10px] font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700 transition">Close Won</button>
                                <button wire:click="endSession('lost')" class="px-2 py-1 text-[10px] font-semibold text-red-600 bg-red-50 rounded hover:bg-red-100 transition">Lost</button>
                            </div>
                        </div>
                        @if($recentLogs->isNotEmpty())
                            <div class="mt-2 space-y-1">
                                @foreach($recentLogs as $log)
                                    <div class="flex items-center justify-between text-[10px] bg-white rounded p-1.5">
                                        <span class="truncate flex-1">{{ substr($log->objection_text, 0, 30) }}...</span>
                                        <div class="flex gap-1">
                                            <button wire:click="markResult({{ $log->id }}, 'won')" class="px-1.5 py-0.5 rounded {{ $log->result === 'won' ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-crm-t3' }} text-[8px] font-bold">Won</button>
                                            <button wire:click="markResult({{ $log->id }}, 'lost')" class="px-1.5 py-0.5 rounded {{ $log->result === 'lost' ? 'bg-red-500 text-white' : 'bg-gray-100 text-crm-t3' }} text-[8px] font-bold">Lost</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Right: Rebuttal Panel --}}
            <div class="lg:col-span-2">
                @if($selectedObjection)
                    <div class="bg-crm-card border border-crm-border rounded-lg p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="text-sm font-bold">{{ $selectedObjection->objection_text }}</div>
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-blue-50 text-blue-600">{{ \App\Models\Objection::CATEGORIES[$selectedObjection->category] ?? $selectedObjection->category }}</span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach([
                                ['level' => 'level_1', 'label' => 'Soft', 'color' => 'emerald', 'field' => 'rebuttal_level_1'],
                                ['level' => 'level_2', 'label' => 'Closer', 'color' => 'amber', 'field' => 'rebuttal_level_2'],
                                ['level' => 'level_3', 'label' => 'Aggressive', 'color' => 'red', 'field' => 'rebuttal_level_3'],
                            ] as $rb)
                                @if($selectedObjection->{$rb['field']})
                                    <div class="bg-{{ $rb['color'] }}-50 border border-{{ $rb['color'] }}-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-{{ $rb['color'] }}-700">{{ $rb['label'] }} Rebuttal</span>
                                            <button wire:click="logRebuttalUsed({{ $selectedObjection->id }}, '{{ $rb['level'] }}', '{{ addslashes(substr($selectedObjection->{$rb['field']}, 0, 200)) }}')"
                                                class="px-2.5 py-1 text-[10px] font-semibold text-white bg-{{ $rb['color'] }}-600 rounded-lg hover:bg-{{ $rb['color'] }}-700 transition">
                                                Use This
                                            </button>
                                        </div>
                                        <p class="text-sm text-crm-t1 leading-relaxed">{{ $selectedObjection->{$rb['field']} }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- AI Next Line --}}
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-[10px] text-blue-700 uppercase tracking-wider font-semibold">AI Suggested Next Line</div>
                                <button wire:click="getAiNextLine" wire:loading.attr="disabled" wire:target="getAiNextLine"
                                    class="px-3 py-1 text-[10px] font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                                    <span wire:loading.remove wire:target="getAiNextLine">Get AI Line</span>
                                    <span wire:loading wire:target="getAiNextLine">Thinking...</span>
                                </button>
                            </div>
                            @if($aiNextLine)
                                <p class="text-sm text-blue-900 leading-relaxed italic">"{{ $aiNextLine }}"</p>
                                <div class="text-[9px] text-blue-400 mt-1">Generated by AI</div>
                            @else
                                <p class="text-xs text-blue-400">Click "Get AI Line" for an AI-generated response to this objection.</p>
                            @endif
                        </div>

                        {{-- Quick Close --}}
                        <div class="mt-3 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                            <div class="text-[10px] text-purple-700 uppercase tracking-wider font-semibold mb-1">Quick Close Line</div>
                            <p class="text-sm text-purple-900 font-medium italic">"If everything we discussed works for you... would you be ready to move forward today?"</p>
                        </div>
                    </div>
                @else
                    <div class="bg-crm-card border border-crm-border rounded-lg p-12 text-center">
                        <div class="text-4xl mb-3">🎯</div>
                        <div class="text-sm font-bold mb-1">Live Close Assist</div>
                        <p class="text-xs text-crm-t3">Type what the client said or select a category to get instant rebuttals.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         OBJECTION LIBRARY
    ═══════════════════════════════════════════════ --}}
    @if($tab === 'library')
        <div class="space-y-3">
            @foreach(\App\Models\Objection::CATEGORIES as $cat => $catLabel)
                @php $catObjections = $objections->where('category', $cat); @endphp
                @if($catObjections->isNotEmpty())
                    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                        <div class="px-4 py-2.5 bg-crm-surface border-b border-crm-border">
                            <span class="text-xs font-bold">{{ $catLabel }}</span>
                            <span class="text-[10px] text-crm-t3 ml-1">({{ $catObjections->count() }})</span>
                        </div>
                        @foreach($catObjections as $obj)
                            <div class="px-4 py-3 border-b border-crm-border last:border-0 hover:bg-crm-hover transition cursor-pointer" wire:click="selectObjection({{ $obj->id }}); $set('tab', 'live_assist')">
                                <div class="text-sm font-semibold">{{ $obj->objection_text }}</div>
                                <div class="text-[10px] text-crm-t3 mt-0.5">{{ substr($obj->rebuttal_level_1 ?? '', 0, 100) }}{{ strlen($obj->rebuttal_level_1 ?? '') > 100 ? '...' : '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         ANALYTICS
    ═══════════════════════════════════════════════ --}}
    @if($tab === 'analytics')
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Rebuttal Win Rate</div>
                <div class="text-2xl font-extrabold text-emerald-500 mt-1">{{ $analytics['close_rate'] }}%</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Total Sessions</div>
                <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $analytics['total_sessions'] }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Objections Won</div>
                <div class="text-2xl font-extrabold text-purple-500 mt-1">{{ $analytics['won'] }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-400">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Objections Lost</div>
                <div class="text-2xl font-extrabold text-red-500 mt-1">{{ $analytics['lost'] }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Top Objections --}}
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-bold mb-3">Top Objections</div>
                @forelse($analytics['top_objections'] as $to)
                    <div class="flex justify-between text-xs py-1.5 border-b border-crm-border last:border-0">
                        <span class="truncate flex-1">{{ $to['text'] }}</span>
                        <span class="font-bold font-mono ml-2">{{ $to['count'] }}</span>
                    </div>
                @empty
                    <p class="text-xs text-crm-t3">No data yet</p>
                @endforelse
            </div>

            {{-- Best Rebuttals --}}
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-bold mb-3">Best Rebuttals (Win Rate)</div>
                @forelse($analytics['best_rebuttals'] as $br)
                    <div class="py-1.5 border-b border-crm-border last:border-0">
                        <div class="flex justify-between text-xs">
                            <span class="truncate flex-1">{{ $br['text'] }}...</span>
                            <span class="font-bold text-emerald-600 ml-2">{{ $br['pct'] }}%</span>
                        </div>
                        <div class="text-[9px] text-crm-t3">{{ ucfirst($br['level'] ?? '') }} · {{ $br['wins'] }}/{{ $br['total'] }}</div>
                    </div>
                @empty
                    <p class="text-xs text-crm-t3">No data yet</p>
                @endforelse
            </div>

            {{-- Rep Ranking --}}
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="text-sm font-bold mb-3">Rep Ranking</div>
                @forelse($analytics['rep_ranking'] as $i => $rr)
                    <div class="flex items-center gap-2 py-1.5 border-b border-crm-border last:border-0">
                        <span class="text-[10px] font-bold text-crm-t3 w-4">{{ $i + 1 }}</span>
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold text-white" style="background: {{ $rr['color'] ?? '#6b7280' }}">{{ $rr['avatar'] ?? '--' }}</div>
                        <span class="text-xs font-semibold flex-1">{{ $rr['name'] }}</span>
                        <span class="text-[10px] font-bold text-emerald-600">{{ $rr['pct'] }}% ({{ $rr['wins'] }}W)</span>
                    </div>
                @empty
                    <p class="text-xs text-crm-t3">No data yet</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         MANAGE REBUTTALS (Admin)
    ═══════════════════════════════════════════════ --}}
    @if($tab === 'manage' && $isAdmin)
        @if(!$showAddObjection)
            <button wire:click="$set('showAddObjection', true)" class="mb-4 px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">+ Add Objection & Rebuttals</button>
        @else
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="text-xs font-bold mb-3">New Objection & Rebuttals</div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase">Category</label>
                        <select id="fld-obj-cat" wire:model="objectionForm.category" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            @foreach(\App\Models\Objection::CATEGORIES as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase">Keywords (comma-separated)</label>
                        <input id="fld-obj-kw" wire:model="objectionForm.keywords" type="text" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="expensive,cost,money">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-[10px] text-crm-t3 uppercase">Objection Text</label>
                    <input id="fld-obj-text" wire:model="objectionForm.objection_text" type="text" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg" placeholder="What the client says...">
                </div>
                <div class="space-y-2 mb-3">
                    <div>
                        <label class="text-[10px] text-emerald-600 uppercase font-semibold">Soft Rebuttal</label>
                        <textarea id="fld-obj-r1" wire:model="objectionForm.rebuttal_level_1" rows="2" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] text-amber-600 uppercase font-semibold">Closer Rebuttal</label>
                        <textarea id="fld-obj-r2" wire:model="objectionForm.rebuttal_level_2" rows="2" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] text-red-600 uppercase font-semibold">Aggressive Rebuttal</label>
                        <textarea id="fld-obj-r3" wire:model="objectionForm.rebuttal_level_3" rows="2" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg"></textarea>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button wire:click="saveObjection" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save</button>
                    <button wire:click="$set('showAddObjection', false)" class="px-3 py-2 text-xs text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                </div>
            </div>
        @endif

        {{-- Existing objections --}}
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Objection</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Category</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Rebuttals</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Active</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($objections as $obj)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-4 py-2.5 font-semibold">{{ substr($obj->objection_text, 0, 50) }}</td>
                            <td class="text-center px-3 py-2.5 text-xs text-crm-t3">{{ \App\Models\Objection::CATEGORIES[$obj->category] ?? $obj->category }}</td>
                            <td class="text-center px-3 py-2.5 text-xs font-mono">{{ ($obj->rebuttal_level_1 ? 1 : 0) + ($obj->rebuttal_level_2 ? 1 : 0) + ($obj->rebuttal_level_3 ? 1 : 0) }}/3</td>
                            <td class="text-center px-3 py-2.5">
                                <button wire:click="toggleObjection({{ $obj->id }})" class="text-[10px] font-bold px-2 py-0.5 rounded {{ $obj->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' }}">{{ $obj->is_active ? 'Active' : 'Off' }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
