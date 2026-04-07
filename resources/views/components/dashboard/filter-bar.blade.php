@props([
    'dateRange' => '30d',
    'ownerFilter' => 'all',
    'users' => collect(),
    'showOwnerFilter' => false,
    'showExport' => false,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <select wire:model.live="dateRange" class="px-3 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
        <option value="today">Today</option>
        <option value="7d">Last 7 Days</option>
        <option value="30d">Last 30 Days</option>
        <option value="month">This Month</option>
    </select>
    @if($showOwnerFilter)
        <select wire:model.live="ownerFilter" class="px-3 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
            <option value="all">All Reps</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }} ({{ ucfirst(str_replace('_', ' ', $u->role)) }})</option>
            @endforeach
        </select>
    @endif
    @if($showExport)
        <button class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-white border border-crm-border rounded-lg hover:bg-crm-hover transition">Export</button>
    @endif
</div>
