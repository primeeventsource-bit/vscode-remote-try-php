<?php

namespace App\Livewire;

use App\Livewire\Concerns\ChatEngine;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Services\Storage\ActiveStorageResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Full-page sidebar chat — uses shared ChatEngine trait
 * for all thread/message/unread/send/GIF/search logic.
 *
 * Only contains sidebar-specific features:
 * - info panel / shared media
 * - group member management
 * - avatar/icon uploads
 * - emoji avatar selection
 */
#[Layout('components.layouts.app')]
#[Title('Chat')]
class ChatPage extends Component
{
    use ChatEngine, WithFileUploads;

    // Shared state (used by ChatEngine trait)
    public ?int $selectedChat = null;
    public string $messageInput = '';
    public bool $showNewChatForm = false;
    public string $newChatType = 'dm';
    public string $newChatName = '';
    public array $newChatMembers = [];
    public string $newChatError = '';
    public string $chatSearch = '';

    // Sidebar-only state
    public string $chatTab = 'all'; // all, direct, group, unread
    public bool $showInfoPanel = false;
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
            if (!$enabled) {
                $this->redirectRoute('dashboard');
                session()->flash('error', 'Chat module is disabled.');
                return;
            }
        } catch (\Throwable $e) {
            // Settings table may not exist — allow access by default
        }
        // Note: view_chat permission check removed from mount() to prevent
        // redirect loops when user permissions haven't been refreshed yet.
        // The route itself is inside auth middleware which is sufficient.
    }

    // Override selectChat to also close sidebar-only panels
    public function selectChat($id): void
    {
        $this->selectedChat = (int) $id;
        $this->messageInput = '';
        $this->showNewChatForm = false;
        $this->showInfoPanel = false;
        $this->showManageMembers = false;
        try { $this->markChatAsSeen(); } catch (\Throwable $e) {}
        $this->dispatch('scroll-to-bottom');
    }

    // New Chat shortcuts — avoid chained $set() + method in wire:click
    public function startNewDm(): void
    {
        $this->newChatType = 'dm';
        $this->selectedChat = null;
        $this->showNewChatForm = true;
        $this->showInfoPanel = false;
        $this->newChatName = '';
        $this->newChatMembers = [];
        $this->newChatError = '';
    }

    public function startNewGroup(): void
    {
        $this->newChatType = 'group';
        $this->selectedChat = null;
        $this->showNewChatForm = true;
        $this->showInfoPanel = false;
        $this->newChatName = '';
        $this->newChatMembers = [];
        $this->newChatError = '';
    }

    // Override sendMessage to auto-scroll after send
    public function sendMessage(): void
    {
        $text = trim($this->messageInput);
        if (!$text || !$this->selectedChat) return;

        $msg = $this->messageService()->sendText($this->selectedChat, auth()->id(), $text);
        if ($msg) {
            $this->messageInput = '';
            $this->dispatch('scroll-to-bottom');
        }
    }

    // Override createNewChat to auto-open the created chat
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

        try {
            if ($this->newChatType === 'dm') {
                $otherUser = User::find($selectedMembers[0]);
                if (!$otherUser) {
                    $this->newChatError = 'The selected user could not be found.';
                    return;
                }
                $chat = $this->chatService()->findOrCreateDirectChat($user, $otherUser);
            } else {
                $name = trim($this->newChatName) ?: 'Group Chat';
                $chat = $this->chatService()->createGroupChat($user, $selectedMembers, $name);
            }

            $this->selectedChat = $chat->id;
            $this->showNewChatForm = false;
            $this->showInfoPanel = false;
            $this->newChatName = '';
            $this->newChatMembers = [];
            $this->messageInput = '';
            $this->newChatError = '';
            $this->markChatAsSeen();
            $this->dispatch('scroll-to-bottom');
        } catch (\Throwable $e) {
            $this->newChatError = 'Failed to create chat. Please try again.';
        }
    }

    // ── Sidebar-only: Info Panel ─────────────────────────

    public function toggleInfoPanel(): void
    {
        $this->showInfoPanel = !$this->showInfoPanel;
    }

    // ── Sidebar-only: Avatar / Icon Uploads ──────────────

    public function uploadAvatar(): void
    {
        $this->validate(['avatarUpload' => 'image|max:2048|mimes:jpg,jpeg,png,webp']);
        try {
            $resolver = app(ActiveStorageResolver::class);
            $user = auth()->user();
            if ($user->avatar_path) $resolver->delete($user->avatar_path);
            $path = $resolver->storeUpload($this->avatarUpload, 'avatars');
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
            try { app(ActiveStorageResolver::class)->delete($user->avatar_path); } catch (\Throwable $e) { Log::warning('Avatar delete failed', ['error' => $e->getMessage()]); }
            $user->update(['avatar_path' => null]);
        }
    }

    public function uploadGroupIcon(): void
    {
        if (!$this->selectedChat) return;
        if (!auth()->user()?->hasRole('master_admin')) return;
        $this->validate(['groupIconUpload' => 'image|max:2048|mimes:jpg,jpeg,png,webp']);
        try {
            $resolver = app(ActiveStorageResolver::class);
            $chat = Chat::find($this->selectedChat);
            if (!$chat || $chat->type === 'dm') return;
            if ($chat->icon_path) $resolver->delete($chat->icon_path);
            $path = $resolver->storeUpload($this->groupIconUpload, 'chat-icons');
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
            try { app(ActiveStorageResolver::class)->delete($chat->icon_path); } catch (\Throwable $e) { Log::warning('Group icon delete failed', ['error' => $e->getMessage()]); }
            $chat->update(['icon_path' => null]);
        }
    }

    public function setEmojiAvatar(string $emoji): void
    {
        $user = auth()->user();
        if (!$user) return;
        if ($user->avatar_path) { try { app(ActiveStorageResolver::class)->delete($user->avatar_path); } catch (\Throwable $e) {} }
        $user->update(['avatar_emoji' => $emoji, 'avatar_path' => null]);
    }

    public function setGroupEmojiIcon(string $emoji): void
    {
        if (!$this->selectedChat) return;
        if (!auth()->user()?->hasRole('master_admin')) return;
        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type === 'dm') return;
        if ($chat->icon_path) { try { app(ActiveStorageResolver::class)->delete($chat->icon_path); } catch (\Throwable $e) {} }
        $chat->update(['icon_emoji' => $emoji, 'icon_path' => null]);
    }

    // ── Sidebar-only: Group Member Management ────────────

    private function canManageGroup(): bool
    {
        return auth()->user()?->hasRole('master_admin', 'admin') ?? false;
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

        if (empty($added)) { $this->addMemberIds = []; return; }

        $chat->update(['members' => array_values(array_unique($members))]);

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
        if ($chat->created_by === $userId) return;

        $members = array_values(array_filter($members, fn($m) => $m !== $userId));
        $chat->update(['members' => $members]);

        $removedUser = User::find($userId);
        $actorName = auth()->user()->name ?? 'Admin';
        try {
            Message::create([
                'chat_id' => $chat->id,
                'message_type' => 'text',
                'sender_id' => auth()->id(),
                'text' => "📌 " . ($removedUser->name ?? 'User') . " was removed from the group by {$actorName}",
            ]);
        } catch (\Throwable $e) {}
        $chat->update(['updated_at' => now()]);

        if ($userId === auth()->id()) $this->selectedChat = null;
    }

    // ── Render ───────────────────────────────────────────

    public function render()
    {
        $user = auth()->user();
        if (!$user) {
            return view('livewire.chat-page', [
                'chats' => collect(), 'messages' => collect(), 'activeChat' => null,
                'users' => collect(), 'gifPickerSettings' => [], 'canUseGifPicker' => false,
                'currentUserId' => 0, 'searchResults' => collect(), 'searchMessageResults' => collect(),
                'isSearching' => false, 'sharedMedia' => collect(), 'lastMessages' => collect(),
                'unreadCounts' => collect(), 'activeDirectCall' => null,
            ]);
        }

        try {
            $chats = $this->loadThreadsForUser($user);
        } catch (\Throwable $e) { $chats = collect(); }

        if ($this->selectedChat) {
            $this->markChatAsSeen();
        }

        $unreadCounts = $this->computeUnreadCounts($chats, $user->id);
        $messages = $this->loadMessages(200);
        try {
            $activeChat = $this->selectedChat ? Chat::find($this->selectedChat) : null;
            $users = User::all()->keyBy('id');
        } catch (\Throwable $e) {
            $activeChat = null;
            $users = collect();
        }

        // Pre-load last message for each chat (single query via service)
        $lastMessages = $this->getLastMessages($chats);
        $gifPickerSettings = $this->loadGifSettings();
        $canUseGifPicker = $gifPickerSettings['module_enabled'];
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

        $activeDirectCall = null;
        if ($activeChat && $activeChat->type === 'dm') {
            try { $activeDirectCall = \App\Services\VideoCall\VideoRoomService::getActiveDirectRoom($activeChat->id); } catch (\Throwable $e) {}
        }

        // Search (uses shared ChatEngine::searchChats)
        $search = $this->searchChats($chats, $this->chatSearch, $users);
        $searchResults = $search['chats'];
        $searchMessageResults = $search['messages'];
        $isSearching = $search['searching'];

        return view('livewire.chat-page', [
            'chats'                => $chats,
            'messages'             => $messages,
            'activeChat'           => $activeChat,
            'users'                => $users,
            'gifPickerSettings'    => $gifPickerSettings,
            'canUseGifPicker'      => $canUseGifPicker,
            'currentUserId'        => $currentUserId,
            'searchResults'        => $searchResults,
            'searchMessageResults' => $searchMessageResults,
            'isSearching'          => $isSearching,
            'sharedMedia'       => $sharedMedia,
            'lastMessages'     => $lastMessages,
            'unreadCounts'      => $unreadCounts,
            'activeDirectCall'  => $activeDirectCall,
        ]);
    }
}
