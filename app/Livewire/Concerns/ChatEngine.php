<?php

namespace App\Livewire\Concerns;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared chat engine trait used by BOTH ChatWidget (bubble) and ChatPage (sidebar).
 * Single source of truth for: threads, messages, send, read, create, unread, GIF, search.
 *
 * DO NOT duplicate any of this logic in the consuming components.
 */
trait ChatEngine
{
    // ═══════════════════════════════════════════════════════
    // THREAD QUERIES — single source for both UIs
    // ═══════════════════════════════════════════════════════

    protected function loadThreadsForUser(User $user): Collection
    {
        try {
            return Chat::orderBy('updated_at', 'desc')->get()->filter(function ($c) use ($user) {
                $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                return in_array($user->id, $members) || in_array((string) $user->id, $members);
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    // ═══════════════════════════════════════════════════════
    // UNREAD COUNTS — single source for both UIs
    // ═══════════════════════════════════════════════════════

    protected function computeUnreadCounts(Collection $chats, int $userId): Collection
    {
        if ($chats->isEmpty()) return collect();

        try {
            return DB::table('messages')
                ->whereIn('chat_id', $chats->pluck('id'))
                ->where('sender_id', '!=', $userId)
                ->whereNull('seen_at')
                ->selectRaw('chat_id, count(*) as cnt')
                ->groupBy('chat_id')
                ->pluck('cnt', 'chat_id');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    // ═══════════════════════════════════════════════════════
    // MARK AS SEEN — single source for both UIs
    // ═══════════════════════════════════════════════════════

    protected function markChatAsSeen(): void
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

    // ═══════════════════════════════════════════════════════
    // SELECT CHAT — single source for both UIs
    // ═══════════════════════════════════════════════════════

    public function selectChat($id): void
    {
        $this->selectedChat = (int) $id;
        $this->messageInput = '';
        $this->showNewChatForm = false;
        $this->markChatAsSeen();
    }

    // ═══════════════════════════════════════════════════════
    // REFRESH (poll target) — single source for both UIs
    // ═══════════════════════════════════════════════════════

    public function refreshUnreadCounts(): void
    {
        // Re-mark active chat on each poll cycle
        $this->markChatAsSeen();
    }

    // ═══════════════════════════════════════════════════════
    // SEND MESSAGE — single source for both UIs
    // ═══════════════════════════════════════════════════════

    public function sendMessage(): void
    {
        $text = trim($this->messageInput);
        if (! $text || ! $this->selectedChat) return;

        try {
            $msgData = [
                'chat_id'      => $this->selectedChat,
                'message_type' => 'text',
                'sender_id'    => auth()->id(),
                'text'         => $text,
            ];

            // Sender sees own message as delivered immediately
            try { $msgData['seen_at'] = now(); $msgData['delivered_at'] = now(); } catch (\Throwable $e) {}

            Message::create($msgData);
            Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
            $this->messageInput = '';
        } catch (\Throwable $e) {
            Log::error('Message send failed', ['error' => $e->getMessage()]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // SEND GIF — single source for both UIs
    // ═══════════════════════════════════════════════════════

    public function sendGif(array $gif): void
    {
        if (! $this->selectedChat || empty($gif['url'])) return;

        try {
            $msgData = [
                'chat_id'         => $this->selectedChat,
                'message_type'    => 'gif',
                'sender_id'       => auth()->id(),
                'gif_url'         => $gif['url'] ?? '',
                'gif_preview_url' => $gif['preview_url'] ?? $gif['url'] ?? '',
                'gif_provider'    => $gif['provider'] ?? 'giphy',
                'gif_external_id' => $gif['id'] ?? null,
                'gif_title'       => $gif['title'] ?? 'GIF',
            ];

            // Sender delivery status
            try { $msgData['seen_at'] = now(); $msgData['delivered_at'] = now(); } catch (\Throwable $e) {}

            Message::create($msgData);
            Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('GIF send failed', ['error' => $e->getMessage()]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // CREATE NEW CHAT — single source for both UIs
    // ═══════════════════════════════════════════════════════

    public function createNewChat(): void
    {
        $user = auth()->user();
        if (! $user) return;

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
            if (! $otherUser) {
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
                $this->markChatAsSeen();
                return;
            }
        } else {
            $name = trim($this->newChatName) ?: 'Group Chat';
        }

        try {
            $members = array_values(array_unique(array_merge($selectedMembers, [$user->id])));
            $chat = Chat::create([
                'name'       => $name,
                'type'       => $this->newChatType,
                'members'    => $members,
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

    // ═══════════════════════════════════════════════════════
    // TOGGLE NEW CHAT FORM — single source for both UIs
    // ═══════════════════════════════════════════════════════

    public function toggleNewChatForm(): void
    {
        $this->showNewChatForm = ! $this->showNewChatForm;
        if ($this->showNewChatForm) {
            $this->selectedChat = null;
            $this->newChatType = 'dm';
            $this->newChatName = '';
            $this->newChatMembers = [];
            $this->newChatError = '';
        }
    }

    // ═══════════════════════════════════════════════════════
    // VIDEO CALLS — single source for both UIs
    // ═══════════════════════════════════════════════════════

    public function startDirectCall(): void
    {
        if (! $this->selectedChat) return;
        $chat = Chat::find($this->selectedChat);
        if (! $chat || $chat->type !== 'dm') return;

        try {
            // Get other user from DM members
            $members = is_array($chat->members) ? $chat->members : json_decode($chat->members ?? '[]', true);
            $otherId = collect($members)->first(fn($m) => (int) $m !== auth()->id());
            if (! $otherId) return;

            // Use unified meeting system — creates meeting + notifies other user
            $meeting = \App\Services\Meetings\MeetingService::startDirectCall(auth()->user(), (int) $otherId, $chat->id);
            if ($meeting) {
                $this->redirect('/meeting/' . $meeting->uuid);
            }
        } catch (\Throwable $e) { report($e); }
    }

    public function startDirectAudioCall(): void
    {
        // Audio calls use same meeting system (media selection happens on client)
        $this->startDirectCall();
    }

    // ═══════════════════════════════════════════════════════
    // MESSAGES LOAD — single source for both UIs
    // ═══════════════════════════════════════════════════════

    protected function loadMessages(int $limit = 200): Collection
    {
        if (! $this->selectedChat) return collect();

        try {
            return Message::where('chat_id', $this->selectedChat)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    // ═══════════════════════════════════════════════════════
    // SEARCH — single source for both UIs
    // ═══════════════════════════════════════════════════════

    protected function searchChats(Collection $chats, string $query, Collection $users): array
    {
        $q = trim($query);
        if ($q === '') return ['chats' => collect(), 'messages' => collect(), 'searching' => false];

        $chatResults = $chats->filter(function ($c) use ($q, $users) {
            $name = $c->name ?? '';
            if (stripos($name, $q) !== false) return true;
            $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
            foreach ($members as $mid) {
                $mu = $users->get((int) $mid);
                if ($mu && stripos($mu->name ?? '', $q) !== false) return true;
            }
            return false;
        });

        $msgResults = collect();
        try {
            $msgResults = Message::whereIn('chat_id', $chats->pluck('id'))
                ->where('text', 'like', "%{$q}%")
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) {}

        return ['chats' => $chatResults, 'messages' => $msgResults, 'searching' => true];
    }

    // ═══════════════════════════════════════════════════════
    // GIF SETTINGS — single source for both UIs
    // ═══════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════
    // PENDING CALL INVITES — single source for both UIs
    // ═══════════════════════════════════════════════════════

    protected function loadPendingCallInvite(): ?object
    {
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
    // GIF SETTINGS — single source for both UIs
    // ═══════════════════════════════════════════════════════

    protected function loadGifSettings(): array
    {
        $get = function (string $key, mixed $default) {
            try {
                $raw = DB::table('crm_settings')->where('key', $key)->value('value');
                return $raw === null ? $default : json_decode($raw, true);
            } catch (\Throwable $e) {
                return $default;
            }
        };

        return [
            'module_enabled'   => (bool) $get('gifs.module_enabled', true),
            'trending_enabled' => (bool) $get('gifs.trending_enabled', true),
            'movies_enabled'   => (bool) $get('gifs.movies_enabled', true),
            'search_enabled'   => (bool) $get('gifs.search_enabled', true),
            'recent_enabled'   => (bool) $get('gifs.recent_enabled', true),
            'favorites_enabled' => (bool) $get('gifs.favorites_enabled', true),
            'provider'         => (string) $get('gifs.provider', 'giphy'),
            'results_limit'    => (int) $get('gifs.results_limit', 24),
        ];
    }
}
