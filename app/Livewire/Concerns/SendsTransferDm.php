<?php

namespace App\Livewire\Concerns;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;

trait SendsTransferDm
{
    protected function sendTransferDm(int $recipientId, string $itemType, int $itemId, string $itemName, string $role): void
    {
        try {
            $senderId = auth()->id();
            if (!$senderId || $senderId === $recipientId) return;

            // Find or create direct chat
            $chat = Chat::where('type', 'dm')->get()->first(function ($c) use ($senderId, $recipientId) {
                $m = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                $ids = array_map('intval', array_values($m));
                sort($ids);
                $t = [$senderId, $recipientId];
                sort($t);
                return $ids === $t;
            });

            if (!$chat) {
                $recipient = User::find($recipientId);
                $chat = Chat::create([
                    'name' => $recipient->name ?? 'Direct Message',
                    'type' => 'dm',
                    'members' => array_values([$senderId, $recipientId]),
                    'created_by' => $senderId,
                ]);
            }

            $senderName = auth()->user()->name ?? 'System';
            $icon = $itemType === 'Deal' ? '💼' : '📋';
            $text = "{$icon} {$itemType} Transfer\n{$itemName} ({$itemType} #{$itemId}) has been assigned to you for {$role}.\nTransferred by: {$senderName}\nOpen the {$itemType}s page to view details.";

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $senderId,
                'message_type' => 'text',
                'text' => $text,
            ]);

            $chat->update(['updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Transfer DM failed', ['error' => $e->getMessage(), 'recipient' => $recipientId]);
        }
    }
}
