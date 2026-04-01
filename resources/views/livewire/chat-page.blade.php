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
                        @if($chat->icon_path)
                            <img src="{{ asset('storage/' . $chat->icon_path) }}" class="w-6 h-6 rounded-lg object-cover flex-shrink-0">
                        @else
                            <span class="w-6 h-6 rounded-lg bg-crm-card border border-crm-border flex items-center justify-center text-[8px] font-bold text-crm-t3 flex-shrink-0">G</span>
                        @endif
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
            @php $memberIds = is_array($activeChat->members) ? $activeChat->members : json_decode($activeChat->members ?? '[]', true); @endphp
            {{-- Chat Header --}}
            <div class="px-4 py-3 border-b border-crm-border bg-crm-surface flex items-center gap-3">
                @if($activeChat->type === 'channel')
                    <span class="text-crm-t3">#</span>
                @endif
                <h4 class="text-sm font-bold">{{ $activeChat->name ?? 'Chat' }}</h4>
                <span class="text-[10px] text-crm-t3">
                    {{ count($memberIds) }} member{{ count($memberIds) !== 1 ? 's' : '' }}
                </span>
                <button wire:click="toggleInfoPanel" class="ml-auto flex h-7 w-7 items-center justify-center rounded-lg text-crm-t3 hover:bg-crm-hover hover:text-crm-t1 transition" title="Conversation info">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
            </div>

            <div class="flex flex-1 min-h-0">
            {{-- Messages + Input Column --}}
            <div class="flex-1 flex flex-col min-h-0">
            {{-- Messages Thread --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="message-thread">
                @if(isset($messages))
                    @foreach($messages as $msg)
                        @php
                            $msgUser = isset($users) ? $users->firstWhere('id', $msg->sender_id) : null;
                            $isMine = $msg->sender_id === auth()->id();
                        @endphp
                        <div class="flex items-start gap-3 {{ $isMine ? 'flex-row-reverse' : '' }}">
                            @if($msgUser?->avatar_path)
                                <img src="{{ asset('storage/' . $msgUser->avatar_path) }}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[9px] font-bold text-white flex-shrink-0" style="background: {{ $msgUser->color ?? '#6b7280' }}">{{ $msgUser->avatar ?? substr($msgUser->name ?? '?', 0, 2) }}</div>
                            @endif
                            <div class="max-w-[60%]">
                                <div class="flex items-center gap-2 mb-0.5 {{ $isMine ? 'flex-row-reverse' : '' }}">
                                    <span class="text-xs font-semibold">{{ $msgUser->name ?? 'Unknown' }}</span>
                                    <span class="text-[10px] text-crm-t3">{{ $msg->created_at?->format('g:i A') ?? '' }}</span>
                                </div>
                                @if(($msg->message_type ?? 'text') === 'gif' && $msg->gif_url)
                                    <div class="overflow-hidden rounded-2xl border {{ $isMine ? 'border-blue-500 bg-blue-600/10' : 'border-crm-border bg-white' }}">
                                        <a href="{{ $msg->gif_url }}" target="_blank" rel="noreferrer" class="block">
                                            <img src="{{ $msg->gif_preview_url ?: $msg->gif_url }}" alt="{{ $msg->gif_title ?? 'GIF' }}" class="max-h-72 w-full object-cover" loading="lazy">
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
            <div class="px-4 py-3 border-t border-crm-border bg-crm-surface relative">
                <div class="flex items-center gap-2">
                    @include('livewire.partials.gif-picker', [
                        'gifPickerId' => 'chat-page-gif-picker',
                        'gifPickerPanelClass' => 'left-0 bottom-full mb-3 w-[380px] max-h-[420px] z-[9999] shadow-2xl',
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
            </div>{{-- /messages+input column --}}

            {{-- Conversation Info Panel --}}
            @if($showInfoPanel)
            <div class="w-64 border-l border-crm-border bg-crm-surface flex flex-col flex-shrink-0 overflow-y-auto">
                <div class="px-4 py-3 border-b border-crm-border">
                    <h4 class="text-sm font-bold">Conversation Info</h4>
                </div>

                {{-- Chat Details + Group Icon --}}
                <div class="px-4 py-3 border-b border-crm-border">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Details</div>
                    @if($activeChat->type !== 'dm')
                        <div class="flex items-center gap-3 mb-3">
                            @if($activeChat->icon_path)
                                <img src="{{ asset('storage/' . $activeChat->icon_path) }}" class="w-12 h-12 rounded-xl object-cover">
                            @else
                                <div class="w-12 h-12 rounded-xl bg-crm-card border border-crm-border flex items-center justify-center text-lg font-bold text-crm-t3">G</div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ $activeChat->name ?? 'Chat' }}</div>
                                <div class="text-[10px] text-crm-t3 capitalize">{{ $activeChat->type }} chat</div>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="flex items-center gap-2 cursor-pointer text-xs text-blue-600 hover:text-blue-700 font-medium">
                                <input type="file" wire:model="groupIconUpload" accept="image/jpeg,image/png,image/webp" class="hidden">
                                {{ $activeChat->icon_path ? 'Change icon' : 'Upload icon' }}
                            </label>
                            @if($groupIconUpload)
                                <button wire:click="uploadGroupIcon" class="text-xs font-semibold text-white bg-blue-600 rounded px-2 py-1 hover:bg-blue-700">Save Icon</button>
                            @endif
                            @if($activeChat->icon_path)
                                <button wire:click="removeGroupIcon" class="text-xs text-red-500 hover:text-red-600 font-medium">Remove icon</button>
                            @endif
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <div class="text-sm font-medium">{{ $activeChat->name ?? 'Chat' }}</div>
                            <div class="text-xs text-crm-t3 capitalize">{{ $activeChat->type }} chat</div>
                        </div>
                    @endif
                    <div class="text-xs text-crm-t3 mt-2">Created {{ $activeChat->created_at?->format('M j, Y') ?? '' }}</div>
                </div>

                {{-- Members --}}
                <div class="px-4 py-3 border-b border-crm-border">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Members ({{ count($memberIds) }})</div>
                    <div class="space-y-2">
                        @foreach($memberIds as $memberId)
                            @php $member = $users->get((int) $memberId); @endphp
                            @if($member)
                            <div class="flex items-center gap-2">
                                @if($member->avatar_path)
                                    <img src="{{ asset('storage/' . $member->avatar_path) }}" class="w-7 h-7 rounded-full object-cover flex-shrink-0">
                                @else
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[8px] font-bold text-white flex-shrink-0" style="background: {{ $member->color ?? '#6b7280' }}">{{ $member->avatar ?? substr($member->name, 0, 2) }}</div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium truncate">{{ $member->name }}</div>
                                    <div class="text-[10px] text-crm-t3 capitalize">{{ str_replace('_', ' ', $member->role ?? '') }}</div>
                                </div>
                                @if($memberId == auth()->id())
                                    <span class="text-[9px] text-crm-t3 font-semibold">you</span>
                                @endif
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Your Avatar --}}
                <div class="px-4 py-3 border-b border-crm-border">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Your Avatar</div>
                    <div class="flex items-center gap-3">
                        @if(auth()->user()->avatar_path)
                            <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" class="w-10 h-10 rounded-full object-cover">
                        @else
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background: {{ auth()->user()->color ?? '#3b82f6' }}">{{ auth()->user()->avatar ?? substr(auth()->user()->name, 0, 2) }}</div>
                        @endif
                        <div class="flex-1 space-y-1">
                            <label class="flex items-center gap-1 cursor-pointer text-xs text-blue-600 hover:text-blue-700 font-medium">
                                <input type="file" wire:model="avatarUpload" accept="image/jpeg,image/png,image/webp" class="hidden">
                                {{ auth()->user()->avatar_path ? 'Change photo' : 'Upload photo' }}
                            </label>
                            @if($avatarUpload)
                                <button wire:click="uploadAvatar" class="text-xs font-semibold text-white bg-blue-600 rounded px-2 py-1 hover:bg-blue-700">Save</button>
                            @endif
                            @if(auth()->user()->avatar_path)
                                <button wire:click="removeAvatar" class="text-xs text-red-500 hover:text-red-600 font-medium">Remove</button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Shared Media --}}
                <div class="px-4 py-3">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Shared Media</div>
                    @if($sharedMedia->count() > 0)
                        <div class="grid grid-cols-3 gap-1">
                            @foreach($sharedMedia as $media)
                                <a href="{{ $media->gif_url }}" target="_blank" rel="noreferrer" class="block overflow-hidden rounded-lg border border-crm-border">
                                    <img src="{{ $media->gif_preview_url ?: $media->gif_url }}" alt="{{ $media->gif_title ?? 'GIF' }}" class="h-16 w-full object-cover" loading="lazy">
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-crm-t3">No shared media yet.</p>
                    @endif
                </div>
            </div>
            @endif
            </div>{{-- /flex messages+info wrapper --}}

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
