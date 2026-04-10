<div class="min-h-screen" style="background:#050508;color:#e0e0e0;" x-data="{ mobileTab: false }">
    {{-- ═══ Flash Messages ═══ --}}
    @if($successMessage)
    <div class="fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-2xl text-sm font-semibold animate-pulse cursor-pointer"
         style="background:#0fff50;color:#050508;" wire:click="clearMessages">
        {{ $successMessage }}
    </div>
    @endif
    @if($errorMessage)
    <div class="fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-2xl text-sm font-semibold cursor-pointer"
         style="background:#e94560;color:#fff;" wire:click="clearMessages">
        {{ $errorMessage }}
    </div>
    @endif

    {{-- ═══ Header ═══ --}}
    <div class="px-4 sm:px-6 pt-6 pb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight" style="color:#c8a44e;">ATLAS GLOBAL <span style="color:#00d4ff;">v4</span></h1>
                <p class="text-xs mt-1 opacity-60">Lead Intelligence Platform &mdash; Skip Trace &amp; Pipeline</p>
            </div>
            <a href="{{ route('atlas.export-csv') }}" class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all hover:scale-105"
               style="background:#c8a44e;color:#050508;">
                CSV Export
            </a>
        </div>
    </div>

    {{-- ═══ Tab Navigation ═══ --}}
    <div class="px-4 sm:px-6 mt-2 mb-4">
        {{-- Mobile tab selector --}}
        <div class="sm:hidden relative">
            <button @click="mobileTab = !mobileTab" class="w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-bold"
                    style="background:#0d0d12;border:1px solid #c8a44e33;">
                <span style="color:#c8a44e;">
                    @switch($activeTab)
                        @case('dashboard') Dashboard @break
                        @case('sheets') Google Sheets @break
                        @case('trace') Skip Trace @break
                        @case('parser') AI Parser @break
                        @case('pdf') PDF Upload @break
                        @case('counties') Counties @break
                        @case('leads') Leads @break
                        @case('settings') Settings @break
                    @endswitch
                </span>
                <svg class="w-4 h-4" style="color:#c8a44e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="mobileTab" @click.away="mobileTab=false" x-cloak
                 class="absolute top-full left-0 right-0 z-40 mt-1 rounded-xl shadow-2xl overflow-hidden"
                 style="background:#0d0d12;border:1px solid #c8a44e33;">
                @foreach(['dashboard'=>'Dashboard','sheets'=>'Google Sheets','trace'=>'Skip Trace','parser'=>'AI Parser','pdf'=>'PDF Upload','counties'=>'Counties','leads'=>'Leads','settings'=>'Settings'] as $tab => $label)
                <button wire:click="$set('activeTab','{{ $tab }}')" @click="mobileTab=false"
                        class="block w-full text-left px-4 py-3 text-sm transition-colors {{ $activeTab === $tab ? 'font-bold' : 'opacity-60 hover:opacity-100' }}"
                        style="{{ $activeTab === $tab ? 'background:#c8a44e22;color:#c8a44e;' : '' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>
        {{-- Desktop tabs --}}
        <div class="hidden sm:flex gap-1 p-1 rounded-xl overflow-x-auto" style="background:#0d0d12;">
            @foreach(['dashboard'=>'Dashboard','sheets'=>'Sheets Upload','trace'=>'Skip Trace','parser'=>'AI Parser','pdf'=>'PDF Upload','counties'=>'Counties','leads'=>'Leads','settings'=>'Settings'] as $tab => $label)
            <button wire:click="$set('activeTab','{{ $tab }}')"
                    class="px-4 py-2.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ $activeTab === $tab ? '' : 'opacity-50 hover:opacity-80' }}"
                    style="{{ $activeTab === $tab ? 'background:#c8a44e;color:#050508;' : '' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    <div class="px-4 sm:px-6 pb-12">

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 1: DASHBOARD
    ═══════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'dashboard')
    <div class="space-y-6">
        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            @foreach([
                ['label'=>'Total Leads','val'=>$stats['total'],'color'=>'#c8a44e'],
                ['label'=>'New','val'=>$stats['new'],'color'=>'#e94560'],
                ['label'=>'Searched','val'=>$stats['searched'],'color'=>'#f5a623'],
                ['label'=>'Traced','val'=>$stats['traced'],'color'=>'#00d4ff'],
                ['label'=>'Imported','val'=>$stats['imported'],'color'=>'#0fff50'],
            ] as $s)
            <div class="rounded-xl p-4 text-center" style="background:#0d0d12;border:1px solid {{ $s['color'] }}33;">
                <p class="text-2xl sm:text-3xl font-black" style="color:{{ $s['color'] }};">{{ $s['val'] }}</p>
                <p class="text-xs mt-1 opacity-60">{{ $s['label'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Quick Actions --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <button wire:click="$set('activeTab','sheets')" class="rounded-xl p-4 text-center transition-all hover:scale-[1.02]" style="background:#0d0d12;border:1px solid #0fff5033;">
                <p class="text-2xl">📊</p>
                <p class="text-xs font-bold mt-1" style="color:#0fff50;">Import Sheets</p>
            </button>
            <button wire:click="$set('activeTab','trace')" class="rounded-xl p-4 text-center transition-all hover:scale-[1.02]" style="background:#0d0d12;border:1px solid #00d4ff33;">
                <p class="text-2xl">📞</p>
                <p class="text-xs font-bold mt-1" style="color:#00d4ff;">Skip Trace</p>
            </button>
            <button wire:click="$set('activeTab','parser')" class="rounded-xl p-4 text-center transition-all hover:scale-[1.02]" style="background:#0d0d12;border:1px solid #c8a44e33;">
                <p class="text-2xl">🤖</p>
                <p class="text-xs font-bold mt-1" style="color:#c8a44e;">AI Parser</p>
            </button>
            <button wire:click="$set('activeTab','leads')" class="rounded-xl p-4 text-center transition-all hover:scale-[1.02]" style="background:#0d0d12;border:1px solid #e9456033;">
                <p class="text-2xl">👥</p>
                <p class="text-xs font-bold mt-1" style="color:#e94560;">View Leads</p>
            </button>
        </div>

        {{-- Recent Activity --}}
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #c8a44e22;">
            <h3 class="text-sm font-bold mb-3" style="color:#c8a44e;">Recent Activity</h3>
            @forelse($recentLogs as $log)
            <div class="flex items-center justify-between py-2 border-b" style="border-color:#ffffff0a;">
                <div class="text-xs">
                    <span class="font-bold" style="color:#00d4ff;">{{ $log->parse_type }}</span>
                    <span class="opacity-50 ml-2">{{ $log->leads_found ?? 0 }} found</span>
                    @if($log->leads_traced)<span class="ml-2" style="color:#0fff50;">{{ $log->leads_traced }} traced</span>@endif
                    @if($log->cost_estimate)<span class="ml-2 opacity-40">${{ number_format($log->cost_estimate,2) }}</span>@endif
                </div>
                <span class="text-xs opacity-40">{{ $log->created_at?->diffForHumans() }}</span>
            </div>
            @empty
            <p class="text-xs opacity-40">No activity yet. Import leads or run a skip trace to get started.</p>
            @endforelse
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 2: GOOGLE SHEETS / CSV UPLOAD
    ═══════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'sheets')
    <div class="space-y-6">
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #0fff5033;">
            <h3 class="text-sm font-bold mb-1" style="color:#0fff50;">Google Sheets / CSV Upload</h3>
            <p class="text-xs opacity-40 mb-4">Export your Google Sheet as CSV, or paste data directly. Columns are auto-mapped.</p>

            @if(!$csvParsed)
            {{-- Upload Area --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="csvFileInput" class="text-xs font-bold block mb-2" style="color:#c8a44e;">Upload CSV File</label>
                    <input type="file" id="csvFileInput" name="csvFile" wire:model="csvFile" accept=".csv,.tsv,.txt"
                           class="block w-full text-xs rounded-lg p-3 cursor-pointer"
                           style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                    <div wire:loading wire:target="csvFile" class="text-xs mt-2" style="color:#00d4ff;">Reading file...</div>
                </div>
                <div>
                    <label for="csvPasteArea" class="text-xs font-bold block mb-2" style="color:#c8a44e;">Or Paste Data</label>
                    <textarea id="csvPasteArea" name="csvPaste" wire:model.defer="csvPasteText" rows="4"
                              class="w-full rounded-lg p-3 text-xs resize-none"
                              style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;"
                              placeholder="Paste header row + data rows (comma or tab separated)..."></textarea>
                    <button wire:click="parseCSVPaste" class="mt-2 px-4 py-2 rounded-lg text-xs font-bold transition-all hover:scale-105"
                            style="background:#0fff50;color:#050508;">
                        Parse Pasted Data
                    </button>
                </div>
            </div>
            @else
            {{-- Column Mapping --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs" style="color:#0fff50;">{{ count($csvParsed['rows']) }} rows detected with {{ count($csvParsed['headers']) }} columns</p>
                    <button wire:click="$set('csvParsed', null)" class="text-xs px-3 py-1 rounded-lg" style="background:#e9456033;color:#e94560;">Reset</button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach(['name'=>'Owner Name *','phone'=>'Existing Phone','address'=>'Address','city'=>'City','state'=>'State','zip'=>'ZIP','resort'=>'Resort/Seller'] as $field => $label)
                    <div>
                        <label for="colMap_{{ $field }}" class="text-xs font-bold block mb-1 opacity-60">{{ $label }}</label>
                        <select id="colMap_{{ $field }}" name="columnMap[{{ $field }}]" wire:model.defer="columnMap.{{ $field }}"
                                class="w-full rounded-lg p-2 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                            <option value="">-- skip --</option>
                            @foreach($csvParsed['headers'] as $h)
                            <option value="{{ $h }}">{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>

                {{-- Preview --}}
                <div class="overflow-x-auto rounded-lg" style="border:1px solid #ffffff0a;">
                    <table class="w-full text-xs">
                        <thead>
                            <tr style="background:#0a0a10;">
                                @foreach($csvParsed['headers'] as $h)
                                <th class="px-3 py-2 text-left font-bold opacity-60 whitespace-nowrap">{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($csvParsed['rows'], 0, 5) as $row)
                            <tr style="border-top:1px solid #ffffff08;">
                                @foreach($csvParsed['headers'] as $h)
                                <td class="px-3 py-2 whitespace-nowrap opacity-80">{{ $row[$h] ?? '' }}</td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(!$csvImported)
                <button wire:click="importCSV" class="px-6 py-3 rounded-xl text-sm font-black transition-all hover:scale-105"
                        style="background:linear-gradient(135deg,#0fff50,#00d4ff);color:#050508;">
                    Import {{ count($csvParsed['rows']) }} Leads
                </button>
                @else
                <p class="text-sm font-bold" style="color:#0fff50;">Import complete! Go to Skip Trace to find phone numbers.</p>
                @endif
            </div>
            @endif
        </div>

        {{-- Instructions --}}
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #c8a44e22;">
            <h4 class="text-xs font-bold mb-2" style="color:#c8a44e;">How It Works</h4>
            <ol class="text-xs opacity-60 space-y-1 list-decimal list-inside">
                <li>Export your Google Sheet (File &rarr; Download &rarr; CSV)</li>
                <li>Upload the CSV or paste columns directly</li>
                <li>Map Owner Name + any existing phone numbers</li>
                <li>Click Import to load leads into Atlas</li>
                <li>Go to <strong>Skip Trace</strong> tab to find 2-5 verified phone numbers per lead</li>
            </ol>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 3: BATCHDATA SKIP TRACE
    ═══════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'trace')
    <div class="space-y-6">
        @if(!$batchConfigured)
        <div class="rounded-xl p-5 text-center" style="background:#0d0d12;border:1px solid #e9456033;">
            <p class="text-sm font-bold" style="color:#e94560;">BatchData API key not configured</p>
            <p class="text-xs opacity-40 mt-1">Go to the Settings tab to add your API key.</p>
            <button wire:click="$set('activeTab','settings')" class="mt-3 px-4 py-2 rounded-lg text-xs font-bold"
                    style="background:#c8a44e;color:#050508;">Go to Settings</button>
        </div>
        @else
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #00d4ff33;">
            <h3 class="text-sm font-bold mb-1" style="color:#00d4ff;">BatchData Skip Trace</h3>
            <p class="text-xs opacity-40 mb-4">Find 2-5 verified phone numbers for each lead. DNC numbers are automatically filtered out.</p>

            <div class="grid sm:grid-cols-3 gap-4 mb-4">
                <div class="rounded-lg p-3 text-center" style="background:#050508;border:1px solid #c8a44e22;">
                    <p class="text-xl font-black" style="color:#c8a44e;">{{ $stats['new'] + $stats['searched'] }}</p>
                    <p class="text-xs opacity-40">Ready to Trace</p>
                </div>
                <div class="rounded-lg p-3 text-center" style="background:#050508;border:1px solid #00d4ff22;">
                    <p class="text-xl font-black" style="color:#00d4ff;">{{ $stats['traced'] }}</p>
                    <p class="text-xs opacity-40">Already Traced</p>
                </div>
                <div class="rounded-lg p-3 text-center" style="background:#050508;border:1px solid #f5a62322;">
                    <p class="text-xl font-black" style="color:#f5a623;">${{ number_format(($stats['new'] + $stats['searched']) * 0.12, 2) }}</p>
                    <p class="text-xs opacity-40">Est. Cost (~$0.12/lead)</p>
                </div>
            </div>

            @if($traceError)
            <div class="rounded-lg p-3 mb-4 text-xs font-bold" style="background:#e9456022;color:#e94560;">{{ $traceError }}</div>
            @endif

            @if($tracing)
            <div class="space-y-2 mb-4">
                <div class="flex justify-between text-xs">
                    <span style="color:#00d4ff;">Tracing...</span>
                    <span>{{ $traceProgress }}/{{ $traceTotal }}</span>
                </div>
                <div class="h-2 rounded-full overflow-hidden" style="background:#050508;">
                    <div class="h-full rounded-full transition-all" style="background:linear-gradient(90deg,#00d4ff,#0fff50);width:{{ $traceTotal > 0 ? ($traceProgress/$traceTotal)*100 : 0 }}%;"></div>
                </div>
            </div>
            @else
            <button wire:click="runSkipTrace" class="px-6 py-3 rounded-xl text-sm font-black transition-all hover:scale-105"
                    style="background:linear-gradient(135deg,#00d4ff,#c8a44e);color:#050508;"
                    {{ ($stats['new'] + $stats['searched']) === 0 ? 'disabled' : '' }}>
                Run Skip Trace ({{ $stats['new'] + $stats['searched'] }} leads)
            </button>
            @endif

            {{-- Results --}}
            @if($traceResults && count($traceResults) > 0)
            <div class="mt-6 space-y-2">
                <h4 class="text-xs font-bold" style="color:#0fff50;">Results ({{ count($traceResults) }})</h4>
                <div class="overflow-x-auto rounded-lg" style="border:1px solid #ffffff0a;">
                    <table class="w-full text-xs">
                        <thead>
                            <tr style="background:#0a0a10;">
                                <th class="px-3 py-2 text-left font-bold opacity-60">Name</th>
                                <th class="px-3 py-2 text-left font-bold opacity-60">Existing</th>
                                <th class="px-3 py-2 text-left font-bold opacity-60">New Phones</th>
                                <th class="px-3 py-2 text-left font-bold opacity-60">Emails</th>
                                <th class="px-3 py-2 text-left font-bold opacity-60">Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($traceResults as $r)
                            <tr style="border-top:1px solid #ffffff08;">
                                <td class="px-3 py-2 font-bold whitespace-nowrap">{{ $r['name'] }}</td>
                                <td class="px-3 py-2 opacity-60">{{ $r['existingPhone'] ?: '-' }}</td>
                                <td class="px-3 py-2">
                                    @foreach($r['newPhones'] as $p)
                                    <span class="inline-block mr-2 px-2 py-0.5 rounded text-xs" style="background:#0fff5022;color:#0fff50;">
                                        {{ $p['number'] }} <span class="opacity-40">{{ $p['type'] }}</span>
                                    </span>
                                    @endforeach
                                    @if(empty($r['newPhones']))<span class="opacity-30">None</span>@endif
                                </td>
                                <td class="px-3 py-2 opacity-60">
                                    {{ implode(', ', array_slice($r['emails'] ?? [], 0, 2)) ?: '-' }}
                                </td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold"
                                          style="background:{{ $r['confidence'] === 'high' ? '#0fff5022' : ($r['confidence'] === 'medium' ? '#f5a62322' : '#e9456022') }};color:{{ $r['confidence'] === 'high' ? '#0fff50' : ($r['confidence'] === 'medium' ? '#f5a623' : '#e94560') }};">
                                        {{ $r['confidence'] }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 4: AI TEXT PARSER
    ═══════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'parser')
    <div class="space-y-6">
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #c8a44e33;">
            <h3 class="text-sm font-bold mb-1" style="color:#c8a44e;">AI Deed Parser</h3>
            <p class="text-xs opacity-40 mb-4">Paste county recorder search results. AI extracts grantee, grantor, dates, and addresses.</p>

            <label for="aiPasteArea" class="text-xs font-bold block mb-1 opacity-60">Paste Deed Search Results</label>
            <textarea id="aiPasteArea" name="pasteText" wire:model.defer="pasteText" rows="10"
                      class="w-full rounded-lg p-3 text-xs font-mono resize-none"
                      style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;"
                      placeholder="Paste deed search results here (text from county recorder website)..."></textarea>

            @if($aiError)
            <div class="rounded-lg p-3 mt-3 text-xs font-bold" style="background:#e9456022;color:#e94560;">{{ $aiError }}</div>
            @endif

            <div class="flex gap-3 mt-4">
                <button wire:click="parseText" class="px-6 py-3 rounded-xl text-sm font-black transition-all hover:scale-105"
                        style="background:#c8a44e;color:#050508;" wire:loading.attr="disabled" wire:target="parseText">
                    <span wire:loading.remove wire:target="parseText">Parse with AI</span>
                    <span wire:loading wire:target="parseText">Parsing...</span>
                </button>
            </div>
        </div>

        {{-- AI Results --}}
        @if($aiResults && count($aiResults) > 0)
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #0fff5033;">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold" style="color:#0fff50;">{{ count($aiResults) }} Records Found</h4>
                <button wire:click="importAiResults" class="px-4 py-2 rounded-lg text-xs font-bold"
                        style="background:#0fff50;color:#050508;">
                    Import {{ count($aiSelected) }} Selected
                </button>
            </div>
            <div class="space-y-2">
                @foreach($aiResults as $idx => $r)
                <div class="rounded-lg p-3 flex items-start gap-3" style="background:#050508;border:1px solid #ffffff0a;">
                    <input type="checkbox" id="aiSel_{{ $idx }}" wire:model.defer="aiSelected" value="{{ $idx }}"
                           class="mt-1 rounded" style="accent-color:#0fff50;">
                    <div class="flex-1 text-xs">
                        <p class="font-bold" style="color:#c8a44e;">{{ $r['grantee'] ?? 'Unknown' }}</p>
                        <p class="opacity-50">From: {{ $r['grantor'] ?? '-' }} | {{ $r['date'] ?? '' }} | {{ $r['type'] ?? '' }}</p>
                        @if(!empty($r['address']))<p class="opacity-40">{{ $r['address'] }}</p>@endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 5: PDF UPLOAD
    ═══════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'pdf')
    <div class="space-y-6">
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #f5a62333;">
            <h3 class="text-sm font-bold mb-1" style="color:#f5a623;">PDF Upload &amp; Parse</h3>
            <p class="text-xs opacity-40 mb-4">Upload deed PDFs from county recorder sites. AI extracts owner details from documents.</p>

            <label for="pdfFileInput" class="text-xs font-bold block mb-2 opacity-60">Upload PDFs (multiple allowed)</label>
            <input type="file" id="pdfFileInput" name="pdfFiles" wire:model="pdfFiles" accept=".pdf" multiple
                   class="block w-full text-xs rounded-lg p-3 cursor-pointer"
                   style="background:#050508;border:1px solid #f5a62333;color:#e0e0e0;">
            <div wire:loading wire:target="pdfFiles" class="text-xs mt-2" style="color:#00d4ff;">Loading PDF(s)...</div>

            {{-- PDF Queue --}}
            @if(!empty($pdfQueue))
            <div class="mt-4 space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold" style="color:#f5a623;">Queue ({{ count($pdfQueue) }} files)</h4>
                    <button wire:click="clearPdfQueue" class="text-xs px-2 py-1 rounded" style="background:#e9456033;color:#e94560;">Clear All</button>
                </div>
                @foreach($pdfQueue as $idx => $file)
                <div class="flex items-center justify-between rounded-lg p-2" style="background:#050508;border:1px solid #ffffff0a;">
                    <span class="text-xs opacity-80">{{ $file->getClientOriginalName() }}</span>
                    <button wire:click="removePdfFromQueue({{ $idx }})" class="text-xs px-2 py-1 rounded" style="color:#e94560;">X</button>
                </div>
                @endforeach
            </div>
            @endif

            @if($pdfError)
            <div class="rounded-lg p-3 mt-3 text-xs font-bold" style="background:#e9456022;color:#e94560;">{{ $pdfError }}</div>
            @endif

            <button wire:click="parsePDFs" class="mt-4 px-6 py-3 rounded-xl text-sm font-black transition-all hover:scale-105"
                    style="background:#f5a623;color:#050508;" wire:loading.attr="disabled" wire:target="parsePDFs"
                    {{ empty($pdfQueue) ? 'disabled' : '' }}>
                <span wire:loading.remove wire:target="parsePDFs">Parse {{ count($pdfQueue) }} PDF(s)</span>
                <span wire:loading wire:target="parsePDFs">Parsing...</span>
            </button>
        </div>

        {{-- PDF Results --}}
        @if($pdfResults && count($pdfResults) > 0)
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #0fff5033;">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold" style="color:#0fff50;">{{ count($pdfResults) }} Records from PDFs</h4>
                <button wire:click="importPdfResults" class="px-4 py-2 rounded-lg text-xs font-bold"
                        style="background:#0fff50;color:#050508;">
                    Import {{ count($pdfSelected) }} Selected
                </button>
            </div>
            <div class="space-y-2">
                @foreach($pdfResults as $idx => $r)
                <div class="rounded-lg p-3 flex items-start gap-3" style="background:#050508;border:1px solid #ffffff0a;">
                    <input type="checkbox" id="pdfSel_{{ $idx }}" wire:model.defer="pdfSelected" value="{{ $idx }}"
                           class="mt-1 rounded" style="accent-color:#0fff50;">
                    <div class="flex-1 text-xs">
                        <p class="font-bold" style="color:#c8a44e;">{{ $r['grantee'] ?? 'Unknown' }}</p>
                        <p class="opacity-50">From: {{ $r['grantor'] ?? '-' }} | {{ $r['date'] ?? '' }}</p>
                        @if(!empty($r['_filename']))<p class="opacity-30">File: {{ $r['_filename'] }}</p>@endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 6: COUNTIES
    ═══════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'counties')
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <input type="text" id="countySearchInput" name="countySearch" wire:model.live.debounce.300ms="countySearch"
                   class="flex-1 rounded-lg p-2.5 text-xs" style="background:#0d0d12;border:1px solid #c8a44e33;color:#e0e0e0;"
                   placeholder="Search counties, states, cities, or resorts...">
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($filteredCounties as $idx => $c)
            <div class="rounded-xl p-4" style="background:#0d0d12;border:1px solid #c8a44e22;">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h4 class="text-sm font-bold" style="color:#c8a44e;">{{ $c['county'] }} County</h4>
                        <p class="text-xs opacity-40">{{ $c['city'] }}, {{ $c['state'] }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded text-xs font-bold" style="background:#00d4ff22;color:#00d4ff;">{{ $c['state'] }}</span>
                </div>
                <p class="text-xs opacity-60 mb-2">{{ $c['resorts'] }}</p>
                <p class="text-xs opacity-40 mb-3"><strong>Tip:</strong> {{ $c['tip'] }}</p>
                <div class="flex gap-2">
                    <a href="{{ $c['url'] }}" target="_blank" rel="noopener"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all hover:scale-105"
                       style="background:#c8a44e33;color:#c8a44e;">
                        Open Records
                    </a>
                    <button wire:click="setTabWithCounty('parser', {{ $loop->index }})"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold" style="background:#00d4ff22;color:#00d4ff;">
                        AI Parse
                    </button>
                    <button wire:click="setTabWithCounty('pdf', {{ $loop->index }})"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold" style="background:#f5a62322;color:#f5a623;">
                        PDF Parse
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 7: LEADS
    ═══════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'leads')
    <div class="space-y-4">
        {{-- Filters --}}
        <div class="flex flex-wrap gap-3 items-center">
            <input type="text" id="leadSearchInput" name="searchQuery" wire:model.live.debounce.300ms="searchQuery"
                   class="flex-1 min-w-[200px] rounded-lg p-2.5 text-xs" style="background:#0d0d12;border:1px solid #c8a44e33;color:#e0e0e0;"
                   placeholder="Search leads by name, phone, address...">
            <select id="statusFilter" name="filterStatus" wire:model.live="filterStatus"
                    class="rounded-lg p-2.5 text-xs" style="background:#0d0d12;border:1px solid #c8a44e33;color:#e0e0e0;">
                <option value="ALL">All Statuses</option>
                <option value="new">New</option>
                <option value="searched">Searched</option>
                <option value="traced">Traced</option>
                <option value="imported">Imported</option>
            </select>
            <button wire:click="$toggle('showAddForm')" class="px-4 py-2.5 rounded-lg text-xs font-bold"
                    style="background:#c8a44e;color:#050508;">
                {{ $showAddForm ? 'Cancel' : '+ Add Lead' }}
            </button>
        </div>

        {{-- Manual Add Form --}}
        @if($showAddForm)
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #c8a44e33;">
            <h4 class="text-xs font-bold mb-3" style="color:#c8a44e;">Add Lead Manually</h4>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label for="formNameInput" class="text-xs opacity-60 block mb-1">Name *</label>
                    <input type="text" id="formNameInput" name="formName" wire:model.defer="formName"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                </div>
                <div>
                    <label for="formPhoneInput" class="text-xs opacity-60 block mb-1">Phone</label>
                    <input type="text" id="formPhoneInput" name="formPhone" wire:model.defer="formPhone"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                </div>
                <div>
                    <label for="formResortInput" class="text-xs opacity-60 block mb-1">Resort/Seller</label>
                    <input type="text" id="formResortInput" name="formResort" wire:model.defer="formResort"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                </div>
                <div>
                    <label for="formAddressInput" class="text-xs opacity-60 block mb-1">Address</label>
                    <input type="text" id="formAddressInput" name="formAddress" wire:model.defer="formAddress"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                </div>
                <div>
                    <label for="formCityInput" class="text-xs opacity-60 block mb-1">City</label>
                    <input type="text" id="formCityInput" name="formCity" wire:model.defer="formCity"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                </div>
                <div>
                    <label for="formStateInput" class="text-xs opacity-60 block mb-1">State</label>
                    <input type="text" id="formStateInput" name="formState" wire:model.defer="formState"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                </div>
                <div>
                    <label for="formZipInput" class="text-xs opacity-60 block mb-1">ZIP</label>
                    <input type="text" id="formZipInput" name="formZip" wire:model.defer="formZip"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #c8a44e33;color:#e0e0e0;">
                </div>
                <div class="flex items-end">
                    <button wire:click="saveLead" class="w-full px-4 py-2.5 rounded-lg text-xs font-bold"
                            style="background:#0fff50;color:#050508;">Save Lead</button>
                </div>
            </div>
        </div>
        @endif

        {{-- Leads Table --}}
        <div class="overflow-x-auto rounded-xl" style="background:#0d0d12;border:1px solid #c8a44e22;">
            <table class="w-full text-xs">
                <thead>
                    <tr style="border-bottom:1px solid #ffffff0a;">
                        <th class="px-3 py-3 text-left font-bold opacity-60">Name</th>
                        <th class="px-3 py-3 text-left font-bold opacity-60">Existing Phone</th>
                        <th class="px-3 py-3 text-left font-bold opacity-60">Skip Trace Phones</th>
                        <th class="px-3 py-3 text-left font-bold opacity-60">Source</th>
                        <th class="px-3 py-3 text-left font-bold opacity-60">Status</th>
                        <th class="px-3 py-3 text-left font-bold opacity-60">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                    <tr style="border-top:1px solid #ffffff06;" class="hover:bg-white/[.02]">
                        <td class="px-3 py-3">
                            <p class="font-bold" style="color:#c8a44e;">{{ $lead->grantee }}</p>
                            <p class="opacity-30">{{ $lead->grantor ?: '-' }}</p>
                            @if($lead->address)<p class="opacity-20">{{ $lead->address }}{{ $lead->city ? ', '.$lead->city : '' }}</p>@endif
                        </td>
                        <td class="px-3 py-3 opacity-60">{{ $lead->existing_phone ?: '-' }}</td>
                        <td class="px-3 py-3">
                            @foreach($lead->getPhones() as $p)
                            <div class="mb-0.5">
                                <span style="color:#0fff50;">{{ $p['number'] }}</span>
                                <span class="opacity-30 text-[10px]">{{ $p['type'] }}</span>
                            </div>
                            @endforeach
                            @if(empty($lead->getPhones()))<span class="opacity-20">-</span>@endif
                        </td>
                        <td class="px-3 py-3">
                            <span class="text-xs">{!! $lead->getSourceBadge() !!}</span>
                        </td>
                        <td class="px-3 py-3">
                            <select wire:change="updateStatus({{ $lead->id }}, $event.target.value)"
                                    class="rounded p-1 text-xs" style="background:{{ $lead->getStatusColor() }}22;color:{{ $lead->getStatusColor() }};border:1px solid {{ $lead->getStatusColor() }}44;">
                                @foreach(['new','searched','traced','imported'] as $s)
                                <option value="{{ $s }}" {{ $lead->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-3 py-3">
                            <button wire:click="deleteLead({{ $lead->id }})"
                                    wire:confirm="Delete this lead?"
                                    class="px-2 py-1 rounded text-xs" style="background:#e9456022;color:#e94560;">
                                Delete
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center opacity-30">
                            No leads found. Import from Google Sheets or use AI Parser to add leads.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($leads->hasPages())
        <div class="mt-4">{{ $leads->links() }}</div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB 8: SETTINGS
    ═══════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'settings')
    <div class="space-y-6 max-w-2xl">
        {{-- BatchData API Key --}}
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #00d4ff33;">
            <h3 class="text-sm font-bold mb-1" style="color:#00d4ff;">BatchData API Key</h3>
            <p class="text-xs opacity-40 mb-4">Required for skip tracing. Get your key at <a href="https://batchdata.com" target="_blank" rel="noopener" class="underline" style="color:#00d4ff;">batchdata.com</a></p>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label for="batchKeyInput" class="sr-only">BatchData API Key</label>
                    <input type="password" id="batchKeyInput" name="batchDataKey" wire:model.defer="batchDataKey"
                           class="w-full rounded-lg p-2.5 text-xs" style="background:#050508;border:1px solid #00d4ff33;color:#e0e0e0;"
                           placeholder="Enter your BatchData API key...">
                </div>
                <button wire:click="saveBatchDataKey" class="px-6 py-2.5 rounded-lg text-xs font-bold"
                        style="background:#00d4ff;color:#050508;">
                    Save Key
                </button>
            </div>

            @if($keyIsSaved || $batchConfigured)
            <p class="text-xs mt-2" style="color:#0fff50;">API key is configured and active.</p>
            @endif
        </div>

        {{-- Status Overview --}}
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #c8a44e22;">
            <h3 class="text-sm font-bold mb-3" style="color:#c8a44e;">System Status</h3>
            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between py-2" style="border-bottom:1px solid #ffffff08;">
                    <span class="opacity-60">BatchData API</span>
                    <span class="px-2 py-0.5 rounded font-bold" style="background:{{ $batchConfigured ? '#0fff5022' : '#e9456022' }};color:{{ $batchConfigured ? '#0fff50' : '#e94560' }};">
                        {{ $batchConfigured ? 'Connected' : 'Not Set' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-2" style="border-bottom:1px solid #ffffff08;">
                    <span class="opacity-60">Claude AI (Deed Parsing)</span>
                    @php $aiConfigured = app(\App\Services\AtlasAIService::class)->isConfigured(); @endphp
                    <span class="px-2 py-0.5 rounded font-bold" style="background:{{ $aiConfigured ? '#0fff5022' : '#e9456022' }};color:{{ $aiConfigured ? '#0fff50' : '#e94560' }};">
                        {{ $aiConfigured ? 'Connected' : 'Not Set' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-2" style="border-bottom:1px solid #ffffff08;">
                    <span class="opacity-60">Total Leads</span>
                    <span class="font-bold" style="color:#c8a44e;">{{ $stats['total'] }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="opacity-60">Leads Ready for Trace</span>
                    <span class="font-bold" style="color:#00d4ff;">{{ $stats['new'] + $stats['searched'] }}</span>
                </div>
            </div>
        </div>

        {{-- Danger Zone --}}
        <div class="rounded-xl p-5" style="background:#0d0d12;border:1px solid #e9456033;">
            <h3 class="text-xs font-bold mb-2" style="color:#e94560;">Export</h3>
            <p class="text-xs opacity-40 mb-3">Download all leads as a CSV file for CRM import.</p>
            <a href="{{ route('atlas.export-csv') }}" class="inline-block px-4 py-2 rounded-lg text-xs font-bold transition-all hover:scale-105"
               style="background:#c8a44e;color:#050508;">
                Download CSV Export
            </a>
        </div>
    </div>
    @endif

    </div>
</div>
