<div
    x-data="{
        open: false,
        px: Math.max(20, window.innerWidth - 400),
        py: Math.max(60, window.innerHeight - 540),
        dragging: false,
        ox: 0,
        oy: 0,
        startDrag(e) {
            if (e.button !== undefined && e.button !== 0) return;
            this.dragging = true;
            this.ox = e.clientX - this.px;
            this.oy = e.clientY - this.py;
        },
        doDrag(e) {
            if (!this.dragging) return;
            this.px = Math.max(0, Math.min(e.clientX - this.ox, window.innerWidth - 385));
            this.py = Math.max(44, Math.min(e.clientY - this.oy, window.innerHeight - 510));
        },
        stopDrag() { this.dragging = false; }
    }"
    @mousemove.window="doDrag($event)"
    @mouseup.window="stopDrag()"
    wire:poll.10s
>
    {{-- ─── Floating Bubble ─── --}}
    <button
        @click="open = !open"
        class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg shadow-blue-600/30 transition hover:scale-105 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200"
        title="Toggle Chat"
    >
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-20"></span>
        <span class="relative text-2xl leading-none" x-text="open ? '✕' : '💬'"></span>
    </button>

    {{-- ─── Draggable Chat Popup ─── --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        :style="`position:fixed;left:${px}px;top:${py}px;width:380px;height:500px;z-index:9999;`"
        class="flex flex-col overflow-hidden rounded-xl border border-crm-border bg-white shadow-2xl select-none"
        style="display:none;"
    >
        {{-- Drag Handle / Header --}}
        <div
            class="flex flex-shrink-0 cursor-grab items-center gap-2 border-b border-crm-border bg-crm-surface px-4 py-3 active:cursor-grabbing"
            @mousedown="startDrag($event)"
        >
            @if($selectedChat && $activeChat)
                <button wire:click="clearChat" class="mr-1 flex h-6 w-6 items-center justify-center rounded text-crm-t3 hover:bg-crm-hover hover:text-crm-t1 transition text-sm">←</button>
                <span class="flex-1 text-sm font-bold truncate">{{ $activeChat->name ?? 'Chat' }}</span>
            @else
                <span class="text-base">💬</span>
                <h4 class="flex-1 text-sm font-bold">Chat</h4>
            @endif
            <button @click="open = false" class="flex h-6 w-6 items-center justify-center rounded text-lg text-crm-t3 hover:bg-crm-hover hover:text-crm-t1 transition leading-none">&times;</button>
        </div>

        {{-- ─── Chat List (no chat selected) ─── --}}
        @if(!$selectedChat && !$showNewChatForm)
            <div class="flex-1 overflow-y-auto">
                @forelse($chats as $chat)
                    @php
                        $members = is_array($chat->members) ? $chat->members : json_decode($chat->members ?? '[]', true);
                        $otherId = collect($members)->first(fn($m) => (int)$m !== auth()->id());
                        $other   = $users->get($otherId);
                        $bg      = $other->color ?? '#3b82f6';
                        $initials = $chat->type === 'channel' ? '#' : ($other->avatar ?? substr($other->name ?? 'G', 0, 2));
                        $displayName = $chat->type === 'dm' ? ($other->name ?? $chat->name ?? 'DM') : $chat->name;
                    @endphp
                    <button wire:click="selectChat({{ $chat->id }})"
                        class="flex w-full items-center gap-3 border-b border-crm-border px-4 py-3 text-left transition hover:bg-crm-hover">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                             style="background:{{ $bg }}">{{ $initials }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ $displayName }}</div>
                            <div class="text-[10px] text-crm-t3">{{ $chat->updated_at?->diffForHumans() ?? '' }}</div>
                        </div>
                        @if($chat->type === 'channel')
                            <span class="text-[10px] font-mono text-crm-t3">#</span>
                        @elseif($chat->type === 'group')
                            <span class="text-[10px] text-crm-t3">G</span>
                        @endif
                    </button>
                @empty
                    <div class="flex flex-1 items-center justify-center p-8 text-center">
                        <p class="text-sm text-crm-t3">No conversations yet.</p>
                    </div>
                @endforelse
            </div>

            {{-- New Chat Button --}}
            <div class="flex-shrink-0 border-t border-crm-border px-3 py-2">
                <button wire:click="toggleNewChatForm" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">+ New Chat</button>
            </div>

        {{-- ─── Create New Chat Form ─── --}}
        @elseif($showNewChatForm)
            <div class="flex-1 overflow-y-auto p-4">
                <div class="space-y-3">
                    @if($newChatError)
                        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                            {{ $newChatError }}
                        </div>
                    @endif

                    <div>
                        <label class="text-xs text-crm-t3 uppercase font-semibold">Chat Type</label>
                        <select wire:model="newChatType" class="w-full mt-1 rounded border border-crm-border bg-white px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                            <option value="dm">Direct Message</option>
                            <option value="group">Group</option>
                        </select>
                    </div>

                    @if($newChatType === 'group')
                    <div>
                        <label class="text-xs text-crm-t3 uppercase font-semibold">Group Name</label>
                        <input wire:model="newChatName" type="text" placeholder="E.g., Sales Team" 
                               class="w-full mt-1 rounded border border-crm-border bg-white px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                    </div>
                    @endif

                    <div>
                        <label class="text-xs text-crm-t3 uppercase font-semibold">Select {{ $newChatType === 'dm' ? 'Person' : 'Members' }}</label>
                        <div class="mt-1 max-h-36 overflow-y-auto border border-crm-border rounded bg-white">
                            @foreach($users as $u)
                                @if($u->id !== auth()->id())
                                    <label class="flex items-center gap-2 border-b border-crm-border px-3 py-2 last:border-0 cursor-pointer hover:bg-crm-hover text-sm">
                                        <input type="checkbox" wire:model="newChatMembers" value="{{ $u->id }}" 
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
                        <button wire:click="toggleNewChatForm" class="flex-1 rounded bg-crm-card border border-crm-border px-2 py-1.5 text-xs font-semibold text-crm-t2 transition hover:bg-crm-hover">
                            Cancel
                        </button>
                        <button wire:click="createNewChat" class="flex-1 rounded bg-blue-600 px-2 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700">
                            Create
                        </button>
                    </div>
                </div>
            </div>

        {{-- ─── Chat List (no chat selected, no form) ─── --}}
        @else
            {{-- ─── Message Thread (chat selected) ─── --}}
            <div class="flex-1 overflow-y-auto space-y-2 p-3" id="cwt-messages">
                @forelse($messages as $msg)
                    @php
                        $msgUser = $users->get($msg->sender_id);
                        $isMine  = $msg->sender_id === auth()->id();
                        $bubble  = $isMine ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-crm-card border border-crm-border text-crm-t1 rounded-bl-sm';
                    @endphp
                    <div class="flex items-end gap-2 {{ $isMine ? 'flex-row-reverse' : '' }}">
                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-[8px] font-bold text-white"
                             style="background:{{ $msgUser->color ?? '#6b7280' }}">
                            {{ $msgUser->avatar ?? substr($msgUser->name ?? '?', 0, 2) }}
                        </div>
                        @if(($msg->message_type ?? 'text') === 'gif' && $msg->gif_url)
                            <div class="max-w-[72%] overflow-hidden rounded-xl border {{ $isMine ? 'border-blue-500 bg-blue-600/10' : 'border-crm-border bg-white' }}">
                                <a href="{{ $msg->gif_url }}" target="_blank" rel="noreferrer" class="block">
                                    <img src="{{ $msg->gif_preview_url ?: $msg->gif_url }}" alt="{{ $msg->gif_title ?? 'GIF' }}" class="max-h-52 w-full object-cover" loading="lazy">
                                </a>
                                <div class="px-2 py-1.5 text-[11px] {{ $isMine ? 'bg-blue-600 text-blue-50' : 'bg-crm-card text-crm-t2' }}">
                                    {{ $msg->gif_title ?: 'GIF' }}
                                </div>
                            </div>
                        @else
                            <div class="max-w-[72%] rounded-lg px-3 py-2 text-sm {{ $bubble }}">
                                {{ $msg->text ?? '' }}
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-crm-t3">No messages yet. Say hello! 👋</p>
                @endforelse
            </div>

            <div class="flex flex-shrink-0 gap-2 border-t border-crm-border bg-crm-surface px-3 py-2 relative">
                @include('livewire.partials.gif-picker', [
                    'gifPickerSettings' => $gifPickerSettings,
                    'canUseGifPicker' => $canUseGifPicker,
                    'currentUserId' => $currentUserId,
                    'sendAction' => 'sendGif',
                ])
                <input
                    wire:model="messageInput"
                    wire:keydown.enter="sendMessage"
                    type="text"
                    placeholder="Type a message…"
                    class="flex-1 rounded-lg border border-crm-border bg-white px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                >
                <button wire:click="sendMessage"
                    class="flex-shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    ↑
                </button>
            </div>
        @endif
    </div>

    {{-- Auto-scroll messages on update --}}
    @if($selectedChat)
    <script>
        (function() {
            var el = document.getElementById('cwt-messages');
            if (el) el.scrollTop = el.scrollHeight;
        })();
    </script>
    @endif
</div>
