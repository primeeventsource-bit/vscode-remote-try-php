<div class="flex h-[calc(100vh-3rem)]" wire:poll.5s>
    {{-- Left Panel: Chat List --}}
    <div class="w-72 border-r border-crm-border bg-crm-surface flex flex-col flex-shrink-0">
        {{-- Header --}}
        <div class="px-4 py-3 border-b border-crm-border">
            <h3 class="text-sm font-bold">Chat</h3>
        </div>

        {{-- Chat Lists --}}
        <div class="flex-1 overflow-y-auto">
            {{-- Channels --}}
            <div class="px-3 pt-3 pb-1">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Channels</div>
            </div>
            @if(isset($chats))
                @foreach($chats->where('type', 'channel') as $chat)
                    <button wire:click="selectChat({{ $chat->id }})" class="w-full text-left px-3 py-2 flex items-center gap-2 transition {{ (isset($activeChat) && $activeChat === $chat->id) ? 'bg-blue-50 text-blue-600' : 'hover:bg-crm-hover text-crm-t2' }}">
                        <span class="text-xs">#</span>
                        <span class="text-sm font-medium truncate">{{ $chat->name }}</span>
                        @if(isset($chat->unread) && $chat->unread > 0)
                            <span class="ml-auto w-4 h-4 flex items-center justify-center text-[8px] font-bold text-white bg-blue-600 rounded-full">{{ $chat->unread }}</span>
                        @endif
                    </button>
                @endforeach
            @endif

            {{-- DMs --}}
            <div class="px-3 pt-4 pb-1">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Direct Messages</div>
            </div>
            @if(isset($chats))
                @foreach($chats->where('type', 'dm') as $chat)
                    @php
                        $members = is_array($chat->members) ? $chat->members : json_decode($chat->members ?? '[]', true);
                        $otherId = collect($members)->first(fn($m) => $m != auth()->id());
                        $other = isset($users) ? $users->firstWhere('id', $otherId) : null;
                    @endphp
                    <button wire:click="selectChat({{ $chat->id }})" class="w-full text-left px-3 py-2 flex items-center gap-2 transition {{ (isset($activeChat) && $activeChat === $chat->id) ? 'bg-blue-50 text-blue-600' : 'hover:bg-crm-hover text-crm-t2' }}">
                        @if($other)
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold text-white flex-shrink-0" style="background: {{ $other->color ?? '#6b7280' }}">{{ $other->avatar ?? substr($other->name, 0, 2) }}</div>
                            <span class="text-sm font-medium truncate">{{ $other->name }}</span>
                        @else
                            <span class="text-sm font-medium truncate">{{ $chat->name ?? 'DM' }}</span>
                        @endif
                        @if(isset($chat->unread) && $chat->unread > 0)
                            <span class="ml-auto w-4 h-4 flex items-center justify-center text-[8px] font-bold text-white bg-blue-600 rounded-full">{{ $chat->unread }}</span>
                        @endif
                    </button>
                @endforeach
            @endif

            {{-- Groups --}}
            <div class="px-3 pt-4 pb-1">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Groups</div>
            </div>
            @if(isset($chats))
                @foreach($chats->where('type', 'group') as $chat)
                    <button wire:click="selectChat({{ $chat->id }})" class="w-full text-left px-3 py-2 flex items-center gap-2 transition {{ (isset($activeChat) && $activeChat === $chat->id) ? 'bg-blue-50 text-blue-600' : 'hover:bg-crm-hover text-crm-t2' }}">
                        <span class="w-6 h-6 rounded-lg bg-crm-card border border-crm-border flex items-center justify-center text-[8px] font-bold text-crm-t3 flex-shrink-0">G</span>
                        <span class="text-sm font-medium truncate">{{ $chat->name }}</span>
                        @if(isset($chat->unread) && $chat->unread > 0)
                            <span class="ml-auto w-4 h-4 flex items-center justify-center text-[8px] font-bold text-white bg-blue-600 rounded-full">{{ $chat->unread }}</span>
                        @endif
                    </button>
                @endforeach
            @endif
        </div>

        {{-- New Chat Button --}}
        <div class="p-3 border-t border-crm-border">
            <button wire:click="toggleNewChatForm" class="w-full px-3 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">+ New Chat</button>
        </div>
    </div>

    {{-- Right Panel: Messages / New Chat Form --}}
    <div class="flex-1 flex flex-col bg-crm-bg">
        @if($showNewChatForm)
            {{-- New Chat Form Header --}}
            <div class="px-4 py-3 border-b border-crm-border bg-crm-surface flex items-center gap-3">
                <button wire:click="toggleNewChatForm" class="flex h-7 w-7 items-center justify-center rounded text-crm-t3 hover:bg-crm-hover hover:text-crm-t1 transition text-sm">←</button>
                <h4 class="text-sm font-bold">New Conversation</h4>
            </div>

            {{-- New Chat Form --}}
            <div class="flex-1 overflow-y-auto p-6">
                <div class="max-w-md mx-auto space-y-4">
                    @if($newChatError)
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                            {{ $newChatError }}
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs text-crm-t3 uppercase font-semibold mb-1">Chat Type</label>
                        <select wire:model="newChatType" class="w-full rounded-lg border border-crm-border bg-white px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                            <option value="dm">Direct Message</option>
                            <option value="group">Group Chat</option>
                        </select>
                    </div>

                    @if($newChatType === 'group')
                    <div>
                        <label class="block text-xs text-crm-t3 uppercase font-semibold mb-1">Group Name</label>
                        <input wire:model="newChatName" type="text" placeholder="E.g., Sales Team"
                               class="w-full rounded-lg border border-crm-border bg-white px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                    </div>
                    @endif

                    <div>
                        <label class="block text-xs text-crm-t3 uppercase font-semibold mb-1">Select {{ $newChatType === 'dm' ? 'Person' : 'Members' }}</label>
                        <div class="max-h-64 overflow-y-auto border border-crm-border rounded-lg bg-white">
                            @foreach($users as $u)
                                @if($u->id !== auth()->id())
                                    <label class="flex items-center gap-3 border-b border-crm-border px-4 py-3 last:border-0 cursor-pointer hover:bg-crm-hover text-sm">
                                        <input type="checkbox" wire:model="newChatMembers" value="{{ $u->id }}"
                                               @if($newChatType === 'dm' && count($newChatMembers) > 0 && !in_array($u->id, $newChatMembers)) disabled @endif
                                               class="h-4 w-4 rounded border-crm-border">
                                        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-[9px] font-bold text-white"
                                             style="background:{{ $u->color ?? '#6b7280' }}">{{ $u->avatar ?? substr($u->name, 0, 2) }}</div>
                                        <span>{{ $u->name }}</span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button wire:click="toggleNewChatForm" class="flex-1 rounded-lg bg-crm-card border border-crm-border px-4 py-2.5 text-sm font-semibold text-crm-t2 transition hover:bg-crm-hover">
                            Cancel
                        </button>
                        <button wire:click="createNewChat" class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                            Create Chat
                        </button>
                    </div>
                </div>
            </div>
        @elseif(isset($activeChat) && $activeChat)
            {{-- Chat Header --}}
            <div class="px-4 py-3 border-b border-crm-border bg-crm-surface flex items-center gap-3">
                @if($activeChat->type === 'channel')
                    <span class="text-crm-t3">#</span>
                @endif
                <h4 class="text-sm font-bold">{{ $activeChat->name ?? 'Chat' }}</h4>
                <span class="text-[10px] text-crm-t3">
                    @php $memberIds = is_array($activeChat->members) ? $activeChat->members : json_decode($activeChat->members ?? '[]', true); @endphp
                    {{ count($memberIds) }} member{{ count($memberIds) !== 1 ? 's' : '' }}
                </span>
            </div>

            {{-- Messages Thread --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="message-thread">
                @if(isset($messages))
                    @foreach($messages as $msg)
                        @php
                            $msgUser = isset($users) ? $users->firstWhere('id', $msg->sender_id) : null;
                            $isMine = $msg->sender_id === auth()->id();
                        @endphp
                        <div class="flex items-start gap-3 {{ $isMine ? 'flex-row-reverse' : '' }}">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-[9px] font-bold text-white flex-shrink-0" style="background: {{ $msgUser->color ?? '#6b7280' }}">{{ $msgUser->avatar ?? substr($msgUser->name ?? '?', 0, 2) }}</div>
                            <div class="max-w-[60%]">
                                <div class="flex items-center gap-2 mb-0.5 {{ $isMine ? 'flex-row-reverse' : '' }}">
                                    <span class="text-xs font-semibold">{{ $msgUser->name ?? 'Unknown' }}</span>
                                    <span class="text-[10px] text-crm-t3">{{ $msg->created_at?->format('g:i A') ?? '' }}</span>
                                </div>
                                @if(($msg->message_type ?? 'text') === 'gif' && $msg->gif_url)
                                    <div class="overflow-hidden rounded-2xl border {{ $isMine ? 'border-blue-500 bg-blue-600/10' : 'border-crm-border bg-white' }}">
                                        <a href="{{ $msg->gif_url }}" target="_blank" rel="noreferrer" class="block">
                                            <img src="{{ $msg->gif_preview_url ?: $msg->gif_url }}" alt="{{ $msg->gif_title ?? 'GIF' }}" class="max-h-72 w-full object-cover">
                                        </a>
                                        <div class="px-3 py-2 text-xs {{ $isMine ? 'text-blue-50 bg-blue-600' : 'text-crm-t2 bg-crm-card' }}">
                                            {{ $msg->gif_title ?: 'GIF' }}
                                        </div>
                                    </div>
                                @else
                                    <div class="px-3 py-2 rounded-lg text-sm {{ $isMine ? 'bg-blue-600 text-white' : 'bg-crm-card border border-crm-border text-crm-t1' }}">
                                        {{ $msg->body ?? $msg->content ?? $msg->text ?? '' }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif

                @if(!isset($messages) || count($messages) === 0)
                    <div class="flex-1 flex items-center justify-center">
                        <p class="text-sm text-crm-t3">No messages yet. Start the conversation!</p>
                    </div>
                @endif
            </div>

            {{-- Message Input --}}
            <div class="px-4 py-3 border-t border-crm-border bg-crm-surface">
                <div class="flex items-center gap-2 relative">
                    @include('livewire.partials.gif-picker', [
                        'gifPickerId' => 'chat-page-gif-picker',
                        'gifPickerPanelClass' => 'left-0 bottom-full mb-2 w-[22rem]',
                        'gifPickerSettings' => $gifPickerSettings,
                        'canUseGifPicker' => $canUseGifPicker,
                        'currentUserId' => $currentUserId,
                        'sendAction' => 'sendGif',
                    ])
                    <input wire:model="messageInput" wire:keydown.enter="sendMessage" type="text" placeholder="Type a message..."
                        class="flex-1 px-4 py-2.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <button wire:click="sendMessage" class="px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition flex-shrink-0">
                        Send
                    </button>
                </div>
            </div>
        @else
            {{-- No Chat Selected --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="text-4xl opacity-30 mb-3">💬</div>
                    <p class="text-sm text-crm-t3">Select a conversation to start chatting</p>
                </div>
            </div>
        @endif
    </div>
</div>
