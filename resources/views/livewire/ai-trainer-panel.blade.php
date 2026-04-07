{{-- AI Trainer Side Panel --}}
<div class="bg-crm-card border border-crm-border rounded-xl overflow-hidden" data-training="ai-trainer-panel">
    @if(!$trainerEnabled)
        <div class="p-4 text-center text-xs text-crm-t3">AI Trainer is disabled.</div>
    @elseif(!$entityId)
        <div class="p-6 text-center">
            <div class="text-2xl mb-2 opacity-40">🤖</div>
            <p class="text-xs text-crm-t3 font-medium">Select a {{ $module === 'deals' ? 'deal' : 'lead' }} to get AI coaching</p>
        </div>
    @elseif($coaching)
        {{-- Panel Header --}}
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-4 py-3">
            <div class="flex items-center gap-2">
                <span class="text-white text-sm">🤖</span>
                <span class="text-white text-xs font-bold uppercase tracking-wider">AI Coach</span>
                @if($coaching['coaching'] && isset($coaching['coaching']['status']))
                    @php $cs = $coaching['coaching']['status']; @endphp
                    <span class="ml-auto text-[9px] font-bold px-2 py-0.5 rounded-full
                        {{ match($cs) { 'ready' => 'bg-emerald-400/20 text-emerald-100', 'urgent' => 'bg-red-400/20 text-red-100', 'needs_work' => 'bg-amber-400/20 text-amber-100', 'at_risk' => 'bg-red-400/20 text-red-100', 'closed' => 'bg-gray-400/20 text-gray-200', default => 'bg-white/10 text-white/80' } }}">
                        {{ ucfirst(str_replace('_', ' ', $cs)) }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex border-b border-crm-border bg-crm-surface px-1 py-0.5 gap-0.5">
            @foreach(['coach' => 'Coach', 'mistakes' => 'Issues', 'tips' => 'Score', 'ask' => 'Notes'] as $tk => $tl)
                <button wire:click="$set('activeTab', '{{ $tk }}')"
                    class="flex-1 px-2 py-1.5 text-[9px] font-semibold rounded transition {{ $activeTab === $tk ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                    {{ $tl }}
                    @if($tk === 'mistakes' && count($coaching['mistakes'] ?? []) > 0)
                        <span class="ml-0.5 inline-flex items-center justify-center w-3.5 h-3.5 text-[7px] font-bold text-white bg-red-500 rounded-full">{{ count($coaching['mistakes']) }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="p-3 max-h-[60vh] overflow-y-auto">

            {{-- ═══ COACH TAB ═══ --}}
            @if($activeTab === 'coach')
                {{-- Next Action --}}
                @if($coaching['next_action'] && ($coaching['next_action']['action'] ?? null))
                    <div class="mb-3 p-3 rounded-lg {{ match($coaching['next_action']['priority'] ?? 'low') { 'urgent' => 'bg-red-50 border border-red-200', 'high' => 'bg-amber-50 border border-amber-200', default => 'bg-blue-50 border border-blue-200' } }}">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[9px] font-bold uppercase tracking-wider {{ match($coaching['next_action']['priority'] ?? 'low') { 'urgent' => 'text-red-600', 'high' => 'text-amber-600', default => 'text-blue-600' } }}">
                                {{ $coaching['next_action']['priority'] ?? 'Next' }} Priority
                            </span>
                        </div>
                        <div class="text-sm font-bold text-crm-t1">{{ $coaching['next_action']['label'] ?? 'Review' }}</div>
                        <p class="text-[11px] text-crm-t2 mt-0.5">{{ $coaching['next_action']['reason'] ?? '' }}</p>
                    </div>
                @endif

                {{-- Coaching Tips --}}
                @if($coaching['coaching'] && !empty($coaching['coaching']['tips'] ?? []))
                    <div class="mb-3">
                        <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1.5">Coaching Tips</div>
                        @foreach($coaching['coaching']['tips'] as $tip)
                            <div class="flex items-start gap-2 mb-1.5">
                                <span class="text-blue-500 text-xs flex-shrink-0 mt-0.5">💡</span>
                                <p class="text-[11px] text-crm-t2 leading-relaxed">{{ $tip }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Warnings --}}
                @if($coaching['coaching'] && !empty($coaching['coaching']['warnings'] ?? []))
                    <div class="mb-3">
                        <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1.5">Warnings</div>
                        @foreach($coaching['coaching']['warnings'] as $warn)
                            <div class="flex items-start gap-2 mb-1.5 p-2 rounded-lg bg-red-50 border border-red-100">
                                <span class="text-red-500 text-xs flex-shrink-0">⚠️</span>
                                <p class="text-[11px] text-red-700">{{ $warn }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Checklist --}}
                @if($coaching['coaching'] && isset($coaching['coaching']['checklist']))
                    @php $cl = $coaching['coaching']['checklist']; @endphp
                    <div class="mb-3">
                        <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1.5">Checklist</div>
                        <div class="space-y-1">
                            @php
                                $items = [
                                    ['check' => $cl['has_phone'] ?? false, 'label' => 'Phone number'],
                                    ['check' => count($cl['missing_fields'] ?? []) === 0, 'label' => 'Required fields complete'],
                                    ['check' => $cl['has_notes'] ?? false, 'label' => 'Notes added'],
                                    ['check' => ($cl['note_quality'] ?? 'none') !== 'poor' && ($cl['note_quality'] ?? 'none') !== 'none', 'label' => 'Note quality OK'],
                                    ['check' => $cl['has_disposition'] ?? false, 'label' => 'Disposition set'],
                                    ['check' => $cl['transfer_ready'] ?? $cl['close_ready'] ?? false, 'label' => $module === 'deals' ? 'Ready to verify' : 'Ready to transfer'],
                                ];
                            @endphp
                            @foreach($items as $item)
                                <div class="flex items-center gap-2 text-[11px] {{ $item['check'] ? 'text-emerald-600' : 'text-crm-t3' }}">
                                    @if($item['check'])
                                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                                    @endif
                                    <span class="{{ $item['check'] ? 'line-through opacity-60' : 'font-medium' }}">{{ $item['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Recommendations --}}
                @if(!empty($coaching['recommendations']))
                    <div>
                        <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1.5">Recommendations</div>
                        @foreach($coaching['recommendations'] as $rec)
                            <div class="flex items-start gap-2 mb-2 p-2 rounded-lg bg-crm-surface border border-crm-border">
                                <div class="flex-1 min-w-0">
                                    <div class="text-[11px] font-semibold">{{ $rec['title'] }}</div>
                                    <p class="text-[10px] text-crm-t3 mt-0.5">{{ $rec['message'] }}</p>
                                </div>
                                <button wire:click="dismissRecommendation({{ $rec['id'] }})" class="text-[9px] text-crm-t3 hover:text-red-500 flex-shrink-0">&times;</button>
                            </div>
                        @endforeach
                    </div>
                @endif

            {{-- ═══ MISTAKES TAB ═══ --}}
            @elseif($activeTab === 'mistakes')
                @if(!empty($coaching['mistakes']))
                    <div class="space-y-2">
                        @foreach($coaching['mistakes'] as $mistake)
                            <div class="p-2.5 rounded-lg border {{ match($mistake['severity'] ?? 'low') { 'high' => 'bg-red-50 border-red-200', 'medium' => 'bg-amber-50 border-amber-200', default => 'bg-gray-50 border-gray-200' } }}">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="text-[8px] font-bold px-1.5 py-0.5 rounded uppercase
                                        {{ match($mistake['severity'] ?? 'low') { 'high' => 'bg-red-100 text-red-600', 'medium' => 'bg-amber-100 text-amber-600', default => 'bg-gray-100 text-gray-600' } }}">
                                        {{ $mistake['severity'] ?? 'low' }}
                                    </span>
                                    <span class="text-[9px] font-semibold text-crm-t2">{{ ucfirst(str_replace('_', ' ', $mistake['mistake_type'] ?? '')) }}</span>
                                </div>
                                <p class="text-[11px] text-crm-t2">{{ $mistake['message'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Recent persisted mistakes --}}
                @if(!empty($coaching['recent_mistakes']))
                    <div class="mt-3">
                        <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1.5">Recent Issues</div>
                        @foreach($coaching['recent_mistakes'] as $rm)
                            <div class="flex items-start gap-2 mb-1.5 text-[10px] text-crm-t3">
                                <span class="{{ $rm['severity'] === 'high' ? 'text-red-500' : ($rm['severity'] === 'medium' ? 'text-amber-500' : 'text-gray-400') }}">&#9679;</span>
                                <span>{{ $rm['message'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(empty($coaching['mistakes']) && empty($coaching['recent_mistakes']))
                    <div class="text-center py-6">
                        <div class="text-xl opacity-30 mb-1">✓</div>
                        <p class="text-[11px] text-crm-t3">No issues detected. Good work!</p>
                    </div>
                @endif

            {{-- ═══ SCORE TAB ═══ --}}
            @elseif($activeTab === 'tips')
                {{-- Lead Score --}}
                @if($coaching['lead_score'] ?? null)
                    @php $ls = $coaching['lead_score']; @endphp
                    <div class="mb-3 p-3 rounded-lg border border-crm-border bg-crm-surface">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Lead Score</div>
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full uppercase
                                {{ match($ls['label'] ?? '') { 'hot' => 'bg-red-100 text-red-600', 'warm' => 'bg-amber-100 text-amber-600', 'cold' => 'bg-blue-100 text-blue-600', default => 'bg-gray-100 text-gray-600' } }}">
                                {{ $ls['label'] ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="text-2xl font-extrabold {{ match($ls['label'] ?? '') { 'hot' => 'text-red-500', 'warm' => 'text-amber-500', 'cold' => 'text-blue-500', default => 'text-gray-500' } }}">{{ $ls['score'] ?? 0 }}</div>
                            <div class="flex-1">
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ match($ls['label'] ?? '') { 'hot' => 'bg-red-500', 'warm' => 'bg-amber-500', 'cold' => 'bg-blue-500', default => 'bg-gray-400' } }}" style="width: {{ $ls['score'] ?? 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                        @foreach($ls['reasons'] ?? [] as $r)
                            <div class="flex items-center gap-1.5 text-[10px] text-emerald-600 mb-0.5"><span>+</span> {{ $r }}</div>
                        @endforeach
                        @foreach($ls['risks'] ?? [] as $r)
                            <div class="flex items-center gap-1.5 text-[10px] text-red-500 mb-0.5"><span>-</span> {{ $r }}</div>
                        @endforeach
                        @if($ls['next_action'] ?? null)
                            <div class="mt-2 pt-2 border-t border-crm-border text-[10px] text-blue-600 font-semibold">Next: {{ $ls['next_action'] }}</div>
                        @endif
                    </div>
                @endif

                {{-- Deal Score --}}
                @if($coaching['deal_score'] ?? null)
                    @php $ds = $coaching['deal_score']; @endphp
                    <div class="mb-3 p-3 rounded-lg border border-crm-border bg-crm-surface">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Close Probability</div>
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full uppercase
                                {{ match($ds['label'] ?? '') { 'strong' => 'bg-emerald-100 text-emerald-600', 'medium' => 'bg-amber-100 text-amber-600', 'weak' => 'bg-red-100 text-red-600', default => 'bg-gray-100 text-gray-600' } }}">
                                {{ $ds['label'] ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="text-2xl font-extrabold {{ match($ds['label'] ?? '') { 'strong' => 'text-emerald-500', 'medium' => 'text-amber-500', 'weak' => 'text-red-500', default => 'text-gray-500' } }}">{{ $ds['score'] ?? 0 }}%</div>
                            <div class="flex-1">
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ match($ds['label'] ?? '') { 'strong' => 'bg-emerald-500', 'medium' => 'bg-amber-500', 'weak' => 'bg-red-500', default => 'bg-gray-400' } }}" style="width: {{ $ds['score'] ?? 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                        @foreach($ds['reasons'] ?? [] as $r)
                            <div class="flex items-center gap-1.5 text-[10px] text-emerald-600 mb-0.5"><span>+</span> {{ $r }}</div>
                        @endforeach
                        @foreach($ds['risks'] ?? [] as $r)
                            <div class="flex items-center gap-1.5 text-[10px] text-red-500 mb-0.5"><span>-</span> {{ $r }}</div>
                        @endforeach
                        @if($ds['next_action'] ?? null)
                            <div class="mt-2 pt-2 border-t border-crm-border text-[10px] text-blue-600 font-semibold">Next: {{ $ds['next_action'] }}</div>
                        @endif
                    </div>
                @endif

            {{-- ═══ NOTES TAB (Note Quality Scorer) ═══ --}}
            @elseif($activeTab === 'ask')
                <div class="mb-3">
                    <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1.5">Note Quality Check</div>
                    <p class="text-[10px] text-crm-t3 mb-2">Paste or type your notes below to get a quality score and improvement tips.</p>
                    <textarea wire:model="noteInput" rows="4" class="w-full px-3 py-2 text-xs bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400 resize-none" placeholder="Paste your notes here..."></textarea>
                    <button wire:click="scoreNote" class="mt-1.5 w-full px-3 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">Score My Notes</button>
                </div>

                @if($noteScore)
                    <div class="p-3 rounded-lg border border-crm-border bg-crm-surface">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Quality Score</div>
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full uppercase
                                {{ match($noteScore['label'] ?? '') { 'excellent' => 'bg-emerald-100 text-emerald-600', 'good' => 'bg-blue-100 text-blue-600', 'fair' => 'bg-amber-100 text-amber-600', default => 'bg-red-100 text-red-600' } }}">
                                {{ $noteScore['label'] ?? 'poor' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="text-2xl font-extrabold {{ ($noteScore['score'] ?? 0) >= 75 ? 'text-emerald-500' : (($noteScore['score'] ?? 0) >= 50 ? 'text-blue-500' : (($noteScore['score'] ?? 0) >= 25 ? 'text-amber-500' : 'text-red-500')) }}">{{ $noteScore['score'] ?? 0 }}</div>
                            <div class="flex-1">
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ ($noteScore['score'] ?? 0) >= 75 ? 'bg-emerald-500' : (($noteScore['score'] ?? 0) >= 50 ? 'bg-blue-500' : (($noteScore['score'] ?? 0) >= 25 ? 'bg-amber-500' : 'bg-red-500')) }}" style="width: {{ $noteScore['score'] ?? 0 }}%"></div>
                                </div>
                            </div>
                            <span class="text-[9px] text-crm-t3">{{ $noteScore['word_count'] ?? 0 }} words</span>
                        </div>
                        @foreach($noteScore['feedback'] ?? [] as $fb)
                            <div class="flex items-start gap-1.5 text-[10px] text-crm-t2 mb-1">
                                <span class="text-indigo-500 flex-shrink-0">→</span>
                                <span>{{ $fb }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @else
        <div class="p-6 text-center">
            <div class="text-2xl mb-2 opacity-40">🤖</div>
            <p class="text-xs text-crm-t3 font-medium">AI Coach ready</p>
            <p class="text-[10px] text-crm-t3 mt-1">Select a record to get coaching</p>
        </div>
    @endif
</div>
