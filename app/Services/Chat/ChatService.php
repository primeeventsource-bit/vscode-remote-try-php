<?php

namespace App\Services\Chat;

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for all chat operations.
 * Used by both ChatPage (sidebar) and ChatWidget (bubble).
 */
class ChatService
{
    // ═══════════════════════════════════════════════════════
    // THREAD QUERIES
    // ═══════════════════════════════════════════════════════

    /**
     * Get all chats for a user, ordered by most recent activity.
     */
    public function getChatsForUser(User $user): Collection
    {
        // Try normalized chat_participants table first
        try {
            $chatIds = ChatParticipant::where('user_id', $user->id)
                ->whereNull('left_at')
                ->pluck('chat_id');

            if ($chatIds->isNotEmpty()) {
                return Chat::whereIn('id', $chatIds)
                    ->orderByDesc('updated_at')
                    ->get();
            }
        } catch (\Throwable $e) {
            // chat_participants table may not exist yet
        }

        // Fallback: JSON members query
        try {
            return Chat::where(function ($q) use ($user) {
                $q->whereJsonContains('members', $user->id)
                  ->orWhereJsonContains('members', (string) $user->id);
            })->orderByDesc('updated_at')->get();
        } catch (\Throwable $e) {
            Log::warning('ChatService::getChatsForUser failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    // ═══════════════════════════════════════════════════════
    // CREATE CHATS
    // ═══════════════════════════════════════════════════════

    /**
     * Create or find existing direct chat between two users.
     */
    public function findOrCreateDirectChat(User $userA, User $userB): Chat
    {
        // Check existing via participants table
        $existing = $this->findExistingDirectChat($userA->id, $userB->id);
        if ($existing) return $existing;

        $chat = Chat::create([
            'name' => $userB->name,
            'type' => 'dm',
            'members' => [$userA->id, $userB->id],
            'created_by' => $userA->id,
        ]);

        // Sync to participants table
        $this->addParticipant($chat, $userA->id, 'host');
        $this->addParticipant($chat, $userB->id, 'member');

        return $chat;
    }

    /**
     * Create a group chat.
     */
    public function createGroupChat(User $creator, array $memberIds, string $name = 'Group Chat'): Chat
    {
        $allMembers = array_values(array_unique(array_merge($memberIds, [$creator->id])));

        $chat = Chat::create([
            'name' => $name,
            'type' => 'group',
            'members' => $allMembers,
            'created_by' => $creator->id,
        ]);

        // Sync all members to participants table
        $this->addParticipant($chat, $creator->id, 'host');
        foreach ($memberIds as $uid) {
            if ((int) $uid !== $creator->id) {
                $this->addParticipant($chat, (int) $uid, 'member');
            }
        }

        return $chat;
    }

    /**
     * Find existing DM between two users.
     */
    public function findExistingDirectChat(int $userIdA, int $userIdB): ?Chat
    {
        // Try via participants table
        try {
            $chatIdsA = ChatParticipant::where('user_id', $userIdA)->whereNull('left_at')->pluck('chat_id');
            $chatIdsB = ChatParticipant::where('user_id', $userIdB)->whereNull('left_at')->pluck('chat_id');
            $commonIds = $chatIdsA->intersect($chatIdsB);

            if ($commonIds->isNotEmpty()) {
                $existing = Chat::whereIn('id', $commonIds)->where('type', 'dm')->first();
                if ($existing) return $existing;
            }
        } catch (\Throwable $e) {}

        // Fallback: JSON query
        try {
            return Chat::where('type', 'dm')
                ->where(function ($q) use ($userIdA, $userIdB) {
                    $q->where(function ($q2) use ($userIdA, $userIdB) {
                        $q2->whereJsonContains('members', $userIdA)
                            ->whereJsonContains('members', $userIdB);
                    })->orWhere(function ($q2) use ($userIdA, $userIdB) {
                        $q2->whereJsonContains('members', (string) $userIdA)
                            ->whereJsonContains('members', (string) $userIdB);
                    });
                })->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════
    // PARTICIPANTS
    // ═══════════════════════════════════════════════════════

    public function addParticipant(Chat $chat, int $userId, string $role = 'member'): ChatParticipant
    {
        $participant = ChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $userId],
            ['role_in_chat' => $role, 'joined_at' => now()]
        );

        // Keep JSON members in sync
        $this->syncJsonMembers($chat);

        return $participant;
    }

    public function removeParticipant(Chat $chat, int $userId): void
    {
        ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', $userId)
            ->update(['left_at' => now()]);

        $this->syncJsonMembers($chat);
    }

    /**
     * Keep the legacy JSON members column in sync with chat_participants.
     */
    private function syncJsonMembers(Chat $chat): void
    {
        try {
            $memberIds = ChatParticipant::where('chat_id', $chat->id)
                ->whereNull('left_at')
                ->pluck('user_id')
                ->toArray();

            $chat->update(['members' => $memberIds]);
        } catch (\Throwable $e) {
            // Best effort
        }
    }

    // ═══════════════════════════════════════════════════════
    // UNREAD COUNTS
    // ═══════════════════════════════════════════════════════

    /**
     * Compute unread counts for all chats for a user.
     * Returns Collection keyed by chat_id => count.
     */
    public function computeUnreadCounts(Collection $chats, int $userId): Collection
    {
        if ($chats->isEmpty()) return collect();

        // Try participant-based unread (faster, uses last_read_message_id)
        try {
            $participants = ChatParticipant::where('user_id', $userId)
                ->whereIn('chat_id', $chats->pluck('id'))
                ->whereNull('left_at')
                ->get()
                ->keyBy('chat_id');

            if ($participants->isNotEmpty()) {
                $counts = collect();
                foreach ($participants as $chatId => $participant) {
                    $query = Message::where('chat_id', $chatId)
                        ->where('sender_id', '!=', $userId);

                    if ($participant->last_read_message_id) {
                        $query->where('id', '>', $participant->last_read_message_id);
                    }

                    $count = $query->count();
                    if ($count > 0) $counts[$chatId] = $count;
                }
                return $counts;
            }
        } catch (\Throwable $e) {}

        // Fallback: seen_at based counting
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
    // MARK AS READ
    // ═══════════════════════════════════════════════════════

    /**
     * Mark a chat as read for a user.
     */
    public function markAsRead(int $chatId, int $userId): void
    {
        if (!$chatId) return;

        // Update participant's last_read
        try {
            $lastMsg = Message::where('chat_id', $chatId)->orderByDesc('id')->first();

            ChatParticipant::where('chat_id', $chatId)
                ->where('user_id', $userId)
                ->update([
                    'last_read_message_id' => $lastMsg?->id,
                    'last_read_at' => now(),
                ]);
        } catch (\Throwable $e) {}

        // Also update legacy seen_at on messages
        try {
            $now = now()->toDateTimeString();
            Message::where('chat_id', $chatId)
                ->where('sender_id', '!=', $userId)
                ->whereNull('seen_at')
                ->update(['seen_at' => $now, 'delivered_at' => $now]);
        } catch (\Throwable $e) {}
    }

    // ═══════════════════════════════════════════════════════
    // LAST MESSAGES (batch for thread list)
    // ═══════════════════════════════════════════════════════

    /**
     * Get the latest message for each chat in a single query.
     */
    public function getLastMessages(Collection $chats): Collection
    {
        if ($chats->isEmpty()) return collect();

        try {
            $chatIds = $chats->pluck('id');
            return Message::whereIn('chat_id', $chatIds)
                ->whereIn('id', function ($q) use ($chatIds) {
                    $q->selectRaw('MAX(id)')
                        ->from('messages')
                        ->whereIn('chat_id', $chatIds)
                        ->groupBy('chat_id');
                })
                ->get()
                ->keyBy('chat_id');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    // ═══════════════════════════════════════════════════════
    // SEARCH
    // ═══════════════════════════════════════════════════════

    /**
     * Search chats by name/members/message content.
     */
    public function search(Collection $chats, string $query, Collection $users): array
    {
        $q = trim($query);
        if ($q === '') return ['chats' => collect(), 'messages' => collect(), 'searching' => false];

        $chatResults = $chats->filter(function ($c) use ($q, $users) {
            if (stripos($c->name ?? '', $q) !== false) return true;
            foreach ($c->getMemberIds() as $mid) {
                $mu = $users->get($mid);
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
}
