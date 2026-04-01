@php
    $gifPickerId = $gifPickerId ?? 'gif-picker';
    $gifPickerPanelClass = $gifPickerPanelClass ?? 'right-0 bottom-full mb-2 w-[22rem]';
    $gifPickerSettings = $gifPickerSettings ?? [];
    $gifPickerDefaultTab = 'trending';

    if (!($gifPickerSettings['trending_enabled'] ?? true) && ($gifPickerSettings['movies_enabled'] ?? false)) {
        $gifPickerDefaultTab = 'movies';
    }

    if (!($gifPickerSettings['trending_enabled'] ?? true) && !($gifPickerSettings['movies_enabled'] ?? false) && ($gifPickerSettings['search_enabled'] ?? false)) {
        $gifPickerDefaultTab = 'search';
    }

    if (!($gifPickerSettings['trending_enabled'] ?? true) && !($gifPickerSettings['movies_enabled'] ?? false) && !($gifPickerSettings['search_enabled'] ?? false) && ($gifPickerSettings['recent_enabled'] ?? false)) {
        $gifPickerDefaultTab = 'recent';
    }

    if (!($gifPickerSettings['trending_enabled'] ?? true) && !($gifPickerSettings['movies_enabled'] ?? false) && !($gifPickerSettings['search_enabled'] ?? false) && !($gifPickerSettings['recent_enabled'] ?? false) && ($gifPickerSettings['favorites_enabled'] ?? false)) {
        $gifPickerDefaultTab = 'favorites';
    }
@endphp

@if($canUseGifPicker)
    <div
        class="relative"
        x-data="{
            open: false,
            loading: false,
            error: null,
            items: [],
            cursor: null,
            query: '',
            tab: @js($gifPickerDefaultTab),
            settings: @js($gifPickerSettings),
            userId: @js($currentUserId),
            favoriteKeys: {},
            favoriteRecords: {},
            async togglePicker() {
                this.open = !this.open;
                if (!this.open) {
                    return;
                }

                await this.refreshFavorites();
                await this.load(true);
            },
            canShowTab(name) {
                return !!this.settings[name + '_enabled'];
            },
            categories: {
                reactions: 'reactions',
                funny: 'funny',
                love: 'love',
                celebrations: 'celebrations party',
            },
            activeCategory: null,
            endpointForTab() {
                if (this.tab === 'movies') return '/api/gifs/movies';
                if (this.tab === 'search' || this.tab === 'category') return '/api/gifs/search';
                if (this.tab === 'recent') return '/api/gifs/recent';
                if (this.tab === 'favorites') return '/api/gifs/favorites';
                return '/api/gifs/trending';
            },
            async setTab(tabName) {
                this.tab = tabName;
                this.activeCategory = null;
                this.error = null;
                this.cursor = null;
                this.items = [];
                if (this.open) {
                    await this.load(true);
                }
            },
            async setCategory(name) {
                this.tab = 'category';
                this.activeCategory = name;
                this.query = this.categories[name] || name;
                this.error = null;
                this.cursor = null;
                this.items = [];
                if (this.open) {
                    await this.load(true);
                }
            },
            normalizeItem(item) {
                return {
                    id: item.id ?? '',
                    title: item.title ?? 'GIF',
                    url: item.url ?? item.preview_url ?? '',
                    preview_url: item.preview_url ?? item.url ?? '',
                    provider: item.provider ?? this.settings.provider ?? 'giphy',
                    width: item.width ?? null,
                    height: item.height ?? null,
                    favorite_id: item.favorite_id ?? null,
                };
            },
            async load(reset = false) {
                if (this.tab === 'search' && this.query.trim().length < 2) {
                    this.items = [];
                    this.cursor = null;
                    this.error = null;
                    return;
                }

                this.loading = true;
                this.error = null;

                try {
                    const params = new URLSearchParams();
                    if (this.userId) params.set('user_id', this.userId);
                    params.set('limit', String(this.settings.results_limit || 24));
                    if (!reset && this.cursor) params.set('cursor', this.cursor);
                    if (this.tab === 'category' && this.activeCategory) {
                        params.set('q', this.categories[this.activeCategory] || this.activeCategory);
                    } else if ((this.tab === 'search' || this.tab === 'movies') && this.query.trim().length >= 2) {
                        params.set('q', this.query.trim());
                    }

                    const response = await fetch(`${this.endpointForTab()}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to load GIFs right now.');
                    }

                    const incoming = (payload.data || []).map((item) => this.normalizeItem(item));
                    this.items = reset ? incoming : this.items.concat(incoming);
                    this.cursor = payload.meta?.next_cursor || null;
                } catch (error) {
                    this.error = error.message || 'Unable to load GIFs right now.';
                } finally {
                    this.loading = false;
                }
            },
            favoriteKey(item) {
                return `${item.provider}:${item.id}`;
            },
            isFavorite(item) {
                return !!this.favoriteKeys[this.favoriteKey(item)];
            },
            async refreshFavorites() {
                if (!this.settings.favorites_enabled || !this.userId) {
                    this.favoriteKeys = {};
                    this.favoriteRecords = {};
                    return;
                }

                try {
                    const params = new URLSearchParams({
                        user_id: this.userId,
                        limit: String(this.settings.results_limit || 24),
                    });
                    const response = await fetch(`/api/gifs/favorites?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to load favorites right now.');
                    }

                    const keys = {};
                    const records = {};

                    (payload.data || []).forEach((item) => {
                        const normalized = this.normalizeItem(item);
                        const key = this.favoriteKey(normalized);
                        keys[key] = true;
                        records[key] = normalized.favorite_id;
                    });

                    this.favoriteKeys = keys;
                    this.favoriteRecords = records;
                } catch (error) {
                    this.favoriteKeys = {};
                    this.favoriteRecords = {};
                }
            },
            async toggleFavorite(item) {
                if (!this.settings.favorites_enabled || !this.userId) {
                    return;
                }

                const key = this.favoriteKey(item);
                const favoriteId = this.favoriteRecords[key];

                try {
                    if (favoriteId) {
                        const response = await fetch(`/api/gifs/favorites/${favoriteId}?user_id=${encodeURIComponent(this.userId)}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            throw new Error(payload.message || 'Unable to update favorites right now.');
                        }

                        delete this.favoriteKeys[key];
                        delete this.favoriteRecords[key];
                        if (this.tab === 'favorites') {
                            this.items = this.items.filter((entry) => this.favoriteKey(entry) !== key);
                        }
                        return;
                    }

                    const response = await fetch('/api/gifs/favorites', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            id: item.id,
                            title: item.title,
                            url: item.url,
                            preview_url: item.preview_url,
                            provider: item.provider,
                            user_id: this.userId,
                        }),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to save favorite right now.');
                    }

                    this.favoriteKeys[key] = true;
                    this.favoriteRecords[key] = payload.data?.id || null;
                    if (this.tab === 'favorites') {
                        this.items.unshift(this.normalizeItem({ ...item, favorite_id: payload.data?.id || null }));
                    }
                } catch (error) {
                    this.error = error.message || 'Unable to update favorites right now.';
                }
            },
            async send(item) {
                await $wire.{{ $sendAction ?? 'sendGif' }}(this.normalizeItem(item));
                this.open = false;
            },
            async onSearchInput() {
                if (!this.settings.search_enabled) {
                    return;
                }

                if (this.query.trim().length >= 2) {
                    this.tab = 'search';
                    await this.load(true);
                    return;
                }

                if (this.tab === 'search') {
                    this.tab = @js($gifPickerDefaultTab);
                    await this.load(true);
                }
            },
        }"
        @keydown.escape.window="open = false"
        @click.outside="open = false"
    >
        <button
            type="button"
            @click="togglePicker()"
            class="flex h-10 items-center justify-center rounded-lg border border-crm-border bg-white px-3 text-sm font-semibold text-crm-t2 transition hover:bg-crm-hover"
            title="Open GIF keyboard"
        >
            GIF
        </button>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            class="absolute {{ $gifPickerPanelClass }} z-40 overflow-hidden rounded-2xl border border-crm-border bg-white shadow-2xl"
            style="display: none;"
        >
            <div class="border-b border-crm-border bg-crm-surface px-3 py-3">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold text-crm-t1">GIF Keyboard</div>
                        <div class="text-[11px] text-crm-t3">Trending, movie moments, and search</div>
                    </div>
                    <button type="button" @click="open = false" class="rounded-md px-2 py-1 text-xs font-semibold text-crm-t3 transition hover:bg-crm-hover hover:text-crm-t1">Close</button>
                </div>

                @if($gifPickerSettings['search_enabled'] ?? true)
                    <div class="mt-3">
                        <input
                            type="text"
                            x-model="query"
                            @input.debounce.350ms="onSearchInput()"
                            placeholder="Search GIFs or movie scenes"
                            class="w-full rounded-xl border border-crm-border bg-white px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                        >
                    </div>
                @endif

                <div class="mt-3 flex flex-wrap gap-1.5">
                    @if($gifPickerSettings['trending_enabled'] ?? true)
                        <button type="button" @click="setTab('trending')" :class="tab === 'trending' ? 'bg-blue-600 text-white' : 'bg-white text-crm-t2 border border-crm-border'" class="rounded-full px-3 py-1 text-xs font-semibold transition">Trending</button>
                    @endif
                    @if($gifPickerSettings['movies_enabled'] ?? true)
                        <button type="button" @click="setTab('movies')" :class="tab === 'movies' ? 'bg-blue-600 text-white' : 'bg-white text-crm-t2 border border-crm-border'" class="rounded-full px-3 py-1 text-xs font-semibold transition">Movies</button>
                    @endif
                    @if($gifPickerSettings['search_enabled'] ?? true)
                        <button type="button" @click="setTab('search')" :class="tab === 'search' ? 'bg-blue-600 text-white' : 'bg-white text-crm-t2 border border-crm-border'" class="rounded-full px-3 py-1 text-xs font-semibold transition">Search</button>
                    @endif
                    @if($gifPickerSettings['recent_enabled'] ?? true)
                        <button type="button" @click="setTab('recent')" :class="tab === 'recent' ? 'bg-blue-600 text-white' : 'bg-white text-crm-t2 border border-crm-border'" class="rounded-full px-3 py-1 text-xs font-semibold transition">Recent</button>
                    @endif
                    @if($gifPickerSettings['favorites_enabled'] ?? true)
                        <button type="button" @click="setTab('favorites')" :class="tab === 'favorites' ? 'bg-blue-600 text-white' : 'bg-white text-crm-t2 border border-crm-border'" class="rounded-full px-3 py-1 text-xs font-semibold transition">Favorites</button>
                    @endif
                </div>

                {{-- Category quick filters --}}
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button type="button" @click="setCategory('reactions')" :class="activeCategory === 'reactions' ? 'bg-purple-600 text-white' : 'bg-crm-surface text-crm-t3 border border-crm-border'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Reactions</button>
                    <button type="button" @click="setCategory('funny')" :class="activeCategory === 'funny' ? 'bg-purple-600 text-white' : 'bg-crm-surface text-crm-t3 border border-crm-border'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Funny</button>
                    <button type="button" @click="setCategory('love')" :class="activeCategory === 'love' ? 'bg-purple-600 text-white' : 'bg-crm-surface text-crm-t3 border border-crm-border'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Love</button>
                    <button type="button" @click="setCategory('celebrations')" :class="activeCategory === 'celebrations' ? 'bg-purple-600 text-white' : 'bg-crm-surface text-crm-t3 border border-crm-border'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Celebrations</button>
                </div>
            </div>

            <div class="max-h-[24rem] overflow-y-auto p-3">
                <template x-if="loading && items.length === 0">
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="index in 6" :key="index">
                            <div class="h-28 animate-pulse rounded-xl bg-slate-100"></div>
                        </template>
                    </div>
                </template>

                <template x-if="error">
                    <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700" x-text="error"></div>
                </template>

                <template x-if="!loading && !error && items.length === 0">
                    <div class="flex h-40 items-center justify-center rounded-xl border border-dashed border-crm-border bg-crm-surface text-center text-sm text-crm-t3">
                        <span x-text="tab === 'search' && query.trim().length < 2 ? 'Type at least 2 characters to search GIFs.' : 'No GIFs found for this section yet.'"></span>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-2" x-show="items.length > 0">
                    <template x-for="item in items" :key="`${item.provider}-${item.id}-${item.favorite_id || 'gif'}`">
                        <div class="group overflow-hidden rounded-xl border border-crm-border bg-crm-surface shadow-sm">
                            <button type="button" @click="send(item)" class="block w-full overflow-hidden bg-black">
                                <img :src="item.preview_url || item.url" :alt="item.title" class="h-28 w-full object-cover transition duration-200 group-hover:scale-[1.03]">
                            </button>
                            <div class="flex items-center justify-between gap-2 px-2 py-2">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-semibold text-crm-t1" x-text="item.title || 'GIF'"></div>
                                    <div class="text-[10px] uppercase tracking-wide text-crm-t3" x-text="item.provider"></div>
                                </div>
                                @if($gifPickerSettings['favorites_enabled'] ?? true)
                                    <button type="button" @click="toggleFavorite(item)" :class="isFavorite(item) ? 'bg-amber-100 text-amber-700' : 'bg-white text-crm-t3 border border-crm-border'" class="rounded-full px-2 py-1 text-[10px] font-bold transition">★</button>
                                @endif
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-3" x-show="cursor">
                    <button type="button" @click="load(false)" class="w-full rounded-xl border border-crm-border bg-white px-3 py-2 text-sm font-semibold text-crm-t2 transition hover:bg-crm-hover">Load More</button>
                </div>
            </div>
        </div>
    </div>
@endif