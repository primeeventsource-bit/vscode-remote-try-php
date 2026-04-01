@php
    $gifPickerSettings = $gifPickerSettings ?? [];
    $gifPickerDefaultTab = 'trending';
@endphp

@if($canUseGifPicker ?? false)
    <div
        x-data="{
            open: false,
            loading: false,
            error: null,
            items: [],
            cursor: null,
            query: '',
            tab: @js($gifPickerDefaultTab),
            settings: @js($gifPickerSettings),
            userId: @js($currentUserId ?? 0),
            categories: {
                reactions: 'reactions',
                funny: 'funny',
                love: 'love',
                celebrations: 'celebrations party',
                movies: 'movie reactions',
            },
            activeCategory: null,
            endpointForTab() {
                if (this.tab === 'category') return '/api/gifs/search';
                if (this.tab === 'search') return '/api/gifs/search';
                return '/api/gifs/trending';
            },
            async toggle() {
                this.open = !this.open;
                if (this.open && this.items.length === 0) {
                    await this.load(true);
                }
            },
            async setTab(name) {
                this.tab = name;
                this.activeCategory = null;
                this.error = null;
                this.cursor = null;
                this.items = [];
                await this.load(true);
            },
            async setCategory(name) {
                this.tab = 'category';
                this.activeCategory = name;
                this.query = this.categories[name] || name;
                this.error = null;
                this.cursor = null;
                this.items = [];
                await this.load(true);
            },
            async load(reset = false) {
                if (this.tab === 'search' && this.query.trim().length < 2) {
                    this.items = [];
                    return;
                }
                this.loading = true;
                this.error = null;
                try {
                    const params = new URLSearchParams();
                    if (this.userId) params.set('user_id', this.userId);
                    params.set('limit', '20');
                    if (!reset && this.cursor) params.set('cursor', this.cursor);
                    if (this.tab === 'category' && this.activeCategory) {
                        params.set('q', this.categories[this.activeCategory] || this.activeCategory);
                    } else if (this.tab === 'search' && this.query.trim().length >= 2) {
                        params.set('q', this.query.trim());
                    }
                    const resp = await fetch(this.endpointForTab() + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const ct = resp.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) {
                        throw new Error('Server returned non-JSON response.');
                    }
                    const data = await resp.json();
                    if (!resp.ok) throw new Error(data.message || 'Failed to load GIFs.');
                    const incoming = (data.data || []).map(g => ({
                        id: g.id || '', title: g.title || 'GIF',
                        url: g.url || g.preview_url || '', preview_url: g.preview_url || g.url || '',
                        provider: g.provider || 'giphy', width: g.width, height: g.height,
                    }));
                    this.items = reset ? incoming : this.items.concat(incoming);
                    this.cursor = data.meta?.next_cursor || null;
                } catch (e) {
                    this.error = e.message || 'Unable to load GIFs.';
                } finally {
                    this.loading = false;
                }
            },
            async send(item) {
                await $wire.{{ $sendAction ?? 'sendGif' }}({
                    id: item.id, title: item.title, url: item.url,
                    preview_url: item.preview_url, provider: item.provider,
                    width: item.width, height: item.height,
                });
                this.open = false;
            },
            async onSearch() {
                if (this.query.trim().length >= 2) {
                    this.tab = 'search';
                    this.activeCategory = null;
                    await this.load(true);
                } else if (this.tab === 'search') {
                    this.tab = 'trending';
                    await this.load(true);
                }
            },
        }"
        @keydown.escape.window="open = false"
        class="relative"
    >
        {{-- GIF Button --}}
        <button type="button" @click="toggle()" title="GIF"
            class="flex h-10 items-center justify-center rounded-lg border border-crm-border bg-white px-3 text-sm font-semibold text-crm-t2 transition hover:bg-crm-hover">
            GIF
        </button>

        {{-- Floating Panel — opens ABOVE the button, fixed height, internal scroll --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="open = false"
            style="display: none; position: fixed; width: 400px; height: 420px; z-index: 99999;"
            x-init="$watch('open', val => {
                if (val) {
                    const btn = $el.previousElementSibling;
                    const rect = btn.getBoundingClientRect();
                    $el.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 410)) + 'px';
                    $el.style.top = Math.max(8, rect.top - 428) + 'px';
                }
            })"
            class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_12px_40px_rgba(0,0,0,0.18)]"
        >
            {{-- Header: search + tabs --}}
            <div class="flex-shrink-0 border-b border-gray-200 bg-gray-50 px-3 py-2.5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-gray-700">GIF Picker</span>
                    <button type="button" @click="open = false" class="text-xs text-gray-400 hover:text-gray-700 font-semibold px-1">✕</button>
                </div>
                <input type="text" x-model="query" @input.debounce.400ms="onSearch()" placeholder="Search GIFs..."
                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                <div class="flex flex-wrap gap-1 mt-2">
                    <button type="button" @click="setTab('trending')" :class="tab === 'trending' && !activeCategory ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border border-gray-200'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Trending</button>
                    <button type="button" @click="setCategory('movies')" :class="activeCategory === 'movies' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border border-gray-200'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Movies</button>
                    <button type="button" @click="setCategory('reactions')" :class="activeCategory === 'reactions' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border border-gray-200'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Reactions</button>
                    <button type="button" @click="setCategory('funny')" :class="activeCategory === 'funny' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border border-gray-200'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Funny</button>
                    <button type="button" @click="setCategory('love')" :class="activeCategory === 'love' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border border-gray-200'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Love</button>
                    <button type="button" @click="setCategory('celebrations')" :class="activeCategory === 'celebrations' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border border-gray-200'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Celebrate</button>
                </div>
            </div>

            {{-- GIF Grid — fixed height, internal scroll only --}}
            <div class="flex-1 overflow-y-auto p-2">
                {{-- Loading --}}
                <template x-if="loading && items.length === 0">
                    <div class="grid grid-cols-3 gap-1.5">
                        <template x-for="i in 9" :key="i">
                            <div class="h-[90px] animate-pulse rounded-lg bg-gray-100"></div>
                        </template>
                    </div>
                </template>

                {{-- Error --}}
                <template x-if="error">
                    <div class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-600 font-medium" x-text="error"></div>
                </template>

                {{-- Empty --}}
                <template x-if="!loading && !error && items.length === 0">
                    <div class="flex items-center justify-center h-32 text-sm text-gray-400">
                        <span x-text="tab === 'search' && query.trim().length < 2 ? 'Type to search GIFs...' : 'No GIFs found.'"></span>
                    </div>
                </template>

                {{-- Results: compact 3-column grid --}}
                <div class="grid grid-cols-3 gap-1.5" x-show="items.length > 0">
                    <template x-for="item in items" :key="item.provider + '-' + item.id">
                        <button type="button" @click="send(item)"
                            class="overflow-hidden rounded-lg bg-gray-100 cursor-pointer hover:ring-2 hover:ring-blue-400 transition-all">
                            <img :src="item.preview_url || item.url" :alt="item.title"
                                class="w-full h-[90px] object-cover block" loading="lazy">
                        </button>
                    </template>
                </div>

                {{-- Load more --}}
                <div class="mt-2" x-show="cursor && !loading">
                    <button type="button" @click="load(false)" class="w-full rounded-lg border border-gray-200 bg-white py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50 transition">More GIFs</button>
                </div>
                <div x-show="loading && items.length > 0" class="text-center py-2">
                    <span class="text-xs text-gray-400">Loading...</span>
                </div>
            </div>
        </div>
    </div>
@endif
