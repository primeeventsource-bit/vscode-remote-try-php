<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UnifiedNotificationService
{
    // ── Notification types ─────────────────────────────────
    const TYPE_INCOMING_CALL   = 'incoming_call';
    const TYPE_MISSED_CALL     = 'missed_call';
    const TYPE_CALL_DECLINED   = 'call_declined';
    const TYPE_CALL_ENDED      = 'call_ended';
    const TYPE_DIRECT_MESSAGE  = 'direct_message';
    const TYPE_GROUP_MESSAGE   = 'group_message';

    // ── Call notifications ──────────────────────────────────

    public static function sendIncomingCallNotification(User $caller, User $recipient, string $meetingUuid, string $callType = 'video'): void
    {
        $label = $callType === 'voice' ? 'Audio' : 'Video';
        $payload = [
            'type'    => self::TYPE_INCOMING_CALL,
            'title'   => "Incoming {$label} Call",
            'body'    => "{$caller->name} is calling you",
            'icon'    => '/icons/icon-192.png',
            'badge'   => '/icons/icon-192.png',
            'tag'     => 'call-' . $meetingUuid,
            'data'    => [
                'type'         => self::TYPE_INCOMING_CALL,
                'meeting_uuid' => $meetingUuid,
                'caller_name'  => $caller->name,
                'call_type'    => $callType,
                'url'          => "/meeting/{$meetingUuid}",
            ],
        ];

        self::sendPush($recipient, $payload);
    }

    public static function sendMissedCallNotification(User $caller, User $recipient, string $callType = 'video'): void
    {
        $label = $callType === 'voice' ? 'audio' : 'video';
        $payload = [
            'type'  => self::TYPE_MISSED_CALL,
            'title' => 'Missed Call',
            'body'  => "You missed a {$label} call from {$caller->name}",
            'icon'  => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
            'tag'   => 'missed-call-' . now()->timestamp,
            'data'  => [
                'type' => self::TYPE_MISSED_CALL,
                'url'  => '/calls',
            ],
        ];

        self::sendPush($recipient, $payload);
    }

    // ── Message notifications ───────────────────────────────

    public static function sendDirectMessageNotification(User $sender, User $recipient, int $chatId, string $preview): void
    {
        if ($recipient->id === $sender->id) return;

        $payload = [
            'type'  => self::TYPE_DIRECT_MESSAGE,
            'title' => $sender->name,
            'body'  => mb_substr($preview, 0, 120),
            'icon'  => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
            'tag'   => 'chat-' . $chatId,
            'data'  => [
                'type'    => self::TYPE_DIRECT_MESSAGE,
                'chat_id' => $chatId,
                'url'     => '/chat',
            ],
        ];

        self::sendPush($recipient, $payload);
    }

    public static function sendGroupMessageNotification(User $sender, array $recipientIds, int $chatId, string $chatName, string $preview): void
    {
        $recipients = User::whereIn('id', $recipientIds)
            ->where('id', '!=', $sender->id)
            ->get();

        foreach ($recipients as $recipient) {
            $payload = [
                'type'  => self::TYPE_GROUP_MESSAGE,
                'title' => $chatName,
                'body'  => "{$sender->name}: " . mb_substr($preview, 0, 100),
                'icon'  => '/icons/icon-192.png',
                'badge' => '/icons/icon-192.png',
                'tag'   => 'chat-' . $chatId,
                'data'  => [
                    'type'    => self::TYPE_GROUP_MESSAGE,
                    'chat_id' => $chatId,
                    'url'     => '/chat',
                ],
            ];

            self::sendPush($recipient, $payload);
        }
    }

    // ── Push delivery engine ────────────────────────────────

    private static function sendPush(User $user, array $payload): void
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)
            ->where('active', true)
            ->get();

        if ($subscriptions->isEmpty()) return;

        $vapidPublic  = config('services.vapid.public_key');
        $vapidPrivate = config('services.vapid.private_key');

        if (!$vapidPublic || !$vapidPrivate) {
            Log::warning('VAPID keys not configured — push notifications disabled');
            return;
        }

        $jsonPayload = json_encode($payload);

        foreach ($subscriptions as $sub) {
            try {
                $auth = [
                    'VAPID' => [
                        'subject'    => config('app.url'),
                        'publicKey'  => $vapidPublic,
                        'privateKey' => $vapidPrivate,
                    ],
                ];

                $webPush = new \Minishlink\WebPush\WebPush($auth);

                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->p256dh_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]);

                $webPush->sendOneNotification($subscription, $jsonPayload);
            } catch (\Throwable $e) {
                Log::debug('Push delivery failed', [
                    'user_id' => $user->id,
                    'sub_id'  => $sub->id,
                    'error'   => $e->getMessage(),
                ]);

                // Mark expired/invalid subscriptions as inactive
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                    $sub->update(['active' => false]);
                }
            }
        }
    }

    // ── Subscription management ─────────────────────────────

    public static function subscribe(User $user, array $data): PushSubscription
    {
        // Dedup: update existing sub with same endpoint
        $existing = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $data['endpoint'])
            ->first();

        if ($existing) {
            $existing->update([
                'p256dh_key'       => $data['p256dh_key'],
                'auth_token'       => $data['auth_token'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
                'user_agent'       => $data['user_agent'] ?? null,
                'active'           => true,
            ]);
            return $existing;
        }

        return PushSubscription::create([
            'user_id'          => $user->id,
            'endpoint'         => $data['endpoint'],
            'p256dh_key'       => $data['p256dh_key'],
            'auth_token'       => $data['auth_token'],
            'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
            'user_agent'       => $data['user_agent'] ?? null,
            'active'           => true,
        ]);
    }

    public static function unsubscribe(User $user, string $endpoint): void
    {
        PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->update(['active' => false]);
    }

    public static function cleanupStaleSubscriptions(): int
    {
        return PushSubscription::where('active', false)
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();
    }
}
