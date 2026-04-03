@php
    $totalUnread = 0;
    if (isset($unreadCounts) && $unreadCounts instanceof \Illuminate\Support\Collection) {
        $totalUnread = $unreadCounts->sum();
    }
@endphp
<div
    x-data="{
        open: false,
        px: Math.max(20, window.innerWidth - 400),
        py: Math.max(60, window.innerHeight - 540),
        dragging: false,
        ox: 0, oy: 0,
        startDrag(e) { if(e.button!==undefined&&e.button!==0)return; this.dragging=true; this.ox=e.clientX-this.px; this.oy=e.clientY-this.py; },
        doDrag(e) { if(!this.dragging)return; this.px=Math.max(0,Math.min(e.clientX-this.ox,innerWidth-385)); this.py=Math.max(44,Math.min(e.clientY-this.oy,innerHeight-510)); },
        stopDrag() { this.dragging=false; }
    }"
    @mousemove.window="if(dragging) doDrag($event)"
    @mouseup.window="if(dragging) stopDrag()"
>
    <style>
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
        @keyframes wdg-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.7;transform:scale(1.15)} }
        .unread { animation:blink 1s infinite; background-color:red; }
        .wdg-badge-red { animation:wdg-pulse 1.5s ease-in-out infinite; background:#ef4444; }
        .wdg-msg-unread { background:rgba(239,68,68,0.08); border-left:2px solid #ef4444; }
    </style>

    {{-- Floating Bubble Button --}}
    <button @click="open = !open; if(open) $wire.$refresh()" title="Toggle Chat"
        class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full {{ $totalUnread > 0 ? 'unread' : 'bg-blue-600' }} text-white shadow-lg shadow-blue-600/30 transition hover:scale-105 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">
        @if($totalUnread > 0)
            <span class="absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-30 animate-ping"></span>
        @endif
        <span class="relative text-2xl leading-none" x-text="open ? '✕' : '💬'"></span>
        @if($totalUnread > 0)
            <span class="absolute -top-1 -right-1 min-w-[22px] h-[22px] flex items-center justify-center text-[10px] font-bold text-white wdg-badge-red rounded-full px-1 z-10">{{ $totalUnread }}</span>
        @endif
    </button>

    {{-- Chat Popup --}}
    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        :style="`position:fixed;left:${px}px;top:${py}px;width:380px;height:500px;z-index:9999;`"
        class="flex flex-col overflow-hidden rounded-xl border border-crm-border bg-white shadow-2xl">

        {{-- Header --}}
        <div class="flex flex-shrink-0 cursor-grab items-center gap-2 border-b border-crm-border bg-crm-surface px-4 py-3 active:cursor-grabbing select-none" @mousedown="startDrag($event)">
            @if($selectedChat && $activeChat)
                <button wire:click="clearChat" class="mr-1 flex h-6 w-6 items-center justify-center rounded text-crm-t3 hover:bg-crm-hover hover:text-crm-t1 transition text-sm">←</button>
                <span class="flex-1 text-sm font-bold truncate">{{ $activeChat->name ?? 'Chat' }}</span>
            @else
                <span class="text-base">💬</span>
                <h4 class="flex-1 text-sm font-bold">Chat</h4>
                @if($totalUnread > 0)
                    <span class="min-w-[18px] h-[18px] flex items-center justify-center text-[9px] font-bold text-white wdg-badge-red rounded-full px-1">{{ $totalUnread }}</span>
                @endif
            @endif
            <button @click="open = false" class="flex h-6 w-6 items-center justify-center rounded text-lg text-crm-t3 hover:bg-crm-hover hover:text-crm-t1 transition leading-none">&times;</button>
        </div>

        {{-- Chat List --}}
        @if(!$selectedChat && !$showNewChatForm)
            {{-- Search Bar --}}
            <div class="px-3 py-2 border-b border-crm-border">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-crm-t3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input id="chat-search" wire:model.live.debounce.300ms="chatSearch" type="text" placeholder="Search chats, users, messages..."
                        class="w-full pl-8 pr-3 py-1.5 text-xs bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400"
                        @keydown.escape="$wire.set('chatSearch', '')">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                {{-- Search Results --}}
                @if($isSearching)
                    @if($searchResults->isEmpty() && $searchMessageResults->isEmpty())
                        <div class="flex items-center justify-center p-8 text-center">
                            <div>
                                <div class="text-2xl opacity-20 mb-1">🔍</div>
                                <p class="text-xs text-crm-t3">No results for "{{ $chatSearch }}"</p>
                            </div>
                        </div>
                    @else
                        {{-- Matching Chats --}}
                        @if($searchResults->isNotEmpty())
                            <div class="px-3 pt-2 pb-1"><span class="text-[9px] text-crm-t3 uppercase font-semibold">Chats ({{ $searchResults->count() }})</span></div>
                        @endif
                        @foreach($searchResults as $chat)
                            @php
                                $members = is_array($chat->members) ? $chat->members : json_decode($chat->members ?? '[]', true);
                                $otherId = collect($members)->first(fn($m) => (int)$m !== auth()->id());
                                $other = $otherId ? $users->get($otherId) : null;
                                $bg = $other?->color ?? '#3b82f6';
                                $initials = $chat->type === 'channel' ? '#' : ($other?->avatar ?? substr($other?->name ?? 'G', 0, 2));
                                $displayName = $chat->type === 'dm' ? ($other?->name ?? $chat->name ?? 'DM') : $chat->name;
                            @endphp
                            <button wire:key="sr-{{ $chat->id }}" wire:click="selectChat({{ $chat->id }})"
                                class="flex w-full items-center gap-3 border-b border-crm-border px-4 py-2.5 text-left transition hover:bg-blue-50">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full text-[9px] font-bold text-white flex-shrink-0" style="background:{{ $bg }}">{{ $initials }}</div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-semibold">{{ $displayName }}</div>
                                    <div class="text-[9px] text-blue-500 font-medium">Chat match</div>
                                </div>
                            </button>
                        @endforeach

                        {{-- Matching Messages --}}
                        @if($searchMessageResults->isNotEmpty())
                            <div class="px-3 pt-2 pb-1"><span class="text-[9px] text-crm-t3 uppercase font-semibold">Messages ({{ $searchMessageResults->count() }})</span></div>
                        @endif
                        @foreach($searchMessageResults as $mr)
                            @php
                                $mrUser = $users->get($mr->sender_id);
                                $mrChat = $chats->firstWhere('id', $mr->chat_id);
                                $mrName = $mrChat?->name ?? $mrUser?->name ?? 'Chat';
                            @endphp
                            <button wire:key="mr-{{ $mr->id }}" wire:click="selectChat({{ $mr->chat_id }})"
                                class="flex w-full items-center gap-3 border-b border-crm-border px-4 py-2.5 text-left transition hover:bg-amber-50">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-600 text-[9px] font-bold flex-shrink-0">💬</div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-semibold">{{ $mrName }}</div>
                                    <div class="truncate text-[10px] text-crm-t2">{{ Str::limit($mr->text, 60) }}</div>
                                    <div class="text-[9px] text-amber-500 font-medium">Message match · {{ $mr->created_at?->diffForHumans() }}</div>
                                </div>
                            </button>
                        @endforeach
                    @endif
                @else
                {{-- DM Folder --}}
                @php $dmChats = $chats->where('type', 'dm'); $groupChats = $chats->filter(fn($c) => $c->type !== 'dm'); @endphp

                @if($dmChats->isNotEmpty())
                    <div class="px-3 pt-2 pb-1 flex items-center justify-between">
                        <span class="text-[9px] text-crm-t3 uppercase font-semibold tracking-wider">Direct Messages</span>
                        <span class="text-[9px] text-crm-t3">{{ $dmChats->count() }}</span>
                    </div>
                    @foreach($dmChats as $chat)
                        @php
                            $members = is_array($chat->members) ? $chat->members : json_decode($chat->members ?? '[]', true);
                            $otherId = collect($members)->first(fn($m) => (int)$m !== auth()->id());
                            $other = $otherId ? $users->get($otherId) : null;
                            $bg = $other?->color ?? '#3b82f6';
                            $initials = $other?->avatar ?? substr($other?->name ?? '?', 0, 2);
                            $displayName = $other?->name ?? $chat->name ?? 'DM';
                            $chatUnread = $unreadCounts[$chat->id] ?? 0;
                        @endphp
                        <button wire:key="chat-{{ $chat->id }}" wire:click="selectChat({{ $chat->id }})"
                            class="flex w-full items-center gap-3 border-b border-crm-border px-4 py-2.5 text-left transition {{ $chatUnread > 0 ? 'bg-red-50/60' : 'hover:bg-crm-hover' }}">
                            <div class="relative flex-shrink-0">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full text-[9px] font-bold text-white" style="background:{{ $bg }}">{{ $initials }}</div>
                                @if($chatUnread > 0)<span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full wdg-badge-red"></span>@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs {{ $chatUnread > 0 ? 'font-bold' : 'font-semibold' }}">{{ $displayName }}</div>
                                <div class="text-[10px] text-crm-t3">{{ $chat->updated_at?->diffForHumans() ?? '' }}</div>
                            </div>
                            @if($chatUnread > 0)
                                <span class="min-w-[16px] h-[16px] flex items-center justify-center text-[8px] font-bold text-white rounded-full px-1 wdg-badge-red">{{ $chatUnread }}</span>
                            @endif
                        </button>
                    @endforeach
                @endif

                {{-- Group Folder --}}
                @if($groupChats->isNotEmpty())
                    <div class="px-3 pt-3 pb-1 flex items-center justify-between">
                        <span class="text-[9px] text-crm-t3 uppercase font-semibold tracking-wider">Groups</span>
                        <span class="text-[9px] text-crm-t3">{{ $groupChats->count() }}</span>
                    </div>
                    @foreach($groupChats as $chat)
                        @php $chatUnread = $unreadCounts[$chat->id] ?? 0; @endphp
                        <button wire:key="chat-{{ $chat->id }}" wire:click="selectChat({{ $chat->id }})"
                            class="flex w-full items-center gap-3 border-b border-crm-border px-4 py-2.5 text-left transition {{ $chatUnread > 0 ? 'bg-red-50/60' : 'hover:bg-crm-hover' }}">
                            <div class="relative flex-shrink-0">
                                @if($chat->icon_path ?? false)
                                    <img src="{{ asset('storage/' . $chat->icon_path) }}" class="w-8 h-8 rounded-lg object-cover">
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-crm-card border border-crm-border text-[9px] font-bold text-crm-t3">G</div>
                                @endif
                                @if($chatUnread > 0)<span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full wdg-badge-red"></span>@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs {{ $chatUnread > 0 ? 'font-bold' : 'font-semibold' }}">{{ $chat->name }}</div>
                                <div class="text-[10px] text-crm-t3">{{ $chat->updated_at?->diffForHumans() ?? '' }}</div>
                            </div>
                            @if($chatUnread > 0)
                                <span class="min-w-[16px] h-[16px] flex items-center justify-center text-[8px] font-bold text-white rounded-full px-1 wdg-badge-red">{{ $chatUnread }}</span>
                            @endif
                        </button>
                    @endforeach
                @endif

                @if($dmChats->isEmpty() && $groupChats->isEmpty())
                    <div class="flex flex-1 items-center justify-center p-8 text-center">
                        <p class="text-sm text-crm-t3">No conversations yet.</p>
                    </div>
                @endif
                @endif
            </div>
            <div class="flex-shrink-0 border-t border-crm-border px-3 py-2">
                <button wire:click="toggleNewChatForm" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">+ New Chat</button>
            </div>

        {{-- New Chat Form --}}
        @elseif($showNewChatForm)
            <div class="flex-1 overflow-y-auto p-4">
                <div class="space-y-3">
                    @if($newChatError)
                        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{{ $newChatError }}</div>
                    @endif
                    <div>
                        <label for="wdg-chat-type" class="text-xs text-crm-t3 uppercase font-semibold">Chat Type</label>
                        <select id="wdg-chat-type" wire:model.live="newChatType" class="w-full mt-1 rounded border border-crm-border bg-white px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                            <option value="dm">Direct Message</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    @if($newChatType === 'group')
                    <div>
                        <label for="wdg-group-name" class="text-xs text-crm-t3 uppercase font-semibold">Group Name</label>
                        <input id="wdg-group-name" wire:model="newChatName" type="text" placeholder="E.g., Sales Team"
                               class="w-full mt-1 rounded border border-crm-border bg-white px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                    </div>
                    @endif
                    <div>
                        <label class="text-xs text-crm-t3 uppercase font-semibold">Select {{ $newChatType === 'dm' ? 'Person' : 'Members' }}</label>
                        <div class="mt-1 max-h-36 overflow-y-auto border border-crm-border rounded bg-white">
                            @foreach($users as $u)
                                @if($u->id !== auth()->id())
                                    <label class="flex items-center gap-2 border-b border-crm-border px-3 py-2 last:border-0 cursor-pointer hover:bg-crm-hover text-sm">
                                        <input id="wdg-member-{{ $u->id }}" type="checkbox" wire:model="newChatMembers" value="{{ $u->id }}"
                                               @if($newChatType === 'dm' && count($newChatMembers) > 0 && !in_array($u->id, $newChatMembers)) disabled @endif
                                               class="h-4 w-4 rounded border-crm-border">
                                        <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white"
                                             style="background:{{ $u->color ?? '#6b7280' }}">{{ $u->avatar ?? substr($u->name, 0, 2) }}</div>
                                        <span>{{ $u->name }}</span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="toggleNewChatForm" class="flex-1 rounded bg-crm-card border border-crm-border px-2 py-1.5 text-xs font-semibold text-crm-t2 transition hover:bg-crm-hover">Cancel</button>
                        <button wire:click="createNewChat" class="flex-1 rounded bg-blue-600 px-2 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700">Create</button>
                    </div>
                </div>
            </div>

        {{-- Message Thread --}}
        @else
            <div class="flex-1 overflow-y-auto space-y-2 p-3" id="cwt-messages"
                 x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                @php $prevDate = null; @endphp
                @forelse($messages as $msg)
                    @php
                        $msgUser = $users->get($msg->sender_id);
                        $isMine = $msg->sender_id === auth()->id();
                        $curDate = $msg->created_at?->format('Y-m-d');
                        $showDate = $curDate !== $prevDate;
                        $prevDate = $curDate;
                        $isUnread = !$isMine && empty($msg->seen_at);
                    @endphp
                    @if($showDate)
                        <div class="flex justify-center my-2">
                            <span class="px-2.5 py-0.5 rounded-full bg-crm-surface border border-crm-border text-[9px] font-semibold text-crm-t3">
                                {{ $msg->created_at?->isToday() ? 'Today' : ($msg->created_at?->isYesterday() ? 'Yesterday' : $msg->created_at?->format('M j, Y')) }}
                            </span>
                        </div>
                    @endif
                    <div class="flex items-end gap-2 {{ $isMine ? 'flex-row-reverse' : '' }} {{ $isUnread ? 'wdg-msg-unread rounded-md px-1 py-0.5' : '' }}">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white"
                             style="background:{{ $msgUser?->color ?? '#6b7280' }}">
                            {{ $msgUser?->avatar ?? substr($msgUser?->name ?? '?', 0, 2) }}
                        </div>
                        @if(($msg->message_type ?? 'text') === 'gif' && $msg->gif_url)
                            <div class="max-w-[72%] overflow-hidden rounded-xl border {{ $isMine ? 'border-blue-500 bg-blue-600/10' : 'border-crm-border bg-white' }}">
                                <a href="{{ $msg->gif_url }}" target="_blank" rel="noreferrer" class="block">
                                    <img src="{{ $msg->gif_preview_url ?: $msg->gif_url }}" alt="{{ $msg->gif_title ?? 'GIF' }}" class="max-h-52 w-full object-cover" loading="lazy">
                                </a>
                                <div class="flex items-center justify-between px-2 py-1 {{ $isMine ? 'bg-blue-600 text-blue-100' : 'bg-crm-card text-crm-t3' }}">
                                    <span class="text-[9px] truncate">{{ $msg->gif_title ?: 'GIF' }}</span>
                                    <span class="text-[9px] ml-1 flex-shrink-0">{{ $msg->created_at?->format('g:i A') ?? '' }}</span>
                                </div>
                            </div>
                        @else
                            <div class="max-w-[72%] rounded-lg px-3 pt-1.5 pb-1 text-sm {{ $isMine ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-crm-card border border-crm-border text-crm-t1 rounded-bl-sm' }}">
                                <div class="whitespace-pre-line">{{ $msg->text ?? '' }}</div>
                                {{-- Convert to Deal button on transfer messages for closers --}}
                                @if(!$isMine && auth()->user()?->hasRole('closer', 'master_admin', 'admin') && str_contains($msg->text ?? '', 'Lead Transfer'))
                                    @php
                                        preg_match('/Lead #(\d+)/', $msg->text ?? '', $leadMatch);
                                        $transferLeadId = (int) ($leadMatch[1] ?? 0);
                                        $transferLead = $transferLeadId ? \App\Models\Lead::find($transferLeadId) : null;
                                    @endphp
                                    @if($transferLead && $transferLead->disposition !== 'Converted to Deal')
                                        <button wire:click="openDealForm({{ $transferLeadId }})" class="mt-1.5 w-full px-2 py-1.5 text-[10px] font-bold bg-emerald-500 text-white rounded hover:bg-emerald-600 transition">Convert to Deal</button>
                                    @elseif($transferLead && $transferLead->disposition === 'Converted to Deal')
                                        <div class="mt-1 text-[9px] text-emerald-600 font-semibold">✓ Converted to Deal</div>
                                    @endif
                                @endif
                                <div class="flex items-center gap-1 justify-end {{ $isMine ? 'text-blue-200' : 'text-crm-t3' }}">
                                    <span class="text-[9px]">{{ $msg->created_at?->format('g:i A') ?? '' }}</span>
                                    @if($isMine)
                                        <span class="text-[9px]">{{ !empty($msg->seen_at) ? '✓✓' : '✓' }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-crm-t3">No messages yet. Say hello!</p>
                @endforelse
            </div>

            {{-- Inline Deal Form (replaces message area when open) --}}
            @if($showDealForm)
                <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-emerald-50/30">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-700">Convert Lead to Deal</span>
                        <button wire:click="closeDealForm" class="text-xs text-crm-t3 hover:text-crm-t1">&times;</button>
                    </div>
                    @if($dealFormError)
                        <div class="rounded bg-red-50 border border-red-200 px-2 py-1.5 text-[10px] text-red-600 font-medium">{{ $dealFormError }}</div>
                    @endif
                    <div class="space-y-1.5">
                        <div class="text-[9px] text-crm-t3 uppercase font-semibold">Customer</div>
                        <input id="df-name" wire:model="dealForm.owner_name" type="text" placeholder="Owner Name *" class="w-full px-2 py-1 text-xs border border-crm-border rounded">
                        <div class="grid grid-cols-2 gap-1.5">
                            <input id="df-phone" wire:model="dealForm.primary_phone" type="text" placeholder="Phone" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <input id="df-email" wire:model="dealForm.email" type="email" placeholder="Email" class="px-2 py-1 text-xs border border-crm-border rounded">
                        </div>
                        <input id="df-address" wire:model="dealForm.mailing_address" type="text" placeholder="Mailing Address" class="w-full px-2 py-1 text-xs border border-crm-border rounded">
                        <input id="df-csz" wire:model="dealForm.city_state_zip" type="text" placeholder="City, State, Zip" class="w-full px-2 py-1 text-xs border border-crm-border rounded">

                        <div class="text-[9px] text-crm-t3 uppercase font-semibold mt-2">Property</div>
                        <div class="grid grid-cols-2 gap-1.5">
                            <input id="df-resort" wire:model="dealForm.resort_name" type="text" placeholder="Resort Name" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <input id="df-rloc" wire:model="dealForm.resort_city_state" type="text" placeholder="Resort Location" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <input id="df-weeks" wire:model="dealForm.weeks" type="text" placeholder="Weeks" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <input id="df-bedbath" wire:model="dealForm.bed_bath" type="text" placeholder="Bed/Bath" class="px-2 py-1 text-xs border border-crm-border rounded">
                        </div>

                        <div class="text-[9px] text-crm-t3 uppercase font-semibold mt-2">Pricing</div>
                        <div class="grid grid-cols-2 gap-1.5">
                            <input id="df-fee" wire:model="dealForm.fee" type="number" step="0.01" placeholder="Fee *" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <input id="df-sale" wire:model="dealForm.asking_sale_price" type="text" placeholder="Sale Price" class="px-2 py-1 text-xs border border-crm-border rounded">
                        </div>

                        <div class="text-[9px] text-crm-t3 uppercase font-semibold mt-2">Payment</div>
                        <input id="df-cardholder" wire:model="dealForm.name_on_card" type="text" placeholder="Name on Card" class="w-full px-2 py-1 text-xs border border-crm-border rounded">
                        <div class="grid grid-cols-3 gap-1.5">
                            <input id="df-cardnum" wire:model="dealForm.card_number" type="text" placeholder="Card #" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <input id="df-exp" wire:model="dealForm.exp_date" type="text" placeholder="Exp" class="px-2 py-1 text-xs border border-crm-border rounded">
                            <input id="df-cv2" wire:model="dealForm.cv2" type="text" placeholder="CVV" class="px-2 py-1 text-xs border border-crm-border rounded">
                        </div>
                        <input id="df-billing" wire:model="dealForm.billing_address" type="text" placeholder="Billing Address" class="w-full px-2 py-1 text-xs border border-crm-border rounded">

                        <div class="text-[9px] text-crm-t3 uppercase font-semibold mt-2">Closing & Verification</div>
                        <div class="grid grid-cols-2 gap-1.5">
                            <div>
                                <label for="df-closing" class="text-[8px] text-crm-t3">Closing Date *</label>
                                <input id="df-closing" wire:model="dealForm.closing_date" type="date" class="w-full px-2 py-1 text-xs border border-crm-border rounded">
                            </div>
                            <div>
                                <label for="df-vernum" class="text-[8px] text-crm-t3">Verification #</label>
                                <input id="df-vernum" wire:model="dealForm.verification_num" type="text" placeholder="Verification #" class="w-full px-2 py-1 text-xs border border-crm-border rounded">
                            </div>
                        </div>
                        <textarea id="df-notes" wire:model="dealForm.notes" rows="2" placeholder="Notes" class="w-full px-2 py-1 text-xs border border-crm-border rounded"></textarea>

                        <div class="text-[9px] text-crm-t3 uppercase font-semibold mt-2">Transfer to Admin *</div>
                        <select id="df-admin" wire:model="dealFormAdmin" class="w-full px-2 py-1 text-xs border border-crm-border rounded">
                            <option value="">Select Admin...</option>
                            @foreach($adminUsers as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }} ({{ $admin->role }})</option>
                            @endforeach
                        </select>

                        <div class="flex gap-2 mt-3">
                            <button wire:click="closeDealForm" class="px-4 py-2 text-xs font-semibold bg-white border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                            <button wire:click="submitDeal" class="flex-1 px-4 py-2.5 text-sm font-bold bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md transition">Submit Deal</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex flex-shrink-0 gap-1.5 border-t border-crm-border bg-crm-surface px-3 py-2 relative items-center">
                @include('livewire.partials.gif-picker', [
                    'gifPickerSettings' => $gifPickerSettings,
                    'canUseGifPicker' => $canUseGifPicker,
                    'currentUserId' => $currentUserId,
                    'sendAction' => 'sendGif',
                ])
                {{-- Emoji Picker --}}
                <div x-data="{ emojiOpen: false }" class="relative">
                    <button type="button" @click="emojiOpen = !emojiOpen" class="flex h-10 w-10 items-center justify-center rounded-lg border border-crm-border bg-white text-base hover:bg-crm-hover transition" title="Emoji">😊</button>
                    <div x-show="emojiOpen" x-cloak @click.outside="emojiOpen = false"
                        class="fixed z-[99999] bg-white border border-gray-200 rounded-2xl shadow-2xl p-2"
                        :style="(() => { const r = $el.previousElementSibling.getBoundingClientRect(); return `left:${Math.max(8,r.left-120)}px;top:${Math.max(8,r.top-220)}px;width:260px;height:210px;`; })()">
                        <div class="text-[10px] text-crm-t3 font-semibold mb-1 px-1">Quick Emojis</div>
                        <div class="grid grid-cols-8 gap-0.5 overflow-y-auto" style="max-height:170px">
                            @foreach(['😀','😂','🤣','😍','😘','🥰','😎','🤩','😊','🙂','😉','😋','🤤','😜','🤪','😝','😏','😒','😞','😔','😟','😕','🙁','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','👍','👎','👌','✌️','🤞','🤟','🤘','🤙','👋','🖐️','✋','👏','🙌','🤝','🙏','💪','❤️','🔥','⭐','💯','🎉','🎊','💼','📋','📌','✅','❌','⚠️','🚀'] as $emoji)
                                <button type="button" @click="$wire.set('messageInput', $wire.messageInput + '{{ $emoji }}'); emojiOpen = false" class="text-lg hover:bg-gray-100 rounded p-0.5 cursor-pointer text-center">{{ $emoji }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <input id="wdg-msg-input" name="messageInput"
                    wire:model="messageInput"
                    wire:keydown.enter="sendMessage"
                    type="text"
                    placeholder="Type a message…"
                    class="flex-1 rounded-lg border border-crm-border bg-white px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                <button wire:click="sendMessage"
                    class="flex-shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    ↑
                </button>
            </div>
        @endif
    </div>
</div>
