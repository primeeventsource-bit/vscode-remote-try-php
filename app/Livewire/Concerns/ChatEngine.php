<?php

namespace App\Livewire\Concerns;

use App\Models\Chat;
use App\Models\User;
use App\Services\Chat\CallService;
use App\Services\Chat\ChatService;
use App\Services\Chat\MessageService;
use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared chat engine trait used by BOTH ChatWidget (bubble) and ChatPage (sidebar).
 * Delegates ALL business logic to ChatService / MessageService / CallService.
 *
 * DO NOT duplicate any of this logic in the consuming components.
 */
trait ChatEngine
{
    // ═══════════════════════════════════════════════════════
    // SERVICE ACCESSORS
    // ═══════════════════════════════════════════════════════

    protected function chatService(): ChatService
    {
        return app(ChatService::class);
    }

    protected function messageService(): MessageService
    {
        return app(MessageService::class);
    }

    protected function callService(): CallService
    {
        return app(CallService::class);
    }

    // ═══════════════════════════════════════════════════════
    // THREAD QUERIES
    // ═══════════════════════════════════════════════════════

    protected function loadThreadsForUser(User $user): Collection
    {
        try {
            return $this->chatService()->getChatsForUser($user);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    // ═══════════════════════════════════════════════════════
    // UNREAD COUNTS
    // ═══════════════════════════════════════════════════════

    protected function computeUnreadCounts(Collection $chats, int $userId): Collection
    {
        try {
            return $this->chatService()->computeUnreadCounts($chats, $userId);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    // ═══════════════════════════════════════════════════════
    // MARK AS READ
    // ═══════════════════════════════════════════════════════

    protected function markChatAsSeen(): void
    {
        if (!$this->selectedChat) return;
        try {
            $this->chatService()->markAsRead($this->selectedChat, auth()->id());
        } catch (\Throwable $e) {
            // Mark-as-read failure must not break thread selection
        }
    }

    // ═══════════════════════════════════════════════════════
    // SELECT CHAT
    // ═══════════════════════════════════════════════════════

    public function selectChat($id): void
    {
        $this->selectedChat = (int) $id;
        $this->messageInput = '';
        $this->showNewChatForm = false;
        $this->markChatAsSeen();
    }

    // ═══════════════════════════════════════════════════════
    // REFRESH (poll target)
    // ═══════════════════════════════════════════════════════

    public function refreshUnreadCounts(): void
    {
        $this->markChatAsSeen();
    }

    // ═══════════════════════════════════════════════════════
    // SEND MESSAGE
    // ═══════════════════════════════════════════════════════

    public function sendMessage(): void
    {
        $text = trim($this->messageInput);
        if (!$text || !$this->selectedChat) return;

        $msg = $this->messageService()->sendText($this->selectedChat, auth()->id(), $text);
        if ($msg) {
            $this->messageInput = '';
        }
    }

    // ═══════════════════════════════════════════════════════
    // SEND GIF
    // ═══════════════════════════════════════════════════════

    public function sendGif(array $gif): void
    {
        if (!$this->selectedChat || empty($gif['url'])) return;
        $this->messageService()->sendGif($this->selectedChat, auth()->id(), $gif);
    }

    // ═══════════════════════════════════════════════════════
    // CREATE NEW CHAT
    // ═══════════════════════════════════════════════════════

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
            $this->newChatName = '';
            $this->newChatMembers = [];
            $this->messageInput = '';
            $this->newChatError = '';
            $this->markChatAsSeen();
        } catch (\Throwable $e) {
            $this->newChatError = 'Failed to create chat. Please try again.';
        }
    }

    // ═══════════════════════════════════════════════════════
    // TOGGLE NEW CHAT FORM
    // ═══════════════════════════════════════════════════════

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

    // ═══════════════════════════════════════════════════════
    // VIDEO CALLS
    // ═══════════════════════════════════════════════════════

    public function startDirectCall(): void
    {
        if (!$this->selectedChat) return;
        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type !== 'dm') return;

        $otherId = collect($chat->getMemberIds())->first(fn($m) => (int) $m !== auth()->id());
        if (!$otherId) return;

        $meeting = $this->callService()->startDirectCall(auth()->user(), (int) $otherId, $chat->id);
        if ($meeting) {
            $this->redirect('/meeting/' . $meeting->uuid);
        }
    }

    public function startDirectAudioCall(): void
    {
        $this->startDirectCall();
    }

    // ═══════════════════════════════════════════════════════
    // MESSAGES LOAD
    // ═══════════════════════════════════════════════════════

    protected function loadMessages(int $limit = 200): Collection
    {
        return $this->messageService()->loadMessages($this->selectedChat ?? 0, $limit);
    }

    // ═══════════════════════════════════════════════════════
    // SEARCH
    // ═══════════════════════════════════════════════════════

    protected function searchChats(Collection $chats, string $query, Collection $users): array
    {
        return $this->chatService()->search($chats, $query, $users);
    }

    // ═══════════════════════════════════════════════════════
    // LAST MESSAGES (batch for thread list)
    // ═══════════════════════════════════════════════════════

    protected function getLastMessages(Collection $chats): Collection
    {
        return $this->chatService()->getLastMessages($chats);
    }

    // ═══════════════════════════════════════════════════════
    // PENDING CALL INVITES
    // ═══════════════════════════════════════════════════════

    protected function loadPendingCallInvite(): ?object
    {
        // Check unified meetings system first
        $invite = $this->callService()->getPendingInvite(auth()->id());
        if ($invite) return $invite;

        // Fallback: legacy video room system
        try {
            return \App\Models\VideoRoomParticipant::where('user_id', auth()->id())
                ->where('invite_status', 'pending')
                ->whereHas('room', fn ($q) => $q->whereIn('status', ['waiting', 'active']))
                ->with(['room.creator'])
                ->latest()
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════
    // GIF SETTINGS
    // ═══════════════════════════════════════════════════════

    protected function loadGifSettings(): array
    {
        return [
            'module_enabled'    => (bool) AppSetting::getValue('gifs', 'module_enabled', true),
            'trending_enabled'  => (bool) AppSetting::getValue('gifs', 'trending_enabled', true),
            'movies_enabled'    => (bool) AppSetting::getValue('gifs', 'movies_enabled', true),
            'search_enabled'    => (bool) AppSetting::getValue('gifs', 'search_enabled', true),
            'recent_enabled'    => (bool) AppSetting::getValue('gifs', 'recent_enabled', true),
            'favorites_enabled' => (bool) AppSetting::getValue('gifs', 'favorites_enabled', true),
            'provider'          => (string) AppSetting::getValue('gifs', 'provider', 'giphy'),
            'results_limit'     => (int) AppSetting::getValue('gifs', 'results_limit', 24),
        ];
    }
}
