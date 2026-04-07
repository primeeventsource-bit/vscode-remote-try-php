{{--
    Searchable User Picker Component
    Usage: <x-user-picker :users="$users" wire-model="propertyName" placeholder="Select user..." />
    Optional: id, class, size (sm|md), show-role (bool)
--}}
@props([
    'users',
    'wireModel',
    'placeholder' => 'Search users...',
    'id' => 'up-' . uniqid(),
    'size' => 'sm',
    'showRole' => true,
])

@php
    $sz = $size === 'md' ? 'text-sm py-2 px-3' : 'text-xs py-1.5 px-3';
    $dropSz = $size === 'md' ? 'text-sm' : 'text-xs';
    $avatarSz = $size === 'md' ? 'w-7 h-7 text-[9px]' : 'w-6 h-6 text-[8px]';
@endphp

<div
    x-data="{
        open: false,
        search: '',
        selectedId: @entangle($wireModel).live,
        users: @js($users->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => ucfirst(str_replace('_', ' ', $u->role ?? '')),
            'color' => $u->color ?? '#6b7280',
            'avatar' => $u->avatar ?? substr($u->name ?? '?', 0, 2),
            'email' => $u->email ?? '',
        ])->values()),
        get filtered() {
            if (!this.search.trim()) return this.users;
            const q = this.search.toLowerCase();
            return this.users.filter(u =>
                u.name.toLowerCase().includes(q) ||
                u.role.toLowerCase().includes(q) ||
                u.email.toLowerCase().includes(q)
            );
        },
        get selectedUser() {
            if (!this.selectedId) return null;
            return this.users.find(u => u.id == this.selectedId) || null;
        },
        pick(id) {
            this.selectedId = id;
            this.search = '';
            this.open = false;
        },
        clear() {
            this.selectedId = '';
            this.search = '';
        },
    }"
    class="relative"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    {{-- Display: selected user or search input --}}
    <div class="flex items-center gap-1 bg-white border border-crm-border rounded-lg {{ $sz }} focus-within:border-blue-400 transition cursor-pointer"
         @click="open = true; $nextTick(() => $refs.searchInput?.focus())">
        <template x-if="selectedUser && !open">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <div class="{{ $avatarSz }} rounded-full flex items-center justify-center font-bold text-white flex-shrink-0"
                     :style="'background:' + selectedUser.color">
                    <span x-text="selectedUser.avatar"></span>
                </div>
                <span class="{{ $dropSz }} font-semibold truncate" x-text="selectedUser.name"></span>
                @if($showRole)
                    <span class="{{ $dropSz }} text-crm-t3 flex-shrink-0" x-text="'(' + selectedUser.role + ')'"></span>
                @endif
                <button type="button" @click.stop="clear()" class="ml-auto text-crm-t3 hover:text-red-500 flex-shrink-0" title="Clear">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
        <template x-if="!selectedUser || open">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <svg class="w-3.5 h-3.5 text-crm-t3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input x-ref="searchInput" type="text" x-model="search"
                       @focus="open = true"
                       @keydown.arrow-down.prevent="$refs.list?.querySelector('button')?.focus()"
                       placeholder="{{ $placeholder }}"
                       class="{{ $dropSz }} w-full bg-transparent outline-none placeholder-crm-t3">
            </div>
        </template>
    </div>

    {{-- Dropdown --}}
    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute z-50 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-crm-border rounded-lg shadow-lg" x-ref="list">
        <template x-if="filtered.length === 0">
            <div class="px-3 py-3 text-center {{ $dropSz }} text-crm-t3">No users found</div>
        </template>
        <template x-for="u in filtered" :key="u.id">
            <button type="button"
                    @click="pick(u.id)"
                    @keydown.arrow-down.prevent="$el.nextElementSibling?.focus()"
                    @keydown.arrow-up.prevent="$el.previousElementSibling?.focus()"
                    :class="u.id == selectedId ? 'bg-blue-50' : 'hover:bg-crm-hover'"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left transition border-b border-crm-border last:border-0">
                <div class="{{ $avatarSz }} rounded-full flex items-center justify-center font-bold text-white flex-shrink-0"
                     :style="'background:' + u.color">
                    <span x-text="u.avatar"></span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="{{ $dropSz }} font-semibold truncate" x-text="u.name"></div>
                    @if($showRole)
                        <div class="text-[10px] text-crm-t3" x-text="u.role"></div>
                    @endif
                </div>
                <template x-if="u.id == selectedId">
                    <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </template>
            </button>
        </template>
    </div>
</div>
