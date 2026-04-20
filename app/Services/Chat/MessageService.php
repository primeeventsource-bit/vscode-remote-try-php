<?php

namespace App\Services\Chat;

use App\Models\Chat;
use App\Models\ChatAttachment;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\UnifiedNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for sending messages.
 * Used by both ChatPage and ChatWidget.
 */
class MessageService
{
    // ═══════════════════════════════════════════════════════
    // SEND TEXT
    // ═══════════════════════════════════════════════════════

    public function sendText(int $chatId, int $senderId, string $text): ?Message
    {
        $text = trim($text);
        if (!$text || !$chatId) return null;

        try {
            $msg = Message::create([
                'chat_id' => $chatId,
                'message_type' => 'text',
                'sender_id' => $senderId,
                'text' => $text,
                'seen_at' => now(),
                'delivered_at' => now(),
            ]);

            Chat::where('id', $chatId)->update(['updated_at' => now()]);
            $this->notifyRecipients($chatId, $senderId, $text);

            return $msg;
        } catch (\Throwable $e) {
            Log::error('MessageService::sendText failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════
    // SEND GIF
    // ═══════════════════════════════════════════════════════

    public function sendGif(int $chatId, int $senderId, array $gif): ?Message
    {
        if (!$chatId || empty($gif['url'])) return null;

        try {
            $msg = Message::create([
                'chat_id' => $chatId,
                'message_type' => 'gif',
                'sender_id' => $senderId,
                'gif_url' => $gif['url'] ?? '',
                'gif_preview_url' => $gif['preview_url'] ?? $gif['url'] ?? '',
                'gif_provider' => $gif['provider'] ?? 'giphy',
                'gif_external_id' => $gif['id'] ?? null,
                'gif_title' => $gif['title'] ?? 'GIF',
                'seen_at' => now(),
                'delivered_at' => now(),
            ]);

            Chat::where('id', $chatId)->update(['updated_at' => now()]);
            $this->notifyRecipients($chatId, $senderId, 'Sent a GIF');

            return $msg;
        } catch (\Throwable $e) {
            Log::error('MessageService::sendGif failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════
    // SEND FILE / IMAGE
    // ═══════════════════════════════════════════════════════

    public function sendFile(int $chatId, int $senderId, UploadedFile $file): ?Message
    {
        if (!$chatId) return null;

        try {
            $disk = config('filesystems.default', 'public');
            $path = $file->store('chat-files/' . $chatId, $disk);

            $isImage = str_starts_with($file->getMimeType(), 'image/');

            $msg = Message::create([
                'chat_id' => $chatId,
                'message_type' => $isImage ? 'image' : 'file',
                'sender_id' => $senderId,
                'text' => $file->getClientOriginalName(),
                'file_url' => Storage::disk($disk)->url($path),
                'file_name' => $file->getClientOriginalName(),
                'seen_at' => now(),
                'delivered_at' => now(),
            ]);

            // Create attachment record
            ChatAttachment::create([
                'message_id' => $msg->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            Chat::where('id', $chatId)->update(['updated_at' => now()]);
            $this->notifyRecipients($chatId, $senderId, 'Sent a file: ' . $file->getClientOriginalName());

            return $msg;
        } catch (\Throwable $e) {
            Log::error('MessageService::sendFile failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════
    // SEND SYSTEM MESSAGE
    // ═══════════════════════════════════════════════════════

    public function sendSystemMessage(int $chatId, string $text): ?Message
    {
        try {
            return Message::create([
                'chat_id' => $chatId,
                'message_type' => 'text',
                'sender_id' => auth()->id() ?? 0,
                'text' => $text,
                'is_system' => true,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════
    // PUSH NOTIFICATION TRIGGER
    // ═══════════════════════════════════════════════════════

    private function notifyRecipients(int $chatId, int $senderId, string $preview): void
    {
        try {
            $chat = Chat::find($chatId);
            if (!$chat) return;

            $sender = User::find($senderId);
            if (!$sender) return;

            // Get recipients who have not muted this chat
            $recipientIds = ChatParticipant::where('chat_id', $chatId)
                ->where('user_id', '!=', $senderId)
                ->whereNull('left_at')
                ->where('notifications_muted', false)
                ->pluck('user_id')
                ->toArray();

            if (empty($recipientIds)) return;

            if ($chat->type === 'dm') {
                foreach ($recipientIds as $rid) {
                    $recipient = User::find($rid);
                    if ($recipient) {
                        UnifiedNotificationService::sendDirectMessageNotification($sender, $recipient, $chatId, $preview);
                    }
                }
            } else {
                UnifiedNotificationService::sendGroupMessageNotification(
                    $sender,
                    $recipientIds,
                    $chatId,
                    $chat->name ?? 'Group Chat',
                    $preview
                );
            }
        } catch (\Throwable $e) {
            Log::debug('Message push notification failed', ['error' => $e->getMessage()]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // LOAD MESSAGES
    // ═══════════════════════════════════════════════════════

    public function loadMessages(int $chatId, int $limit = 200): Collection
    {
        if (!$chatId) return collect();

        try {
            return Message::with('sender')
                ->where('chat_id', $chatId)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
