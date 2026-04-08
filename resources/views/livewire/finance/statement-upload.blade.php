<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Statement Management</h2>
            <p class="text-xs text-crm-t3 mt-1">Upload, preview, and manage processor statements</p>
        </div>
    </div>

    {{-- Navigation Pills --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach([
            ['href' => '/finance', 'label' => 'Dashboard'],
            ['href' => '/finance/accounts', 'label' => 'Merchant Accounts'],
            ['href' => '/finance/statements', 'label' => 'Statements', 'active' => true],
            ['href' => '/finance/transactions', 'label' => 'Transactions'],
            ['href' => '/finance/chargebacks', 'label' => 'Chargebacks'],
            ['href' => '/finance/entries', 'label' => 'Financial Entries'],
            ['href' => '/finance/settings', 'label' => 'Settings'],
        ] as $nav)
            <a href="{{ $nav['href'] }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition {{ ($nav['active'] ?? false) ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">{{ $nav['label'] }}</a>
        @endforeach
    </div>

    {{-- Flash Messages --}}
    @if(session('finance_success'))
        <div class="mb-4 px-4 py-2 bg-emerald-50 border border-emerald-300 text-emerald-700 text-sm rounded-lg">{{ session('finance_success') }}</div>
    @endif
    @if(session('finance_error'))
        <div class="mb-4 px-4 py-2 bg-red-50 border border-red-300 text-red-700 text-sm rounded-lg">{{ session('finance_error') }}</div>
    @endif

    {{-- Tab Buttons --}}
    <div class="flex gap-2 mb-5">
        <button wire:click="$set('tab', 'upload')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'upload' ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">Upload</button>
        <button wire:click="$set('tab', 'preview')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'preview' ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">Preview</button>
        <button wire:click="$set('tab', 'history')" class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'history' ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t2 hover:bg-crm-hover' }}">History</button>
    </div>

    {{-- ═══ UPLOAD TAB ═══ --}}
    @if($tab === 'upload')
    <div class="bg-crm-card border border-crm-border rounded-lg p-5">
        <div class="text-sm font-bold mb-4">Upload Statement File</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Statement File (CSV, PDF, Excel)</label>
                <input type="file" wire:model="file" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100">
                <div wire:loading wire:target="file" class="text-xs text-blue-500 mt-1">Uploading file...</div>
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Assign to MID</label>
                <select wire:model="midFilter" class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <option value="">Auto-detect</option>
                    @foreach($mids as $mid)
                        <option value="{{ $mid->id }}">{{ $mid->account_name }} ({{ $mid->mid_number }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4">
            <button wire:click="upload" class="px-4 py-1.5 text-sm font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="upload">Upload & Analyze</span>
                <span wire:loading wire:target="upload">Processing...</span>
            </button>
        </div>
    </div>
    @endif

    {{-- ═══ PREVIEW TAB ═══ --}}
    @if($tab === 'preview')
    <div>
        @if($previewUpload)
        {{-- Upload Summary --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-5 mb-5">
            <div class="text-sm font-bold mb-3">Statement Preview</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Filename</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $previewUpload->original_filename ?? $previewUpload->filename }}</div>
                </div>
                <div>
                    <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Detected Processor</div>
                    <div class="text-sm font-semibold mt-0.5">{{ $previewUpload->detected_processor ?? 'Unknown' }}</div>
                </div>
                <div>
                    <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Confidence Score</div>
                    <div class="text-sm font-semibold mt-0.5">
                        <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ ($previewUpload->confidence_score ?? 0) >= 0.8 ? 'bg-emerald-100 text-emerald-700' : (($previewUpload->confidence_score ?? 0) >= 0.5 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                            {{ number_format(($previewUpload->confidence_score ?? 0) * 100, 0) }}%
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">MID Assignment</div>
                    @if($previewUpload->merchant_account_id)
                        <div class="text-sm font-semibold mt-0.5">{{ $previewUpload->merchantAccount->account_name ?? 'Assigned' }}</div>
                    @else
                        <select wire:change="assignMid({{ $previewUpload->id }}, $event.target.value)" class="mt-0.5 w-full px-2 py-1 text-xs bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                            <option value="">Select MID...</option>
                            @foreach($mids as $mid)
                                <option value="{{ $mid->id }}">{{ $mid->account_name }} ({{ $mid->mid_number }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            {{-- Summary Card --}}
            @if($previewUpload->summary)
            @php $summ = $previewUpload->summary; @endphp
            <div class="bg-crm-surface rounded-lg p-3 mb-4">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Statement Summary</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach([
                        'Period' => ($summ->statement_start_date?->format('n/j/Y') ?? '--') . ' - ' . ($summ->statement_end_date?->format('n/j/Y') ?? '--'),
                        'Gross Volume' => '$' . number_format($summ->gross_volume ?? 0, 2),
                        'Refunds' => '$' . number_format($summ->refunds_total ?? 0, 2),
                        'Chargebacks' => '$' . number_format($summ->chargebacks_total ?? 0, 2),
                        'Fees' => '$' . number_format($summ->fees_total ?? 0, 2),
                        'Reserves' => '$' . number_format($summ->reserves_total ?? 0, 2),
                        'Payouts' => '$' . number_format($summ->payouts_total ?? 0, 2),
                        'Ending Balance' => '$' . number_format($summ->ending_balance ?? 0, 2),
                    ] as $label => $val)
                    <div>
                        <div class="text-[9px] text-crm-t3 uppercase">{{ $label }}</div>
                        <div class="text-sm font-bold">{{ $val }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Preview Lines Table --}}
        @if(!empty($previewLines))
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-crm-surface">
                        <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Type</th>
                        <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Date</th>
                        <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Description</th>
                        <th class="px-3 py-2 text-right text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Amount</th>
                        <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Confidence</th>
                        <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Review</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-crm-border">
                    @foreach($previewLines as $line)
                    <tr class="hover:bg-crm-hover transition {{ $line->needs_review ? 'bg-amber-50' : '' }}">
                        <td class="px-3 py-2">
                            @php
                                $typeColors = [
                                    'transaction' => 'bg-blue-100 text-blue-700',
                                    'chargeback' => 'bg-red-100 text-red-700',
                                    'fee' => 'bg-orange-100 text-orange-700',
                                    'reserve_hold' => 'bg-purple-100 text-purple-700',
                                    'reserve_release' => 'bg-emerald-100 text-emerald-700',
                                    'payout' => 'bg-green-100 text-green-700',
                                    'deposit' => 'bg-green-100 text-green-700',
                                    'adjustment' => 'bg-amber-100 text-amber-700',
                                    'refund' => 'bg-amber-100 text-amber-700',
                                ];
                            @endphp
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $typeColors[$line->line_type] ?? 'bg-gray-100 text-gray-700' }}">{{ str_replace('_', ' ', ucfirst($line->line_type)) }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $line->transaction_date?->format('n/j/Y') ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs">{{ Str::limit($line->description ?? '-', 50) }}</td>
                        <td class="px-3 py-2 text-right text-xs font-semibold {{ ($line->amount ?? 0) < 0 ? 'text-red-500' : 'text-emerald-600' }}">${{ number_format($line->amount ?? 0, 2) }}</td>
                        <td class="px-3 py-2 text-center">
                            @php $conf = $line->confidence_score ?? 0; @endphp
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $conf >= 0.8 ? 'bg-emerald-100 text-emerald-700' : ($conf >= 0.5 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($conf * 100, 0) }}%
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($line->needs_review ?? false)
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-amber-100 text-amber-700">Needs Review</span>
                            @else
                                <span class="text-[9px] text-crm-t3">OK</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <button wire:click="confirmImport" class="px-5 py-2 text-sm font-semibold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                Confirm Import
            </button>
        </div>
        @endif

        @else
        <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
            <p class="text-sm text-crm-t3">No statement to preview. Upload a file first.</p>
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ HISTORY TAB ═══ --}}
    @if($tab === 'history')
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-crm-surface">
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Filename</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">MID</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Processor</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Status</th>
                    <th class="px-3 py-2 text-center text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Confidence</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Period</th>
                    <th class="px-3 py-2 text-left text-[9px] text-crm-t3 uppercase tracking-wider font-semibold">Uploaded</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-crm-border">
                @forelse($history as $upload)
                <tr class="hover:bg-crm-hover transition">
                    <td class="px-3 py-2 text-xs font-semibold">{{ $upload->original_filename }}</td>
                    <td class="px-3 py-2 text-xs">{{ $upload->merchantAccount?->account_name ?? 'Unassigned' }}</td>
                    <td class="px-3 py-2 text-xs">{{ $upload->detected_processor ?? '-' }}</td>
                    <td class="px-3 py-2 text-center">
                        @php
                            $status = $upload->processing_status ?? 'pending';
                            $statusColor = match($status) {
                                'imported' => 'bg-emerald-100 text-emerald-700',
                                'parsed' => 'bg-blue-100 text-blue-700',
                                'processing' => 'bg-cyan-100 text-cyan-700',
                                'failed' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $statusColor }}">{{ ucfirst($status) }}</span>
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if($upload->confidence_score)
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $upload->confidence_score >= 0.8 ? 'bg-emerald-100 text-emerald-700' : ($upload->confidence_score >= 0.5 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                {{ number_format($upload->confidence_score * 100, 0) }}%
                            </span>
                        @else
                            <span class="text-crm-t3 text-[9px]">--</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-xs">
                        @if($upload->summary)
                            {{ $upload->summary->statement_start_date?->format('n/j') ?? '' }} - {{ $upload->summary->statement_end_date?->format('n/j/Y') ?? '' }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-3 py-2 text-xs text-crm-t3">{{ $upload->uploaded_at?->format('M j, Y g:ia') ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-3 py-8 text-center text-sm text-crm-t3">No statement uploads yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
