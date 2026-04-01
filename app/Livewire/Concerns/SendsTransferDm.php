<?php

namespace App\Livewire\Concerns;

use App\Models\Chat;
use App\Models\Lead;
use App\Models\LeadTransfer;
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

            // Log transfer to lead_transfers table if it's a Lead
            if ($itemType === 'Lead') {
                try {
                    $lead = Lead::find($itemId);
                    LeadTransfer::create([
                        'lead_id' => $itemId,
                        'from_user_id' => $lead?->assigned_to !== $recipientId ? $lead?->assigned_to : $senderId,
                        'to_user_id' => $recipientId,
                        'transferred_by_user_id' => $senderId,
                        'transfer_type' => strtolower($role),
                        'disposition_snapshot' => $lead?->disposition,
                    ]);
                } catch (\Throwable $e) {
                    // lead_transfers table might not exist yet
                }
            }

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
