<?php

namespace App\Livewire;

use App\Models\Message;
use Livewire\Component;

class MessageNotificationToast extends Component
{
    public ?int $lastSeenMessageId = null;
    public array $toasts = [];

    public function mount(): void
    {
        // Start from the latest message
        $this->lastSeenMessageId = Message::max('id') ?? 0;
    }

    public function checkNewMessages(): void
    {
        $user = auth()->user();
        if (!$user) return;

        // Find messages sent after our last check, not by us
        $newMessages = Message::where('id', '>', $this->lastSeenMessageId)
            ->where('sender_id', '!=', $user->id)
            ->where('is_system', false)
            ->with(['sender', 'chat'])
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($newMessages->isEmpty()) return;

        // Check which chats this user is part of
        $userChatIds = \App\Models\ChatParticipant::where('user_id', $user->id)
            ->whereNull('left_at')
            ->where('notifications_muted', false)
            ->pluck('chat_id')
            ->toArray();

        foreach ($newMessages as $msg) {
            if (!in_array($msg->chat_id, $userChatIds)) continue;

            $chatName = $msg->chat?->type === 'dm'
                ? ($msg->sender?->name ?? 'Someone')
                : ($msg->chat?->name ?? 'Group Chat');

            $this->toasts[] = [
                'id'      => $msg->id,
                'title'   => $chatName,
                'body'    => mb_substr($msg->text ?? 'Sent a file', 0, 80),
                'sender'  => $msg->sender?->name ?? 'Unknown',
                'avatar'  => $msg->sender?->avatar ?? substr($msg->sender?->name ?? '?', 0, 2),
                'color'   => $msg->sender?->color ?? '#3b82f6',
                'chat_id' => $msg->chat_id,
                'is_dm'   => $msg->chat?->type === 'dm',
            ];
        }

        $this->lastSeenMessageId = $newMessages->last()->id;

        // Keep only last 3 toasts
        $this->toasts = array_slice($this->toasts, -3);
    }

    public function dismissToast(int $msgId): void
    {
        $this->toasts = array_values(array_filter($this->toasts, fn($t) => $t['id'] !== $msgId));
    }

    public function render()
    {
        return view('livewire.message-notification-toast');
    }
}
