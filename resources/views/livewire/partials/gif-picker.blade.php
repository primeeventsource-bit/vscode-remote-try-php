@php $gifPickerSettings = $gifPickerSettings ?? []; @endphp

@if($canUseGifPicker ?? false)
<div x-data="{
    open: false,
    loading: false, error: null, items: [], cursor: null, query: '', tab: 'trending',
    settings: @js($gifPickerSettings), userId: @js($currentUserId ?? 0),
    categories: { reactions:'reactions', funny:'funny', love:'love', celebrations:'celebrations party', movies:'movie reactions' },
    activeCategory: null,
    ep() { return (this.tab==='category'||this.tab==='search') ? '/api/gifs/search' : '/api/gifs/trending'; },
    toggle() { this.open=!this.open; if(this.open&&!this.items.length) this.load(true); },
    close() { this.open=false; },
    async setTab(n) { this.tab=n; this.activeCategory=null; this.error=null; this.cursor=null; this.items=[]; await this.load(true); },
    async setCat(n) { this.tab='category'; this.activeCategory=n; this.query=this.categories[n]||n; this.error=null; this.cursor=null; this.items=[]; await this.load(true); },
    async load(reset=false) {
        if(this.tab==='search'&&this.query.trim().length<2){this.items=[];return;}
        this.loading=true; this.error=null;
        try {
            const p=new URLSearchParams(); if(this.userId!==null&&this.userId!==undefined) p.set('user_id',String(this.userId)); p.set('limit','20');
            if(!reset&&this.cursor) p.set('cursor',this.cursor);
            if(this.tab==='category'&&this.activeCategory) p.set('q',this.categories[this.activeCategory]);
            else if(this.tab==='search'&&this.query.trim().length>=2) p.set('q',this.query.trim());
            const r=await fetch(this.ep()+'?'+p,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if(!r.ok||!(r.headers.get('content-type')||'').includes('json')) throw new Error('Failed ('+r.status+')');
            const d=await r.json();
            const items=(d.data||[]).map(g=>({id:g.id||'',title:g.title||'GIF',url:g.url||g.preview_url||'',preview_url:g.preview_url||g.url||'',provider:g.provider||'giphy',width:g.width,height:g.height}));
            this.items=reset?items:this.items.concat(items); this.cursor=d.meta?.next_cursor||null;
        } catch(e){this.error=e.message||'Unable to load GIFs.';}
        finally{this.loading=false;}
    },
    async send(item) {
        await $wire.{{ $sendAction ?? 'sendGif' }}({id:item.id,title:item.title,url:item.url,preview_url:item.preview_url,provider:item.provider,width:item.width,height:item.height});
        this.open=false;
    },
    async onSearch() { if(this.query.trim().length>=2){this.tab='search';this.activeCategory=null;await this.load(true);}else if(this.tab==='search'){this.tab='trending';await this.load(true);} },
}" class="relative" @keydown.escape.window="close()">

    <button type="button" @click.stop="toggle()" title="GIF"
        class="flex h-10 items-center justify-center rounded-lg border border-crm-border bg-white px-3 text-sm font-semibold text-crm-t2 transition hover:bg-crm-hover">GIF</button>

    <div x-show="open" x-cloak @click.outside="close()" @click.stop
        x-transition.opacity.duration.100ms
        class="fixed z-[99999] flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_12px_40px_rgba(0,0,0,0.18)]"
        x-ref="gifpanel"
        x-effect="if(open){$nextTick(()=>{const b=$root.querySelector('button[title=GIF]');if(!b||!$refs.gifpanel)return;const r=b.getBoundingClientRect();$refs.gifpanel.style.width='340px';$refs.gifpanel.style.height='380px';$refs.gifpanel.style.left=Math.max(8,r.right-340)+'px';$refs.gifpanel.style.top=Math.max(8,r.top-388)+'px';})}">

        <div class="flex-shrink-0 border-b border-gray-100 bg-gray-50/80 px-3 py-2">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-bold text-gray-700">GIF Picker</span>
                <button type="button" @click="close()" class="text-gray-400 hover:text-gray-700 text-sm leading-none">&times;</button>
            </div>
            <input id="gif-search" name="gif_search" type="text" x-model="query" @input.debounce.400ms="onSearch()" placeholder="Search GIFs..."
                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
            <div class="flex flex-wrap gap-1 mt-2">
                <button type="button" @click="setTab('trending')" :class="tab==='trending'&&!activeCategory?'bg-blue-600 text-white':'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Trending</button>
                <button type="button" @click="setCat('movies')" :class="activeCategory==='movies'?'bg-blue-600 text-white':'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Movies</button>
                <button type="button" @click="setCat('reactions')" :class="activeCategory==='reactions'?'bg-blue-600 text-white':'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Reactions</button>
                <button type="button" @click="setCat('funny')" :class="activeCategory==='funny'?'bg-blue-600 text-white':'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Funny</button>
                <button type="button" @click="setCat('love')" :class="activeCategory==='love'?'bg-blue-600 text-white':'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Love</button>
                <button type="button" @click="setCat('celebrations')" :class="activeCategory==='celebrations'?'bg-blue-600 text-white':'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition">Celebrate</button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-2 min-h-0">
            <template x-if="loading&&!items.length">
                <div class="grid grid-cols-3 gap-1.5"><template x-for="i in 9" :key="i"><div class="h-[90px] animate-pulse rounded-lg bg-gray-100"></div></template></div>
            </template>
            <template x-if="error"><div class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-600" x-text="error"></div></template>
            <template x-if="!loading&&!error&&!items.length"><div class="flex items-center justify-center h-32 text-sm text-gray-400"><span x-text="tab==='search'&&query.trim().length<2?'Type to search...':'No GIFs found.'"></span></div></template>
            <div class="grid grid-cols-3 gap-1.5" x-show="items.length">
                <template x-for="item in items" :key="item.provider+'-'+item.id">
                    <button type="button" @click="send(item)" class="overflow-hidden rounded-lg bg-gray-100 cursor-pointer hover:ring-2 hover:ring-blue-400 transition-all outline-none">
                        <img :src="item.preview_url||item.url" :alt="item.title" class="w-full h-[90px] object-cover block" loading="lazy">
                    </button>
                </template>
            </div>
            <div class="mt-2" x-show="cursor&&!loading"><button type="button" @click="load(false)" class="w-full rounded-lg border border-gray-200 bg-white py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50">More</button></div>
            <div x-show="loading&&items.length" class="text-center py-2 text-xs text-gray-400">Loading...</div>
        </div>
    </div>
</div>
@endif
