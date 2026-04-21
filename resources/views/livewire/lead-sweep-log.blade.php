<div class="p-5">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">Lead Sweep Log</h2>
            <p class="text-xs text-crm-t3 mt-1">Every auto-fix made by <span class="font-mono">php artisan leads:sweep</span>. Master Admin can revert any change.</p>
        </div>
        <a href="/leads/imports" class="px-3 py-1.5 bg-crm-card border border-crm-border text-xs font-semibold rounded-lg hover:bg-crm-hover transition">Back to Imports</a>
    </div>

    @if($flash)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-50 text-blue-700 border border-blue-200">{{ $flash }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <select id="fld-ruleFilter" name="ruleFilter" wire:model.live="ruleFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="all">All rules</option>
            @foreach($rules as $r)
                <option value="{{ $r }}">{{ $r }}</option>
            @endforeach
        </select>
        <select id="fld-dateFilter" name="dateFilter" wire:model.live="dateFilter" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="all">All time</option>
        </select>
        <select id="fld-perPage" name="perPage" wire:model.live="perPage" class="px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="500">500</option>
        </select>
    </div>

    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="border-b border-crm-border bg-crm-surface">
                <tr>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">When</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Lead</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Field</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Rule</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Old</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">New</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-b border-crm-border last:border-b-0 {{ $log->reverted_at ? 'opacity-50' : '' }}">
                        <td class="px-3 py-2 text-[11px] text-crm-t2">{{ $log->created_at?->diffForHumans() }}</td>
                        <td class="px-3 py-2 font-mono text-xs">#{{ $log->lead_id }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $log->field_name }}</td>
                        <td class="px-3 py-2 text-[11px]">
                            <span class="px-1.5 py-0.5 rounded bg-crm-surface border border-crm-border text-[10px]">{{ $log->rule }}</span>
                        </td>
                        <td class="px-3 py-2 font-mono text-[11px] max-w-[180px] truncate" title="{{ $log->old_value }}">{{ $log->old_value ?: '—' }}</td>
                        <td class="px-3 py-2 font-mono text-[11px] max-w-[180px] truncate" title="{{ $log->new_value }}">{{ $log->new_value ?: '—' }}</td>
                        <td class="px-3 py-2">
                            @if($log->reverted_at)
                                <span class="text-[10px] text-crm-t3">reverted {{ $log->reverted_at->diffForHumans() }}</span>
                            @elseif(in_array($log->rule, ['conflict_skipped']) || str_starts_with($log->rule, 'reverted_'))
                                <span class="text-[10px] text-crm-t3">—</span>
                            @else
                                <button wire:click="revert({{ $log->id }})" wire:confirm="Revert this change on lead #{{ $log->lead_id }}?" class="px-2 py-0.5 text-[10px] font-semibold rounded border border-crm-border hover:bg-crm-hover">Revert</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-8 text-center text-xs text-crm-t3">No sweep activity in this window. Run <span class="font-mono">php artisan leads:sweep</span> to find and fix misplaced fields.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
