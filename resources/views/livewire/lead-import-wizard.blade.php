<div class="p-5 max-w-6xl mx-auto">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">AI Lead Import</h2>
            <p class="text-xs text-crm-t3 mt-1">Upload a CSV — the system maps columns automatically, you confirm, then it imports.</p>
        </div>
        <a href="/lead-imports" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">Import History</a>
    </div>

    @if($error)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm font-semibold bg-red-50 text-red-700 border border-red-200">{{ $error }}</div>
    @endif

    {{-- ═══ STEP 1 — UPLOAD ═══ --}}
    @if($step === 'upload')
        <div class="bg-crm-card border border-crm-border rounded-lg p-5">
            <label for="csvFile" class="block text-xs font-semibold text-crm-t2 mb-2 uppercase tracking-wider">CSV file (with a header row)</label>
            <input id="csvFile" name="csvFile" wire:model="csvFile" type="file" accept=".csv,.txt" class="block w-full text-sm">
            <p class="text-[11px] text-crm-t3 mt-1">Max 100 MB. Row 1 must contain column names.</p>

            <div class="mt-4">
                <label for="csvText" class="block text-xs font-semibold text-crm-t2 mb-2 uppercase tracking-wider">Or paste CSV text</label>
                <textarea id="csvText" name="csvText" wire:model="csvText" rows="4" placeholder="Resort,Owner Name,Phone,Email,State&#10;HILTON,Jane Doe,555-1234,jane@ex.com,FL"
                    class="w-full px-3 py-2 text-sm font-mono bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400"></textarea>
            </div>

            <div class="mt-4">
                <label for="duplicateStrategy" class="block text-xs font-semibold text-crm-t2 mb-2 uppercase tracking-wider">Duplicate strategy</label>
                <select id="duplicateStrategy" name="duplicateStrategy" wire:model="duplicateStrategy" class="w-60 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                    <option value="skip">Skip duplicates</option>
                    <option value="flag">Flag duplicates (still import)</option>
                    <option value="import_all">Import everything (no check)</option>
                </select>
            </div>

            <div class="mt-5 flex items-center gap-2">
                <button wire:click="analyze" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="analyze">Analyze Headers</span>
                    <span wire:loading wire:target="analyze">Analyzing…</span>
                </button>
                <span class="text-[11px] text-crm-t3">Next screen shows the mapping for you to confirm before any data is imported.</span>
            </div>
        </div>
    @endif

    {{-- ═══ STEP 2 — PREVIEW / MAPPING ═══ --}}
    @if($step === 'preview')
        @if($fromRememberedTemplate)
            <div class="mb-3 px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-xs text-blue-700">
                ⚡ Remembered a previously-confirmed mapping for this exact header row. Review and click Import.
            </div>
        @endif

        <div class="bg-crm-card border border-crm-border rounded-lg p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-sm font-bold">Column mapping</h3>
                    <p class="text-[11px] text-crm-t3">
                        {{ number_format($totalDataRows) }} data rows detected · file: <span class="font-mono">{{ $originalFilename }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-3 text-[11px]">
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>dictionary match</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span>AI match</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span>unmapped</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-crm-border bg-crm-surface">
                        <tr>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold w-12"></th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Spreadsheet Column</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Maps To</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Sample Row 1</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Sample Row 2</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Sample Row 3</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($headers as $idx => $header)
                            @php
                                $norm = $normalizedHeaders[$idx] ?? '';
                                $conf = $confidence[$norm] ?? 'none';
                                $dot = match($conf) { 'high' => 'bg-emerald-500', 'medium' => 'bg-amber-500', 'low' => 'bg-amber-500', default => 'bg-red-500' };
                            @endphp
                            <tr class="border-b border-crm-border last:border-b-0">
                                <td class="px-3 py-2">
                                    <span class="inline-block w-2 h-2 rounded-full {{ $dot }}" title="{{ $conf }}"></span>
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $header }}</td>
                                <td class="px-3 py-2">
                                    <select id="map-{{ $idx }}" name="mapping-{{ $idx }}" wire:model="mapping.{{ $norm }}"
                                        class="px-2 py-1 text-xs bg-white border border-crm-border rounded focus:outline-none focus:border-blue-400">
                                        <option value="">— Skip this column —</option>
                                        @foreach($leadFields as $f)
                                            <option value="{{ $f }}">{{ $f }}</option>
                                        @endforeach
                                        <option value="__countystate__">county/state (split)</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-xs text-crm-t2 font-mono max-w-[160px] truncate" title="{{ $sampleRows[0][$idx] ?? '' }}">{{ $sampleRows[0][$idx] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-crm-t2 font-mono max-w-[160px] truncate" title="{{ $sampleRows[1][$idx] ?? '' }}">{{ $sampleRows[1][$idx] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-crm-t2 font-mono max-w-[160px] truncate" title="{{ $sampleRows[2][$idx] ?? '' }}">{{ $sampleRows[2][$idx] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5 flex items-center gap-2">
                <button wire:click="import" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="import">Import {{ number_format($totalDataRows) }} Rows</span>
                    <span wire:loading wire:target="import">Queuing…</span>
                </button>
                <button wire:click="cancel" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-white border border-crm-border rounded-lg hover:bg-crm-hover">Cancel</button>
            </div>
        </div>
    @endif

    {{-- ═══ STEP 3 — SUMMARY ═══ --}}
    @if($step === 'done' && !empty($result))
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-5">
            <h3 class="text-sm font-bold text-emerald-700 mb-2">✓ Import queued</h3>
            <div class="text-xs text-emerald-900 space-y-1">
                <div><strong>Batch ID:</strong> #{{ $result['batch_id'] }}</div>
                <div><strong>Rows queued:</strong> {{ number_format($result['total']) }} in {{ $result['chunks'] }} chunk(s)</div>
                <div><strong>Strategy:</strong> {{ $result['strategy'] }}</div>
                @if(!empty($result['skipped_empty']))
                    <div><strong>Empty/invalid rows skipped:</strong> {{ $result['skipped_empty'] }}</div>
                @endif
            </div>
            <div class="mt-4 flex items-center gap-2">
                <a href="/lead-imports" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Track progress on Import History</a>
                <button wire:click="cancel" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-white border border-crm-border rounded-lg hover:bg-crm-hover">Import Another File</button>
            </div>
        </div>
    @endif
</div>
