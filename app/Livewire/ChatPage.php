<?php
namespace App\Livewire;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Chat')]
class ChatPage extends Component
{
    use WithFileUploads;

    public ?int $selectedChat = null;
    public string $messageInput = '';
    public bool $showNewChatForm = false;
    public bool $showInfoPanel = false;
    public string $newChatType = 'dm';
    public string $newChatName = '';
    public array $newChatMembers = [];
    public string $newChatError = '';
    public $avatarUpload = null;
    public $groupIconUpload = null;
    public string $emojiAvatarPick = '';
    public bool $showManageMembers = false;
    public array $addMemberIds = [];

    public function mount(): void
    {
        try {
            $raw = DB::table('crm_settings')->where('key', 'chat.module_enabled')->value('value');
            $enabled = $raw === null ? true : (bool) json_decode($raw, true);
            if (!$enabled || !auth()->user()?->hasPerm('view_chat')) {
                $this->redirectRoute('dashboard');
                session()->flash('error', 'Chat module is disabled or you do not have access.');
            }
        } catch (\Throwable $e) {
            // crm_settings table may not exist yet — allow access by default
        }
    }

    public function selectChat($id)
    {
        $this->selectedChat = (int) $id;
        $this->showNewChatForm = false;
        $this->showInfoPanel = false;
        $this->markChatAsSeen();
    }

    /**
     * Mark all incoming messages in the active chat as seen.
     * Identical to ChatWidget logic — single source of truth for read status.
     */
    private function markChatAsSeen(): void
    {
        if (! $this->selectedChat) return;

        try {
            $now = now()->toDateTimeString();
            Message::where('chat_id', $this->selectedChat)
                ->where('sender_id', '!=', auth()->id())
                ->whereNull('seen_at')
                ->update([
                    'seen_at'      => $now,
                    'delivered_at' => $now,
                ]);
        } catch (\Throwable $e) {
            // seen_at column may not exist yet
        }
    }

    /**
     * Called by wire:poll to refresh without full re-render.
     * Also re-marks active chat as seen on each poll cycle.
     */
    public function refreshUnreadCounts(): void
    {
        $this->markChatAsSeen();
    }

    public function toggleInfoPanel(): void
    {
        $this->showInfoPanel = !$this->showInfoPanel;
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
            Log::error('Chat creation failed', ['error' => $e->getMessage()]);
            $this->newChatError = 'Failed to create chat. Please try again.';
        }
    }

    public function sendMessage()
    {
        $text = trim($this->messageInput);
        if ($text === '' || !$this->selectedChat) return;

        try {
            $msgData = [
                'chat_id'      => $this->selectedChat,
                'message_type' => 'text',
                'sender_id'    => auth()->id(),
                'text'         => $text,
            ];

            // Sender sees own message as delivered immediately (matches bubble chat)
            try { $msgData['seen_at'] = now(); $msgData['delivered_at'] = now(); } catch (\Throwable $e) {}

            Message::create($msgData);
            Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
            $this->messageInput = '';
        } catch (\Throwable $e) {
            Log::error('Message send failed', ['error' => $e->getMessage()]);
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
            Log::error('GIF send failed', ['error' => $e->getMessage()]);
        }
    }

    public function uploadAvatar(): void
    {
        $this->validate(['avatarUpload' => 'image|max:2048|mimes:jpg,jpeg,png,webp']);
        try {
            $user = auth()->user();
            if ($user->avatar_path) Storage::disk('public')->delete($user->avatar_path);
            $path = $this->avatarUpload->store('avatars', 'public');
            $user->update(['avatar_path' => $path]);
            $this->avatarUpload = null;
        } catch (\Throwable $e) {
            Log::error('Avatar upload failed', ['error' => $e->getMessage()]);
        }
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
    }

    public function uploadGroupIcon(): void
    {
        if (!$this->selectedChat) return;
        // HARD RULE: Master Admin only can change group chat avatar
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->validate(['groupIconUpload' => 'image|max:2048|mimes:jpg,jpeg,png,webp']);
        try {
            $chat = Chat::find($this->selectedChat);
            if (!$chat || $chat->type === 'dm') return;
            if ($chat->icon_path) Storage::disk('public')->delete($chat->icon_path);
            $path = $this->groupIconUpload->store('chat-icons', 'public');
            $chat->update(['icon_path' => $path]);
            $this->groupIconUpload = null;
        } catch (\Throwable $e) {
            Log::error('Group icon upload failed', ['error' => $e->getMessage()]);
        }
    }

    public function removeGroupIcon(): void
    {
        if (!$this->selectedChat) return;
        if (!auth()->user()?->hasRole('master_admin')) return;
        $chat = Chat::find($this->selectedChat);
        if ($chat?->icon_path) {
            Storage::disk('public')->delete($chat->icon_path);
            $chat->update(['icon_path' => null]);
        }
    }

    public function setEmojiAvatar(string $emoji): void
    {
        $user = auth()->user();
        if (!$user) return;
        // Remove uploaded avatar if switching to emoji
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->update(['avatar_emoji' => $emoji, 'avatar_path' => null]);
    }

    public function setGroupEmojiIcon(string $emoji): void
    {
        if (!$this->selectedChat) return;
        if (!auth()->user()?->hasRole('master_admin')) return;
        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type === 'dm') return;
        if ($chat->icon_path) Storage::disk('public')->delete($chat->icon_path);
        $chat->update(['icon_emoji' => $emoji, 'icon_path' => null]);
    }

    public function startDirectCall(): void
    {
        if (!$this->selectedChat) return;
        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type !== 'dm') return;

        try {
            $room = \App\Services\VideoCall\VideoRoomService::createOrReuseDirectRoom($chat, auth()->user());
            if ($room) {
                \App\Services\VideoCall\VideoRoomService::joinRoom($room, auth()->user());
                $this->redirect(route('video-call', ['room' => $room->uuid]));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function startDirectAudioCall(): void
    {
        if (!$this->selectedChat) return;
        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type !== 'dm') return;

        try {
            $room = \App\Services\VideoCall\VideoRoomService::createOrReuseDirectRoom($chat, auth()->user(), 'audio');
            if ($room) {
                \App\Services\VideoCall\VideoRoomService::joinRoom($room, auth()->user());
                $this->redirect(route('video-call', ['room' => $room->uuid]));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function canManageGroup(): bool
    {
        $user = auth()->user();
        return $user && $user->hasRole('master_admin', 'admin');
    }

    public function toggleManageMembers(): void
    {
        if (!$this->canManageGroup()) return;
        $this->showManageMembers = !$this->showManageMembers;
        $this->addMemberIds = [];
    }

    public function addGroupMembers(): void
    {
        if (!$this->canManageGroup() || !$this->selectedChat) return;

        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type === 'dm') return;

        $members = is_array($chat->members) ? $chat->members : json_decode($chat->members ?? '[]', true);
        $members = array_map('intval', $members);
        $added = [];

        foreach (array_map('intval', $this->addMemberIds) as $uid) {
            if ($uid && !in_array($uid, $members)) {
                $members[] = $uid;
                $addedUser = User::find($uid);
                if ($addedUser) $added[] = $addedUser->name;
            }
        }

        if (empty($added)) {
            $this->addMemberIds = [];
            return;
        }

        $chat->update(['members' => array_values(array_unique($members))]);

        // System message
        $actorName = auth()->user()->name ?? 'Admin';
        foreach ($added as $name) {
            try {
                Message::create([
                    'chat_id' => $chat->id,
                    'message_type' => 'text',
                    'sender_id' => auth()->id(),
                    'text' => "📌 {$name} was added to the group by {$actorName}",
                ]);
            } catch (\Throwable $e) {}
        }
        $chat->update(['updated_at' => now()]);

        $this->addMemberIds = [];
        $this->showManageMembers = false;
    }

    public function removeGroupMember(int $userId): void
    {
        if (!$this->canManageGroup() || !$this->selectedChat) return;

        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type === 'dm') return;

        $members = is_array($chat->members) ? $chat->members : json_decode($chat->members ?? '[]', true);
        $members = array_map('intval', $members);

        if (!in_array($userId, $members)) return;

        // Don't allow removing chat creator
        if ($chat->created_by === $userId) return;

        $members = array_values(array_filter($members, fn($m) => $m !== $userId));
        $chat->update(['members' => $members]);

        $removedUser = User::find($userId);
        $actorName = auth()->user()->name ?? 'Admin';
        $removedName = $removedUser->name ?? 'User';

        try {
            Message::create([
                'chat_id' => $chat->id,
                'message_type' => 'text',
                'sender_id' => auth()->id(),
                'text' => "📌 {$removedName} was removed from the group by {$actorName}",
            ]);
        } catch (\Throwable $e) {}

        $chat->update(['updated_at' => now()]);

        // If removed user was viewing this chat, they'll lose access on next render
        if ($userId === auth()->id()) {
            $this->selectedChat = null;
        }
    }

    public function render()
    {
        $user = auth()->user();

        // Get chats — simple, no fancy queries
        try {
            $chats = Chat::orderBy('updated_at', 'desc')->get()->filter(function ($c) use ($user) {
                $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                return in_array($user->id, $members) || in_array((string) $user->id, $members);
            });
        } catch (\Throwable $e) {
            $chats = collect();
        }

        // Unread counts — skip gracefully if columns don't exist
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
                // seen_at column might not exist yet — that's OK
            }
        }

        // Messages for selected chat
        try {
            $messages = $this->selectedChat
                ? Message::where('chat_id', $this->selectedChat)->orderBy('id')->limit(200)->get()
                : collect();
        } catch (\Throwable $e) {
            $messages = collect();
        }

        $activeChat = $this->selectedChat ? Chat::find($this->selectedChat) : null;
        $users = User::all()->keyBy('id');

        // GIF settings — read from DB with safe fallbacks
        $getSetting = function (string $key, mixed $default) {
            try {
                $raw = DB::table('crm_settings')->where('key', $key)->value('value');
                return $raw === null ? $default : json_decode($raw, true);
            } catch (\Throwable $e) {
                return $default;
            }
        };
        $canUseGifPicker = (bool) $getSetting('gifs.module_enabled', true);
        $gifPickerSettings = [
            'module_enabled' => (bool) $getSetting('gifs.module_enabled', true),
            'trending_enabled' => (bool) $getSetting('gifs.trending_enabled', true),
            'movies_enabled' => (bool) $getSetting('gifs.movies_enabled', true),
            'search_enabled' => (bool) $getSetting('gifs.search_enabled', true),
            'recent_enabled' => (bool) $getSetting('gifs.recent_enabled', true),
            'favorites_enabled' => (bool) $getSetting('gifs.favorites_enabled', true),
            'provider' => (string) $getSetting('gifs.provider', 'giphy'),
            'results_limit' => (int) $getSetting('gifs.results_limit', 24),
        ];
        $currentUserId = (int) auth()->id();

        $sharedMedia = collect();
        if ($this->selectedChat && $this->showInfoPanel) {
            try {
                $sharedMedia = Message::where('chat_id', $this->selectedChat)
                    ->where('message_type', 'gif')
                    ->whereNotNull('gif_url')
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();
            } catch (\Throwable $e) {}
        }

        // Check for active direct call on current DM
        $activeDirectCall = null;
        if ($activeChat && $activeChat->type === 'dm') {
            try {
                $activeDirectCall = \App\Services\VideoCall\VideoRoomService::getActiveDirectRoom($activeChat->id);
            } catch (\Throwable $e) {}
        }

        return view('livewire.chat-page', [
            'chats' => $chats,
            'messages' => $messages,
            'activeChat' => $activeChat,
            'users' => $users,
            'gifPickerSettings' => $gifPickerSettings,
            'canUseGifPicker' => $canUseGifPicker,
            'currentUserId' => $currentUserId,
            'sharedMedia' => $sharedMedia,
            'unreadCounts' => $unreadCounts,
            'activeDirectCall' => $activeDirectCall,
        ]);
    }
}
