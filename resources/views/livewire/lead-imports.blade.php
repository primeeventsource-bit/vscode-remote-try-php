<div class="p-5" wire:poll.5s>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Lead Import History</h2>
            <p class="text-xs text-crm-t3 mt-1">Track and manage CSV import batches</p>
        </div>
        <a href="{{ route('leads') }}" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">Back to Leads</a>
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-3 mb-4">
        <select wire:model.live="statusFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        <select wire:model.live="perPage" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="25">25 per page</option>
            <option value="50">50 per page</option>
        </select>
    </div>

    {{-- Imports Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">File</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Progress</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Total</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Imported</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Duplicates</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Invalid</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Failed</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Strategy</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Started</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($imports as $import)
                        @php
                            $statusColor = match($import->status) {
                                'completed' => 'bg-emerald-50 text-emerald-600',
                                'processing' => 'bg-blue-50 text-blue-600',
                                'failed' => 'bg-red-50 text-red-600',
                                'cancelled' => 'bg-gray-50 text-gray-600',
                                default => 'bg-yellow-50 text-yellow-600',
                            };
                        @endphp
                        <tr class="border-b border-crm-border {{ $detailBatchId === $import->id ? 'bg-blue-50' : 'hover:bg-crm-hover' }}">
                            <td class="px-4 py-2.5 font-semibold">{{ \Illuminate\Support\Str::limit($import->original_filename, 30) }}</td>
                            <td class="px-4 py-2.5"><span class="text-[9px] font-bold px-2 py-0.5 rounded {{ $statusColor }}">{{ strtoupper($import->status) }}</span></td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full {{ $import->status === 'failed' ? 'bg-red-500' : 'bg-blue-500' }} rounded-full transition-all" style="width: {{ $import->progressPercent() }}%"></div>
                                    </div>
                                    <span class="text-xs text-crm-t3 font-semibold">{{ $import->progressPercent() }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">{{ number_format($import->total_rows) }}</td>
                            <td class="px-4 py-2.5 text-emerald-600 font-semibold">{{ number_format($import->successful_rows) }}</td>
                            <td class="px-4 py-2.5 text-amber-600">{{ number_format($import->duplicate_rows) }}</td>
                            <td class="px-4 py-2.5 text-orange-600">{{ number_format($import->invalid_rows) }}</td>
                            <td class="px-4 py-2.5 text-red-600">{{ number_format($import->failed_rows) }}</td>
                            <td class="px-4 py-2.5"><span class="text-[9px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-crm-t3">{{ $import->duplicate_strategy }}</span></td>
                            <td class="px-4 py-2.5 text-crm-t3 text-xs">{{ $import->started_at?->format('n/j g:i A') ?? '--' }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    <button wire:click="viewDetails({{ $import->id }})" class="px-2 py-1 text-[10px] font-semibold rounded border border-crm-border bg-white hover:bg-crm-hover transition">
                                        {{ $detailBatchId === $import->id ? 'Hide' : 'Details' }}
                                    </button>
                                    @if($import->status === 'failed' && auth()->user()?->hasRole('master_admin', 'admin'))
                                        <button wire:click="retryBatch({{ $import->id }})" class="px-2 py-1 text-[10px] font-semibold rounded bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 transition">Retry</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-8 text-center text-crm-t3 text-sm">No imports found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mb-4">
        {{ $imports->links() }}
    </div>

    {{-- Detail View --}}
    @if($detailBatch)
    <div class="bg-crm-card border border-crm-border rounded-lg p-5 mb-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold">Import Details — {{ $detailBatch->original_filename }}</h3>
            <button wire:click="viewDetails({{ $detailBatch->id }})" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-6 gap-3 mb-4">
            <div class="bg-crm-surface rounded-lg p-3 text-center border border-crm-border">
                <div class="text-lg font-bold">{{ number_format($detailBatch->total_rows) }}</div>
                <div class="text-[9px] text-crm-t3 uppercase">Total Rows</div>
            </div>
            <div class="bg-crm-surface rounded-lg p-3 text-center border border-crm-border">
                <div class="text-lg font-bold text-emerald-600">{{ number_format($detailBatch->successful_rows) }}</div>
                <div class="text-[9px] text-crm-t3 uppercase">Imported</div>
            </div>
            <div class="bg-crm-surface rounded-lg p-3 text-center border border-crm-border">
                <div class="text-lg font-bold text-amber-600">{{ number_format($detailBatch->duplicate_rows) }}</div>
                <div class="text-[9px] text-crm-t3 uppercase">Duplicates</div>
            </div>
            <div class="bg-crm-surface rounded-lg p-3 text-center border border-crm-border">
                <div class="text-lg font-bold text-orange-600">{{ number_format($detailBatch->invalid_rows) }}</div>
                <div class="text-[9px] text-crm-t3 uppercase">Invalid</div>
            </div>
            <div class="bg-crm-surface rounded-lg p-3 text-center border border-crm-border">
                <div class="text-lg font-bold text-red-600">{{ number_format($detailBatch->failed_rows) }}</div>
                <div class="text-[9px] text-crm-t3 uppercase">Failed</div>
            </div>
            <div class="bg-crm-surface rounded-lg p-3 text-center border border-crm-border">
                <div class="text-lg font-bold">{{ $detailBatch->progressPercent() }}%</div>
                <div class="text-[9px] text-crm-t3 uppercase">Progress</div>
            </div>
        </div>

        {{-- Metadata --}}
        <div class="grid grid-cols-4 gap-3 mb-4 text-xs">
            <div><span class="text-crm-t3">Uploaded by:</span> <span class="font-semibold">{{ $detailBatch->user?->name ?? 'Unknown' }}</span></div>
            <div><span class="text-crm-t3">Strategy:</span> <span class="font-semibold">{{ ucfirst($detailBatch->duplicate_strategy) }}</span></div>
            <div><span class="text-crm-t3">Started:</span> <span class="font-semibold">{{ $detailBatch->started_at?->format('n/j/Y g:i:s A') ?? '--' }}</span></div>
            <div><span class="text-crm-t3">Completed:</span> <span class="font-semibold">{{ $detailBatch->completed_at?->format('n/j/Y g:i:s A') ?? '--' }}</span></div>
        </div>

        @if($detailBatch->error_message)
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span class="font-semibold">Error:</span> {{ $detailBatch->error_message }}
            </div>
        @endif

        {{-- Failures Table --}}
        @if($failures->count() > 0)
        <div class="border-t border-crm-border pt-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold">Issues ({{ $failures->count() }} shown)</h4>
                <select wire:model.live="failureFilter" class="px-2 py-1 text-xs bg-white border border-crm-border rounded-lg">
                    <option value="all">All Issues</option>
                    <option value="validation">Validation</option>
                    <option value="duplicate">Duplicates</option>
                    <option value="exception">Exceptions</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-3 py-2 font-semibold text-crm-t3">Row</th>
                            <th class="text-left px-3 py-2 font-semibold text-crm-t3">Type</th>
                            <th class="text-left px-3 py-2 font-semibold text-crm-t3">Reason</th>
                            <th class="text-left px-3 py-2 font-semibold text-crm-t3">Data</th>
                            <th class="text-left px-3 py-2 font-semibold text-crm-t3">Match</th>
                            <th class="text-left px-3 py-2 font-semibold text-crm-t3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($failures as $fail)
                            @php
                                $ftColor = match($fail->failure_type) {
                                    'validation' => 'bg-orange-50 text-orange-600',
                                    'duplicate' => 'bg-amber-50 text-amber-600',
                                    'exception' => 'bg-red-50 text-red-600',
                                    default => 'bg-gray-50 text-gray-600',
                                };
                            @endphp
                            <tr class="border-b border-crm-border">
                                <td class="px-3 py-2 font-mono">{{ $fail->row_number }}</td>
                                <td class="px-3 py-2"><span class="text-[8px] font-bold px-1.5 py-0.5 rounded {{ $ftColor }}">{{ $fail->failure_type }}</span></td>
                                <td class="px-3 py-2 max-w-xs truncate">{{ $fail->reason }}</td>
                                <td class="px-3 py-2 max-w-xs truncate font-mono text-[10px]">
                                    @if($fail->raw_row)
                                        {{ \Illuminate\Support\Str::limit(json_encode($fail->raw_row), 80) }}
                                    @else
                                        --
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if($fail->matched_lead_id)
                                        <span class="text-blue-600 font-semibold">#{{ $fail->matched_lead_id }}</span>
                                    @else
                                        --
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-crm-t3">{{ $fail->resolution_status }}</span>
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
