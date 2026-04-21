<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Duplicate Review</h2>
            <p class="text-xs text-crm-t3 mt-1">Review and manage duplicate leads</p>
        </div>
        <a href="{{ route('leads') }}" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">Back to Leads</a>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-5 gap-3 mb-5">
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold">{{ number_format($counts['total']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Total</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-red-600">{{ number_format($counts['exact']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Exact</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-amber-600">{{ number_format($counts['possible']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Possible</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-blue-600">{{ number_format($counts['pending']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Pending</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-emerald-600">{{ number_format($counts['reviewed']) }}</div>
            <div class="text-[10px] text-crm-t3 uppercase">Reviewed</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input id="fld-dup-search" wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name or phone..." class="flex-1 min-w-[200px] px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
        <select id="fld-typeFilter" wire:model.live="typeFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All Types</option>
            <option value="exact">Exact</option>
            <option value="possible">Possible</option>
        </select>
        <select id="fld-statusFilter" wire:model.live="statusFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="kept_both">Kept Both</option>
            <option value="deleted_duplicate">Deleted</option>
            <option value="ignored">Ignored</option>
        </select>
        <select id="fld-perPage" wire:model.live="perPage" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="25">25 per page</option>
            <option value="50">50 per page</option>
            <option value="100">100 per page</option>
            <option value="500">500 per page</option>
            <option value="1000">1000 per page</option>
        </select>
    </div>

    {{-- Bulk Actions --}}
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-2">
        <button wire:click="selectAllOnPage" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Select All Pending on Page</button>
        @if(count($selectedIds) > 0)
            <span class="text-xs font-semibold text-blue-700">{{ count($selectedIds) }} selected</span>
            <button wire:click="bulkKeep" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition">Keep All</button>
            <button wire:click="bulkIgnore" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-500 text-white hover:bg-gray-600 transition">Ignore All</button>
            <button wire:click="bulkDelete" onclick="return confirm('Delete {{ count($selectedIds) }} duplicate leads?')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-500 text-white hover:bg-red-600 transition">Delete Duplicates</button>
            <button wire:click="$set('selectedIds', [])" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-crm-border hover:bg-crm-hover transition">Clear</button>
        @else
            <span class="text-xs text-crm-t3">No rows selected — tick a checkbox or use the button above.</span>
        @endif
    </div>

    {{-- Duplicates List --}}
    <div class="space-y-3">
        @forelse($duplicates as $dup)
            @php
                $typeColor = $dup->duplicate_type === 'exact' ? 'bg-red-50 text-red-600 border-red-200' : 'bg-amber-50 text-amber-600 border-amber-200';
                $statusColor = match($dup->review_status) {
                    'pending' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                    'kept_both' => 'bg-green-50 text-green-700 border-green-200',
                    'deleted_duplicate' => 'bg-red-50 text-red-700 border-red-200',
                    'ignored' => 'bg-gray-50 text-gray-600 border-gray-200',
                    default => 'bg-gray-50 text-gray-600 border-gray-200',
                };
            @endphp
            <div class="bg-crm-card border border-crm-border rounded-lg p-4">
                <div class="flex items-start gap-3">
                    {{-- Checkbox --}}
                    @if($dup->review_status === 'pending')
                    <input id="fld-dup-sel-{{ $dup->id }}" type="checkbox" wire:model.live="selectedIds" value="{{ $dup->id }}" class="mt-1 h-4 w-4 rounded border-crm-border">
                    @endif

                    <div class="flex-1">
                        {{-- Header --}}
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded border {{ $typeColor }}">{{ strtoupper($dup->duplicate_type) }}</span>
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded border {{ $statusColor }}">{{ str_replace('_', ' ', strtoupper($dup->review_status)) }}</span>
                            <span class="text-[10px] text-crm-t3">{{ $dup->duplicate_reason }}</span>
                            <span class="text-[10px] text-crm-t3 ml-auto">{{ $dup->detected_at?->format('n/j/Y g:i A') }}</span>
                        </div>

                        {{-- Side-by-side comparison --}}
                        <div class="grid grid-cols-2 gap-4">
                            {{-- Original Lead --}}
                            <div class="bg-crm-surface rounded-lg p-3 border border-crm-border">
                                <div class="text-[9px] text-emerald-600 uppercase font-bold mb-2">Original Lead #{{ $dup->lead?->id }}</div>
                                @if($dup->lead)
                                <div class="space-y-1 text-xs">
                                    <div><span class="text-crm-t3">Name:</span> <span class="font-semibold">{{ $dup->lead->owner_name }}</span></div>
                                    <div><span class="text-crm-t3">Phone:</span> {{ $dup->lead->phone1 }} {{ $dup->lead->phone2 ? '/ '.$dup->lead->phone2 : '' }}</div>
                                    <div><span class="text-crm-t3">Email:</span> {{ $dup->lead->email ?: '--' }}</div>
                                    <div><span class="text-crm-t3">Resort:</span> {{ $dup->lead->resort }}</div>
                                    <div><span class="text-crm-t3">Location:</span> {{ $dup->lead->city }}{{ $dup->lead->st ? ', '.$dup->lead->st : '' }}</div>
                                    <div><span class="text-crm-t3">Created:</span> {{ $dup->lead->created_at?->format('n/j/Y') }}</div>
                                </div>
                                @else
                                <div class="text-xs text-crm-t3 italic">Lead deleted</div>
                                @endif
                            </div>

                            {{-- Duplicate Lead --}}
                            <div class="bg-crm-surface rounded-lg p-3 border border-crm-border">
                                <div class="text-[9px] text-red-600 uppercase font-bold mb-2">Duplicate Lead #{{ $dup->duplicateLead?->id }}</div>
                                @if($dup->duplicateLead)
                                <div class="space-y-1 text-xs">
                                    <div><span class="text-crm-t3">Name:</span> <span class="font-semibold">{{ $dup->duplicateLead->owner_name }}</span></div>
                                    <div><span class="text-crm-t3">Phone:</span> {{ $dup->duplicateLead->phone1 }} {{ $dup->duplicateLead->phone2 ? '/ '.$dup->duplicateLead->phone2 : '' }}</div>
                                    <div><span class="text-crm-t3">Email:</span> {{ $dup->duplicateLead->email ?: '--' }}</div>
                                    <div><span class="text-crm-t3">Resort:</span> {{ $dup->duplicateLead->resort }}</div>
                                    <div><span class="text-crm-t3">Location:</span> {{ $dup->duplicateLead->city }}{{ $dup->duplicateLead->st ? ', '.$dup->duplicateLead->st : '' }}</div>
                                    <div><span class="text-crm-t3">Created:</span> {{ $dup->duplicateLead->created_at?->format('n/j/Y') }}</div>
                                </div>
                                @else
                                <div class="text-xs text-crm-t3 italic">Lead deleted</div>
                                @endif
                            </div>
                        </div>

                        {{-- Matched Fields --}}
                        @if($dup->matched_fields)
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-[9px] text-crm-t3 uppercase">Matched:</span>
                            @foreach($dup->matched_fields as $field)
                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600">{{ $field }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    @if($dup->review_status === 'pending')
                    <div class="flex flex-col gap-1 ml-3">
                        <button wire:click="keepBoth({{ $dup->id }})" class="px-3 py-1.5 text-[10px] font-semibold rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 transition whitespace-nowrap">Keep Both</button>
                        <button wire:click="ignore({{ $dup->id }})" class="px-3 py-1.5 text-[10px] font-semibold rounded-lg bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100 transition whitespace-nowrap">Ignore</button>
                        <button wire:click="deleteDuplicate({{ $dup->id }})" onclick="return confirm('Delete duplicate lead #{{ $dup->duplicate_lead_id }}?')" class="px-3 py-1.5 text-[10px] font-semibold rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition whitespace-nowrap">Delete Dup</button>
                    </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center text-crm-t3 text-sm">
                No duplicates found matching your filters.
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $duplicates->links() }}
    </div>
</div>
