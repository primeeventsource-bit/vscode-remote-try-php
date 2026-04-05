<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\CrmNote;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CrmNoteService
{
    /**
     * Create a note on a noteable record (Deal or Client/Deal).
     */
    public static function createNote(Model $noteable, User $user, string $body): CrmNote
    {
        return CrmNote::create([
            'noteable_type' => get_class($noteable),
            'noteable_id' => $noteable->id,
            'body' => trim($body),
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * Update a note body. Records who edited and when.
     */
    public static function updateNote(CrmNote $note, User $user, string $body): CrmNote
    {
        $note->update([
            'body' => trim($body),
            'updated_by_user_id' => $user->id,
        ]);
        return $note;
    }

    /**
     * Send a note's content into a direct-message chat.
     */
    public static function sendNoteToDirectChat(
        CrmNote $note,
        User $sender,
        User $recipient,
        ?string $extraMessage = null
    ): void {
        DB::transaction(function () use ($note, $sender, $recipient, $extraMessage) {
            // Find or create DM thread
            $chat = Chat::where('type', 'dm')->get()->first(function ($c) use ($sender, $recipient) {
                $m = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                $ids = array_map('intval', array_values($m));
                sort($ids);
                $t = [$sender->id, $recipient->id];
                sort($t);
                return $ids === $t;
            });

            if (!$chat) {
                $chat = Chat::create([
                    'name' => $recipient->name ?? 'Direct Message',
                    'type' => 'dm',
                    'members' => [$sender->id, $recipient->id],
                    'created_by' => $sender->id,
                ]);
            }

            // Build chat message
            $noteCreator = User::find($note->created_by_user_id);
            $recordType = class_basename($note->noteable_type);
            $recordId = $note->noteable_id;

            $text = "📝 Note shared from {$recordType} #{$recordId}\n";
            $text .= "Original note by {$noteCreator->name} on {$note->created_at->format('M j, Y g:i A')}:\n\n";
            $text .= "\"{$note->body}\"\n\n";
            $text .= "Shared by {$sender->name} for follow-up.";

            if ($extraMessage) {
                $text .= "\n\nAdditional context: {$extraMessage}";
            }

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $sender->id,
                'message_type' => 'text',
                'text' => $text,
            ]);

            $chat->update(['updated_at' => now()]);

            // Mark note as sent to chat
            $note->update([
                'sent_to_chat_at' => now(),
                'sent_to_chat_by_user_id' => $sender->id,
            ]);
        });
    }
}
