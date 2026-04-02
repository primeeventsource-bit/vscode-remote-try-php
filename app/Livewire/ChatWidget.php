<?php

namespace App\Livewire;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Livewire\Component;

class ChatWidget extends Component
{
    public ?int $selectedChat = null;
    public string $messageInput = '';
    public bool $showNewChatForm = false;
    public string $newChatType = 'dm';
    public string $newChatName = '';
    public array $newChatMembers = [];
    public string $newChatError = '';

    private function getSetting(string $key, mixed $default): mixed
    {
        try {
            $raw = DB::table('crm_settings')->where('key', $key)->value('value');
            return $raw === null ? $default : json_decode($raw, true);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function selectChat($id): void
    {
        $this->selectedChat = (int) $id;
        $this->messageInput = '';
        $this->showNewChatForm = false;

        // isActiveChat = true → markAllAsSeen → unreadCount = 0
        $this->markChatAsSeen();
    }

    private function markChatAsSeen(): void
    {
        if (!$this->selectedChat) return;

        try {
            $now = now()->toDateTimeString();

            // Mark all incoming messages in this chat as seen
            Message::where('chat_id', $this->selectedChat)
                ->where('sender_id', '!=', auth()->id())
                ->whereNull('seen_at')
                ->update([
                    'seen_at' => $now,
                    'delivered_at' => DB::raw("COALESCE(delivered_at, '$now')"),
                ]);
        } catch (\Throwable $e) {
            // seen_at column may not exist yet
        }
    }

    public function clearChat(): void
    {
        $this->selectedChat = null;
        $this->messageInput = '';
    }

    public function toggleNewChatForm(): void
    {
        $this->showNewChatForm = !$this->showNewChatForm;
        if ($this->showNewChatForm) {
            $this->selectedChat = null;
            $this->newChatType = 'dm';
            $this->newChatName = '';
            $this->newChatMembers = [];
            $this->newChatError = '';
        }
    }

    public function createNewChat(): void
    {
        $user = auth()->user();
        if (!$user) return;

        $this->newChatError = '';
        $selectedMembers = array_values(array_unique(array_map('intval', $this->newChatMembers)));
        if (empty($selectedMembers)) {
            $this->newChatError = $this->newChatType === 'dm'
                ? 'Select one person to start a direct message.'
                : 'Select at least one member to create a group chat.';
            return;
        }

        if ($this->newChatType === 'dm') {
            $selectedMembers = [reset($selectedMembers)];
            $otherUser = User::find($selectedMembers[0]);
            if (!$otherUser) {
                $this->newChatError = 'The selected user could not be found.';
                return;
            }
            $name = $otherUser->name ?? 'Direct Message';

            // Prevent duplicate DMs
            $existing = Chat::where('type', 'dm')->get()->first(function ($c) use ($user, $selectedMembers) {
                $m = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                $ids = array_map('intval', array_values($m));
                sort($ids);
                $t = [$user->id, $selectedMembers[0]];
                sort($t);
                return $ids === $t;
            });
            if ($existing) {
                $this->selectedChat = $existing->id;
                $this->showNewChatForm = false;
                $this->newChatMembers = [];
                return;
            }
        } else {
            $name = trim($this->newChatName) ?: 'Group Chat';
        }

        try {
            $members = array_values(array_unique(array_merge($selectedMembers, [$user->id])));
            $chat = Chat::create([
                'name' => $name,
                'type' => $this->newChatType,
                'members' => $members,
                'created_by' => $user->id,
            ]);
            $this->selectedChat = $chat->id;
            $this->showNewChatForm = false;
            $this->newChatName = '';
            $this->newChatMembers = [];
            $this->messageInput = '';
            $this->newChatError = '';
        } catch (\Throwable $e) {
            Log::error('Chat creation failed (widget)', ['error' => $e->getMessage()]);
            $this->newChatError = 'Failed to create chat. Please try again.';
        }
    }

    public function sendMessage(): void
    {
        $text = trim($this->messageInput);
        if (!$text || !$this->selectedChat) return;

        try {
            $msgData = [
                'chat_id' => $this->selectedChat,
                'message_type' => 'text',
                'sender_id' => auth()->id(),
                'text' => $text,
            ];

            // Sender sees own message immediately
            try { $msgData['seen_at'] = now(); $msgData['delivered_at'] = now(); } catch (\Throwable $e) {}

            Message::create($msgData);
            Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
            $this->messageInput = '';
        } catch (\Throwable $e) {
            Log::error('Message send failed (widget)', ['error' => $e->getMessage()]);
        }
    }

    public function sendGif(array $gif): void
    {
        if (!$this->selectedChat || empty($gif['url'])) return;

        try {
            Message::create([
                'chat_id' => $this->selectedChat,
                'message_type' => 'gif',
                'sender_id' => auth()->id(),
                'gif_url' => $gif['url'],
                'gif_preview_url' => $gif['preview_url'] ?? $gif['url'],
                'gif_provider' => $gif['provider'] ?? 'giphy',
                'gif_external_id' => $gif['id'] ?? null,
                'gif_title' => $gif['title'] ?? 'GIF',
            ]);
            Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('GIF send failed (widget)', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user) {
            return view('livewire.chat-widget', [
                'chats' => collect(), 'messages' => collect(), 'activeChat' => null,
                'users' => collect(), 'gifPickerSettings' => [], 'canUseGifPicker' => false,
                'currentUserId' => 0, 'unreadCounts' => collect(),
            ]);
        }

        try {
            $chats = Chat::orderBy('updated_at', 'desc')->get()->filter(function ($c) use ($user) {
                $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                return in_array($user->id, $members) || in_array((string) $user->id, $members);
            });
        } catch (\Throwable $e) {
            $chats = collect();
        }

        // isActiveChat: if user is viewing a chat, mark as seen
        if ($this->selectedChat) {
            $this->markChatAsSeen();
        }

        try {
            $messages = $this->selectedChat
                ? Message::where('chat_id', $this->selectedChat)->orderBy('id')->limit(100)->get()
                : collect();
        } catch (\Throwable $e) {
            $messages = collect();
        }

        $activeChat = $this->selectedChat ? Chat::find($this->selectedChat) : null;
        $users = User::all()->keyBy('id');
        $currentUserId = (int) $user->id;

        // GIF settings with safe defaults
        $canUseGifPicker = true;
        $gifPickerSettings = [
            'module_enabled' => true, 'trending_enabled' => true, 'movies_enabled' => true,
            'search_enabled' => true, 'recent_enabled' => true, 'favorites_enabled' => true,
            'provider' => 'giphy', 'results_limit' => 24,
        ];

        // Unread counts
        $unreadCounts = collect();
        if ($chats->isNotEmpty()) {
            try {
                $unreadCounts = DB::table('messages')
                    ->whereIn('chat_id', $chats->pluck('id'))
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('seen_at')
                    ->selectRaw('chat_id, count(*) as cnt')
                    ->groupBy('chat_id')
                    ->pluck('cnt', 'chat_id');
            } catch (\Throwable $e) {
                // seen_at column may not exist — that's OK
            }
        }

        return view('livewire.chat-widget', compact(
            'chats', 'messages', 'activeChat', 'users',
            'gifPickerSettings', 'canUseGifPicker', 'currentUserId', 'unreadCounts'
        ));
    }
}
