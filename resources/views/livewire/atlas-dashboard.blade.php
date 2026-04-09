<div class="min-h-screen" style="background: #050508;">
    {{-- Atlas Header --}}
    <div class="px-4 sm:px-6 pt-4 pb-2">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #c8a44e, #a07c3a);">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold" style="color: #c8a44e;">ATLAS GLOBAL</h1>
                    <p class="text-xs" style="color: #666;">Lead Intelligence Platform</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('atlas.export-csv', request()->query()) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition" style="background: #c8a44e22; color: #c8a44e; border: 1px solid #c8a44e33;" onmouseover="this.style.background='#c8a44e33'" onmouseout="this.style.background='#c8a44e22'">
                    Export CSV
                </a>
            </div>
        </div>

        {{-- Success / Error Messages --}}
        @if($successMessage)
            <div class="mb-3 px-4 py-2.5 rounded-lg text-sm font-medium" style="background: #10b98122; color: #34d399; border: 1px solid #10b98133;" x-data x-init="setTimeout(() => $wire.clearMessages(), 4000)">
                {{ $successMessage }}
            </div>
        @endif
        @if($errorMessage)
            <div class="mb-3 px-4 py-2.5 rounded-lg text-sm font-medium" style="background: #ef444422; color: #f87171; border: 1px solid #ef444433;" x-data x-init="setTimeout(() => $wire.clearMessages(), 4000)">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Tab Navigation --}}
        <div class="flex gap-1 overflow-x-auto pb-2 scrollbar-hide" style="scrollbar-width: none;">
            @php
                $tabs = [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    ['key' => 'parser', 'label' => 'AI Text Parser', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ['key' => 'pdf', 'label' => 'PDF Upload', 'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                    ['key' => 'phone', 'label' => 'Phone Lookup', 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                    ['key' => 'counties', 'label' => 'Counties', 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['key' => 'leads', 'label' => 'Leads', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                    ['key' => 'skip', 'label' => 'Skip Trace', 'icon' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
                ];
            @endphp
            @foreach($tabs as $tab)
                <button wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition-all"
                    style="{{ $activeTab === $tab['key'] ? 'background: #c8a44e; color: #050508;' : 'background: #0a0a14; color: #888; border: 1px solid #1a1a2e;' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/></svg>
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="px-4 sm:px-6 pb-8">

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 1: DASHBOARD
        ═══════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'dashboard')
            {{-- Stats Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                @php
                    $statCards = [
                        ['label' => 'Total Leads', 'value' => $stats['total'], 'color' => '#c8a44e'],
                        ['label' => 'New', 'value' => $stats['new'], 'color' => '#3b82f6'],
                        ['label' => 'Searched', 'value' => $stats['searched'], 'color' => '#8b5cf6'],
                        ['label' => 'Traced', 'value' => $stats['traced'], 'color' => '#10b981'],
                        ['label' => 'Imported', 'value' => $stats['imported'], 'color' => '#06b6d4'],
                        ['label' => 'AI Parsed', 'value' => $stats['ai_parsed'], 'color' => '#f59e0b'],
                        ['label' => 'With Phone', 'value' => $stats['with_phone'], 'color' => '#22c55e'],
                    ];
                @endphp
                @foreach($statCards as $sc)
                    <div class="rounded-xl p-4" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                        <div class="text-2xl font-bold" style="color: {{ $sc['color'] }};">{{ number_format($sc['value']) }}</div>
                        <div class="text-[11px] mt-1" style="color: #666;">{{ $sc['label'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Feature Cards --}}
            <div class="grid sm:grid-cols-3 gap-3 mb-6">
                <button wire:click="$set('activeTab', 'parser')" class="text-left rounded-xl p-5 transition-all hover:scale-[1.02]" style="background: linear-gradient(135deg, #0a0a14, #111128); border: 1px solid #c8a44e33;">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-3" style="background: #c8a44e22;">
                        <svg class="w-4 h-4" style="color: #c8a44e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="text-sm font-bold" style="color: #e0e0e0;">AI Text Parser</div>
                    <div class="text-[11px] mt-1" style="color: #666;">Paste deed search results and let Claude extract lead data automatically</div>
                </button>
                <button wire:click="$set('activeTab', 'pdf')" class="text-left rounded-xl p-5 transition-all hover:scale-[1.02]" style="background: linear-gradient(135deg, #0a0a14, #111128); border: 1px solid #c8a44e33;">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-3" style="background: #c8a44e22;">
                        <svg class="w-4 h-4" style="color: #c8a44e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="text-sm font-bold" style="color: #e0e0e0;">PDF Upload</div>
                    <div class="text-[11px] mt-1" style="color: #666;">Upload county recorder PDFs for AI-powered deed extraction</div>
                </button>
                <button wire:click="$set('activeTab', 'phone')" class="text-left rounded-xl p-5 transition-all hover:scale-[1.02]" style="background: linear-gradient(135deg, #0a0a14, #111128); border: 1px solid #c8a44e33;">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-3" style="background: #c8a44e22;">
                        <svg class="w-4 h-4" style="color: #c8a44e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <div class="text-sm font-bold" style="color: #e0e0e0;">AI Phone Lookup</div>
                    <div class="text-[11px] mt-1" style="color: #666;">Use Claude web search to find phone numbers for untraced leads</div>
                </button>
            </div>

            {{-- Recent Parse History --}}
            <div class="rounded-xl p-4" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                <h3 class="text-sm font-bold mb-3" style="color: #c8a44e;">Recent Parse History</h3>
                @if($recentLogs->isEmpty())
                    <p class="text-xs" style="color: #555;">No parse history yet. Start by using the AI Text Parser or PDF Upload.</p>
                @else
                    <div class="space-y-2">
                        @foreach($recentLogs as $log)
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg" style="background: #0f0f1a;">
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                        style="{{ $log->parse_type === 'text' ? 'background:#3b82f622;color:#60a5fa;' : ($log->parse_type === 'pdf' ? 'background:#8b5cf622;color:#a78bfa;' : 'background:#10b98122;color:#34d399;') }}">
                                        {{ $log->parse_type }}
                                    </span>
                                    <div>
                                        <span class="text-xs font-medium" style="color: #ccc;">{{ $log->county }}, {{ $log->state }}</span>
                                        <span class="text-[10px] ml-2" style="color: #555;">{{ $log->leads_found }} leads found</span>
                                    </div>
                                </div>
                                <div class="text-[10px]" style="color: #555;">
                                    {{ $log->user?->name ?? 'System' }} &middot; {{ $log->created_at->diffForHumans() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 2: AI TEXT PARSER
        ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'parser')
            <div class="rounded-xl p-5" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                <h3 class="text-sm font-bold mb-1" style="color: #c8a44e;">AI Deed Parser</h3>
                <p class="text-[11px] mb-4" style="color: #666;">Paste raw text from a county recorder deed search. Claude will extract grantee, grantor, date, address, and instrument data.</p>

                <div class="grid sm:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold mb-1" style="color: #888;">County</label>
                        <select wire:model="aiCounty" class="w-full rounded-lg px-3 py-2 text-sm" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                            @foreach($counties as $c)
                                <option value="{{ $c['county'] }}">{{ $c['county'] }}, {{ $c['state'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold mb-1" style="color: #888;">State</label>
                        <select wire:model="aiState" class="w-full rounded-lg px-3 py-2 text-sm" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                            @foreach(['FL','SC','HI','NV','MO','CO','UT','VA','AZ','CA'] as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-[11px] font-semibold mb-1" style="color: #888;">Paste Deed Records</label>
                    <textarea wire:model="pasteText" rows="10" placeholder="Paste raw deed search results here...&#10;&#10;Example:&#10;DEED 20250401-001234 04/01/2025&#10;GRANTOR: WESTGATE RESORTS LTD&#10;GRANTEE: JOHN DOE&#10;123 VACATION BLVD, ORLANDO FL"
                        class="w-full rounded-lg px-3 py-2.5 text-xs font-mono" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e; resize: vertical;"></textarea>
                </div>

                <button wire:click="parseText" wire:loading.attr="disabled"
                    class="px-5 py-2.5 rounded-lg text-sm font-bold transition disabled:opacity-50"
                    style="background: #c8a44e; color: #050508;">
                    <span wire:loading.remove wire:target="parseText">Parse with Claude AI</span>
                    <span wire:loading wire:target="parseText">
                        <svg class="animate-spin inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Parsing...
                    </span>
                </button>

                @if($aiError)
                    <div class="mt-4 px-4 py-2.5 rounded-lg text-xs" style="background: #ef444422; color: #f87171; border: 1px solid #ef444433;">{{ $aiError }}</div>
                @endif

                {{-- AI Results Table --}}
                @if($aiResults && count($aiResults) > 0)
                    <div class="mt-5">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold" style="color: #c8a44e;">{{ count($aiResults) }} Leads Found</h4>
                            <button wire:click="importAiResults" class="px-4 py-1.5 rounded-lg text-xs font-bold transition" style="background: #10b981; color: white;">
                                Import {{ count($aiSelected) }} Selected
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr style="border-bottom: 1px solid #1a1a2e;">
                                        <th class="px-2 py-2 text-left" style="color: #666;">
                                            <input type="checkbox"
                                                @if(count($aiSelected) === count($aiResults)) checked @endif
                                                wire:click="$set('aiSelected', {{ count($aiSelected) === count($aiResults) ? '[]' : json_encode(array_keys($aiResults)) }})"
                                                class="rounded" style="accent-color: #c8a44e;">
                                        </th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Grantee</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Grantor</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Date</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Address</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Instrument</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($aiResults as $idx => $r)
                                        <tr style="border-bottom: 1px solid #0f0f1a;">
                                            <td class="px-2 py-2">
                                                <input type="checkbox" value="{{ $idx }}" wire:model="aiSelected" class="rounded" style="accent-color: #c8a44e;">
                                            </td>
                                            <td class="px-2 py-2" style="color: #e0e0e0;">{{ $r['grantee'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #aaa;">{{ $r['grantor'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #aaa;">{{ $r['date'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #aaa;">{{ $r['address'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #aaa;">{{ $r['instrument'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 3: PDF UPLOAD
        ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'pdf')
            <div class="rounded-xl p-5" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                <h3 class="text-sm font-bold mb-1" style="color: #c8a44e;">PDF Deed Upload</h3>
                <p class="text-[11px] mb-4" style="color: #666;">Upload county recorder PDFs and Claude will extract all deed transfer data from every page.</p>

                <div class="grid sm:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold mb-1" style="color: #888;">County</label>
                        <select wire:model="pdfCounty" class="w-full rounded-lg px-3 py-2 text-sm" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                            @foreach($counties as $c)
                                <option value="{{ $c['county'] }}">{{ $c['county'] }}, {{ $c['state'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold mb-1" style="color: #888;">State</label>
                        <select wire:model="pdfState" class="w-full rounded-lg px-3 py-2 text-sm" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                            @foreach(['FL','SC','HI','NV','MO','CO','UT','VA','AZ','CA'] as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-[11px] font-semibold mb-1" style="color: #888;">Upload PDFs (max 10MB each)</label>
                    <div class="rounded-lg p-6 text-center" style="background: #0f0f1a; border: 2px dashed #1a1a2e;" x-data x-on:dragover.prevent="$el.style.borderColor='#c8a44e'" x-on:dragleave="$el.style.borderColor='#1a1a2e'" x-on:drop.prevent="$el.style.borderColor='#1a1a2e'">
                        <input type="file" wire:model="pdfFiles" multiple accept=".pdf" class="hidden" id="pdfUpload">
                        <label for="pdfUpload" class="cursor-pointer">
                            <svg class="w-8 h-8 mx-auto mb-2" style="color: #c8a44e33;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <p class="text-xs" style="color: #888;">Click or drag PDFs here</p>
                        </label>
                    </div>
                    @if($pdfFiles && count($pdfFiles) > 0)
                        <div class="mt-2 space-y-1">
                            @foreach($pdfFiles as $idx => $file)
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs" style="background: #0f0f1a; color: #aaa;">
                                    <svg class="w-3.5 h-3.5" style="color: #c8a44e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    {{ $file->getClientOriginalName() }}
                                    <span style="color: #555;">({{ number_format($file->getSize() / 1024, 0) }}KB)</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <button wire:click="parsePDFs" wire:loading.attr="disabled"
                    class="px-5 py-2.5 rounded-lg text-sm font-bold transition disabled:opacity-50"
                    style="background: #c8a44e; color: #050508;">
                    <span wire:loading.remove wire:target="parsePDFs">Analyze PDFs with Claude</span>
                    <span wire:loading wire:target="parsePDFs">
                        <svg class="animate-spin inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Analyzing...
                    </span>
                </button>

                @if($pdfError)
                    <div class="mt-4 px-4 py-2.5 rounded-lg text-xs" style="background: #ef444422; color: #f87171; border: 1px solid #ef444433;">{{ $pdfError }}</div>
                @endif

                {{-- PDF Results Table --}}
                @if($pdfResults && count($pdfResults) > 0)
                    <div class="mt-5">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold" style="color: #c8a44e;">{{ count($pdfResults) }} Leads Extracted</h4>
                            <button wire:click="importPdfResults" class="px-4 py-1.5 rounded-lg text-xs font-bold transition" style="background: #10b981; color: white;">
                                Import {{ count($pdfSelected) }} Selected
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr style="border-bottom: 1px solid #1a1a2e;">
                                        <th class="px-2 py-2 text-left" style="color: #666;">
                                            <input type="checkbox"
                                                @if(count($pdfSelected) === count($pdfResults)) checked @endif
                                                wire:click="$set('pdfSelected', {{ count($pdfSelected) === count($pdfResults) ? '[]' : json_encode(array_keys($pdfResults)) }})"
                                                class="rounded" style="accent-color: #c8a44e;">
                                        </th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">File</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Grantee</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Grantor</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Date</th>
                                        <th class="px-2 py-2 text-left font-semibold" style="color: #666;">Instrument</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pdfResults as $idx => $r)
                                        <tr style="border-bottom: 1px solid #0f0f1a;">
                                            <td class="px-2 py-2">
                                                <input type="checkbox" value="{{ $idx }}" wire:model="pdfSelected" class="rounded" style="accent-color: #c8a44e;">
                                            </td>
                                            <td class="px-2 py-2 truncate max-w-[120px]" style="color: #888;">{{ $r['_filename'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #e0e0e0;">{{ $r['grantee'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #aaa;">{{ $r['grantor'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #aaa;">{{ $r['date'] ?? '—' }}</td>
                                            <td class="px-2 py-2" style="color: #aaa;">{{ $r['instrument'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 4: AI PHONE LOOKUP
        ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'phone')
            <div class="rounded-xl p-5" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                <h3 class="text-sm font-bold mb-1" style="color: #c8a44e;">AI Phone Lookup</h3>
                <p class="text-[11px] mb-4" style="color: #666;">Claude uses web search to find phone numbers for leads without contact info. Click "Lookup" next to any untraced lead.</p>

                @if($phoneError)
                    <div class="mb-4 px-4 py-2.5 rounded-lg text-xs" style="background: #ef444422; color: #f87171; border: 1px solid #ef444433;">{{ $phoneError }}</div>
                @endif

                @if($phoneResult)
                    <div class="mb-4 px-4 py-3 rounded-lg" style="background: #10b98122; border: 1px solid #10b98133;">
                        <div class="text-xs font-bold mb-1" style="color: #34d399;">Phone Lookup Result</div>
                        <div class="text-xs" style="color: #aaa;">
                            Phones: {{ implode(', ', $phoneResult['phones'] ?? []) ?: 'None found' }}
                            &middot; Confidence: {{ $phoneResult['confidence'] ?? 'N/A' }}
                        </div>
                        @if(!empty($phoneResult['sources']))
                            <div class="text-[10px] mt-1" style="color: #666;">Sources: {{ implode(', ', $phoneResult['sources']) }}</div>
                        @endif
                    </div>
                @endif

                {{-- Untraced Leads List --}}
                @if($untracedLeads->isEmpty())
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto mb-2" style="color: #1a1a2e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs" style="color: #555;">All leads have phone numbers, or no leads exist yet.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($untracedLeads as $lead)
                            <div class="flex items-center justify-between px-3 py-2.5 rounded-lg" style="background: #0f0f1a;">
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-medium truncate" style="color: #e0e0e0;">{{ $lead->grantee }}</div>
                                    <div class="text-[10px]" style="color: #666;">
                                        {{ $lead->county }}, {{ $lead->state }}
                                        @if($lead->address) &middot; {{ $lead->address }} @endif
                                    </div>
                                </div>
                                <button wire:click="lookupPhone({{ $lead->id }})" wire:loading.attr="disabled" wire:target="lookupPhone({{ $lead->id }})"
                                    class="ml-3 px-3 py-1 rounded-lg text-[11px] font-bold transition flex-shrink-0"
                                    style="background: #c8a44e22; color: #c8a44e; border: 1px solid #c8a44e33;">
                                    <span wire:loading.remove wire:target="lookupPhone({{ $lead->id }})">Lookup</span>
                                    <span wire:loading wire:target="lookupPhone({{ $lead->id }})">
                                        <svg class="animate-spin inline w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    </span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 5: COUNTIES
        ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'counties')
            <div class="mb-4">
                <input wire:model.live.debounce.300ms="countySearch" type="text" placeholder="Search counties, states, cities, or resorts..."
                    class="w-full rounded-lg px-4 py-2.5 text-sm" style="background: #0a0a14; color: #e0e0e0; border: 1px solid #1a1a2e;">
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($filteredCounties as $idx => $c)
                    <div class="rounded-xl p-4" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h4 class="text-sm font-bold" style="color: #e0e0e0;">{{ $c['county'] }} County</h4>
                                <div class="text-[11px]" style="color: #888;">{{ $c['city'] }}, {{ $c['state'] }}</div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold" style="background: #c8a44e22; color: #c8a44e;">{{ $c['state'] }}</span>
                        </div>
                        <div class="text-[11px] mb-2" style="color: #666;">
                            <span class="font-semibold" style="color: #888;">Resorts:</span> {{ $c['resorts'] }}
                        </div>
                        <div class="text-[10px] mb-3 px-2 py-1.5 rounded" style="background: #0f0f1a; color: #888;">
                            <span class="font-semibold" style="color: #c8a44e;">Tip:</span> {{ $c['tip'] }}
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ $c['url'] }}" target="_blank" class="flex-1 text-center px-2 py-1.5 rounded-lg text-[11px] font-semibold transition" style="background: #c8a44e22; color: #c8a44e; border: 1px solid #c8a44e33;">
                                Open Portal
                            </a>
                            @php
                                $origIdx = collect($counties)->search(fn($item) => $item['county'] === $c['county'] && $item['state'] === $c['state']);
                            @endphp
                            <button wire:click="setTabWithCounty('parser', {{ $origIdx }})" class="px-2 py-1.5 rounded-lg text-[11px] font-semibold transition" style="background: #3b82f622; color: #60a5fa; border: 1px solid #3b82f633;">
                                Text
                            </button>
                            <button wire:click="setTabWithCounty('pdf', {{ $origIdx }})" class="px-2 py-1.5 rounded-lg text-[11px] font-semibold transition" style="background: #8b5cf622; color: #a78bfa; border: 1px solid #8b5cf633;">
                                PDF
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 6: LEADS
        ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'leads')
            {{-- Search & Filter Bar --}}
            <div class="flex flex-wrap gap-2 mb-4">
                <input wire:model.live.debounce.300ms="searchQuery" type="text" placeholder="Search grantee, grantor, county..."
                    class="flex-1 min-w-[200px] rounded-lg px-3 py-2 text-sm" style="background: #0a0a14; color: #e0e0e0; border: 1px solid #1a1a2e;">
                <select wire:model.live="filterStatus" class="rounded-lg px-3 py-2 text-sm" style="background: #0a0a14; color: #e0e0e0; border: 1px solid #1a1a2e;">
                    <option value="ALL">All Statuses</option>
                    <option value="new">New</option>
                    <option value="searched">Searched</option>
                    <option value="traced">Traced</option>
                    <option value="imported">Imported</option>
                    <option value="no_contact">No Contact</option>
                    <option value="duplicate">Duplicate</option>
                    <option value="bad_data">Bad Data</option>
                </select>
                <button wire:click="$toggle('showAddForm')" class="px-3 py-2 rounded-lg text-xs font-bold transition" style="background: #c8a44e; color: #050508;">
                    + Add Lead
                </button>
            </div>

            {{-- Manual Add Form --}}
            @if($showAddForm)
                <div class="rounded-xl p-4 mb-4" style="background: #0a0a14; border: 1px solid #c8a44e33;">
                    <h4 class="text-xs font-bold mb-3" style="color: #c8a44e;">Add Lead Manually</h4>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">Grantee *</label>
                            <input wire:model="formGrantee" type="text" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                            @error('formGrantee') <span class="text-[10px]" style="color: #f87171;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">Grantor *</label>
                            <input wire:model="formGrantor" type="text" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                            @error('formGrantor') <span class="text-[10px]" style="color: #f87171;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">County</label>
                            <input wire:model="formCounty" type="text" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">State</label>
                            <input wire:model="formState" type="text" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">Deed Date</label>
                            <input wire:model="formDate" type="date" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">Address</label>
                            <input wire:model="formAddress" type="text" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">Instrument #</label>
                            <input wire:model="formInstrument" type="text" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold mb-1" style="color: #888;">Deed Type</label>
                            <input wire:model="formDeedType" type="text" class="w-full rounded-lg px-3 py-1.5 text-xs" style="background: #0f0f1a; color: #e0e0e0; border: 1px solid #1a1a2e;">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="saveLead" class="px-4 py-1.5 rounded-lg text-xs font-bold" style="background: #10b981; color: white;">Save Lead</button>
                        <button wire:click="$set('showAddForm', false)" class="px-4 py-1.5 rounded-lg text-xs font-semibold" style="background: #1a1a2e; color: #888;">Cancel</button>
                    </div>
                </div>
            @endif

            {{-- Leads Table --}}
            <div class="rounded-xl overflow-hidden" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr style="border-bottom: 1px solid #1a1a2e;">
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">Grantee</th>
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">Grantor</th>
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">County</th>
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">Date</th>
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">Phone 1</th>
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">Status</th>
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">Source</th>
                                <th class="px-3 py-2.5 text-left font-semibold" style="color: #666;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leads as $lead)
                                <tr style="border-bottom: 1px solid #0f0f1a;" class="hover:bg-white/[0.02]">
                                    <td class="px-3 py-2" style="color: #e0e0e0;">
                                        <div class="font-medium">{{ $lead->grantee }}</div>
                                        @if($lead->address)
                                            <div class="text-[10px] truncate max-w-[160px]" style="color: #555;">{{ $lead->address }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2" style="color: #aaa;">{{ $lead->grantor }}</td>
                                    <td class="px-3 py-2" style="color: #aaa;">{{ $lead->county }}, {{ $lead->state }}</td>
                                    <td class="px-3 py-2" style="color: #aaa;">{{ $lead->deed_date?->format('m/d/Y') ?? '—' }}</td>
                                    <td class="px-3 py-2" style="color: {{ $lead->phone_1 ? '#34d399' : '#555' }};">
                                        {{ $lead->phone_1 ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <select wire:change="updateStatus({{ $lead->id }}, $event.target.value)"
                                            class="rounded px-2 py-0.5 text-[10px] font-bold"
                                            style="background: {{ $lead->getStatusColor() }}22; color: {{ $lead->getStatusColor() }}; border: 1px solid {{ $lead->getStatusColor() }}33;">
                                            @foreach(['new','searched','traced','imported','no_contact','duplicate','bad_data'] as $status)
                                                <option value="{{ $status }}" {{ $lead->status === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: #1a1a2e; color: #888;">{{ $lead->source }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            @if(!$lead->phone_1)
                                                <button wire:click="lookupPhone({{ $lead->id }})" class="px-2 py-0.5 rounded text-[10px] font-semibold" style="background: #c8a44e22; color: #c8a44e;">
                                                    Phone
                                                </button>
                                            @endif
                                            <button wire:click="deleteLead({{ $lead->id }})" wire:confirm="Delete this lead?" class="px-2 py-0.5 rounded text-[10px] font-semibold" style="background: #ef444422; color: #f87171;">
                                                Del
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-8 text-center" style="color: #555;">
                                        No leads found. Import from AI parser or add manually.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($leads->hasPages())
                    <div class="px-4 py-3" style="border-top: 1px solid #1a1a2e;">
                        {{ $leads->links() }}
                    </div>
                @endif
            </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 7: SKIP TRACE REFERENCE
        ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'skip')
            <div class="rounded-xl p-5" style="background: #0a0a14; border: 1px solid #1a1a2e;">
                <h3 class="text-sm font-bold mb-1" style="color: #c8a44e;">Skip Trace Services</h3>
                <p class="text-[11px] mb-4" style="color: #666;">External services for bulk phone lookup and skip tracing. Use these when the AI phone lookup doesn't return results.</p>

                <div class="grid sm:grid-cols-2 gap-3">
                    @php
                        $skipServices = [
                            ['name' => 'TLO (TransUnion)', 'desc' => 'Premium skip trace with phone, email, and address data. Industry standard for collections and timeshare.', 'type' => 'Premium', 'color' => '#c8a44e'],
                            ['name' => 'Skip Smasher', 'desc' => 'Batch skip tracing with CSV upload. Good for bulk phone lookups on large lead lists.', 'type' => 'Batch', 'color' => '#3b82f6'],
                            ['name' => 'Accurint (LexisNexis)', 'desc' => 'Comprehensive people search with deep public records and phone data.', 'type' => 'Premium', 'color' => '#c8a44e'],
                            ['name' => 'BeenVerified / Spokeo', 'desc' => 'Consumer-grade people search. Useful for quick lookups but not as reliable for timeshare leads.', 'type' => 'Basic', 'color' => '#10b981'],
                            ['name' => 'BatchSkipTracing.com', 'desc' => 'Affordable batch processing. Upload CSV of names/addresses, get phone numbers back.', 'type' => 'Batch', 'color' => '#3b82f6'],
                        ];
                    @endphp
                    @foreach($skipServices as $svc)
                        <div class="rounded-lg p-4" style="background: #0f0f1a; border: 1px solid #1a1a2e;">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="text-xs font-bold" style="color: #e0e0e0;">{{ $svc['name'] }}</h4>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold" style="background: {{ $svc['color'] }}22; color: {{ $svc['color'] }};">{{ $svc['type'] }}</span>
                            </div>
                            <p class="text-[11px]" style="color: #666;">{{ $svc['desc'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 px-4 py-3 rounded-lg" style="background: #c8a44e11; border: 1px solid #c8a44e22;">
                    <p class="text-[11px] font-medium" style="color: #c8a44e;">Pro Tip: Use the AI Phone Lookup tab first (free via Claude). Only use paid services for leads that AI couldn't trace.</p>
                </div>
            </div>
        @endif

    </div>
</div>
