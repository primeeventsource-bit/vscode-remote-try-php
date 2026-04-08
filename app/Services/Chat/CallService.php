<?php

namespace App\Services\Chat;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Services\UnifiedNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Unified call/meeting service.
 * Wraps the existing Meeting/MeetingParticipant models.
 */
class CallService
{
    /**
     * Start a direct call (audio or video) from a chat DM.
     */
    public function startDirectCall(User $caller, int $recipientId, ?int $chatId = null, string $type = 'direct'): ?Meeting
    {
        try {
            $roomName = 'call-' . Str::uuid();

            $meeting = Meeting::create([
                'type' => $type,
                'source_type' => 'bubble_dm',
                'source_id' => $chatId,
                'provider' => 'twilio',
                'provider_room_name' => $roomName,
                'title' => $caller->name . ' — Direct Call',
                'host_user_id' => $caller->id,
                'status' => 'ringing',
                'max_participants' => 2,
            ]);

            // Host participant
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'user_id' => $caller->id,
                'role' => 'host',
                'invite_status' => 'accepted',
                'attendance_status' => 'not_joined',
                'participant_identity' => 'user-' . $caller->id,
            ]);

            // Invite recipient
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'user_id' => $recipientId,
                'role' => 'participant',
                'invite_status' => 'pending',
                'attendance_status' => 'not_joined',
                'participant_identity' => 'user-' . $recipientId,
            ]);

            // Send push notification to recipient
            $recipient = User::find($recipientId);
            if ($recipient) {
                UnifiedNotificationService::sendIncomingCallNotification(
                    $caller, $recipient, $meeting->uuid, $type === 'direct' ? 'video' : $type
                );
            }

            return $meeting;
        } catch (\Throwable $e) {
            Log::error('CallService::startDirectCall failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Start a group meeting.
     */
    public function startGroupMeeting(User $host, array $participantIds, string $title = 'Meeting', ?int $chatId = null): ?Meeting
    {
        try {
            $roomName = 'meeting-' . Str::uuid();

            $meeting = Meeting::create([
                'type' => 'group',
                'source_type' => $chatId ? 'group_chat' : 'meetings_module',
                'source_id' => $chatId,
                'provider' => 'twilio',
                'provider_room_name' => $roomName,
                'title' => $title,
                'host_user_id' => $host->id,
                'status' => 'pending',
                'max_participants' => 20,
            ]);

            // Host
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'user_id' => $host->id,
                'role' => 'host',
                'invite_status' => 'accepted',
                'attendance_status' => 'not_joined',
                'participant_identity' => 'user-' . $host->id,
            ]);

            // Invite all participants and send push
            foreach ($participantIds as $uid) {
                $uid = (int) $uid;
                if ($uid === $host->id) continue;

                MeetingParticipant::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $uid,
                    'role' => 'participant',
                    'invite_status' => 'pending',
                    'attendance_status' => 'not_joined',
                    'participant_identity' => 'user-' . $uid,
                ]);

                $recipient = User::find($uid);
                if ($recipient) {
                    UnifiedNotificationService::sendIncomingCallNotification(
                        $host, $recipient, $meeting->uuid, 'video'
                    );
                }
            }

            return $meeting;
        } catch (\Throwable $e) {
            Log::error('CallService::startGroupMeeting failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Accept a meeting invite.
     */
    public function acceptInvite(Meeting $meeting, User $user): bool
    {
        $participant = MeetingParticipant::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) return false;

        $participant->update([
            'invite_status' => 'accepted',
        ]);

        // If meeting was ringing and someone accepted, mark as live
        if ($meeting->status === 'ringing' || $meeting->status === 'pending') {
            $meeting->markLive();
        }

        return true;
    }

    /**
     * Decline a meeting invite.
     */
    public function declineInvite(Meeting $meeting, User $user): bool
    {
        $participant = MeetingParticipant::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) return false;

        $participant->update(['invite_status' => 'declined']);

        // If all participants declined, mark meeting as declined
        $pending = MeetingParticipant::where('meeting_id', $meeting->id)
            ->whereIn('invite_status', ['pending', 'accepted'])
            ->where('role', '!=', 'host')
            ->count();

        if ($pending === 0 && $meeting->isRinging()) {
            $meeting->update(['status' => 'declined']);
        }

        return true;
    }

    /**
     * Mark participant as joined (connected to Twilio room).
     */
    public function markJoined(Meeting $meeting, User $user): void
    {
        $participant = MeetingParticipant::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant) {
            $participant->markJoined();
        }

        // Ensure meeting is live
        if (!$meeting->isLive()) {
            $meeting->markLive();
        }
    }

    /**
     * Mark participant as left.
     */
    public function markLeft(Meeting $meeting, User $user): void
    {
        $participant = MeetingParticipant::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant) {
            $participant->markLeft();
        }

        // If no participants left, end meeting
        $joined = $meeting->activeParticipants()->count();
        if ($joined === 0 && $meeting->isLive()) {
            $meeting->markEnded();
        }
    }

    /**
     * End meeting entirely (host action).
     */
    public function endMeeting(Meeting $meeting, ?User $endedBy = null): void
    {
        $meeting->markEnded();
    }

    /**
     * Get pending invite for user.
     */
    public function getPendingInvite(int $userId): ?MeetingParticipant
    {
        try {
            return MeetingParticipant::where('user_id', $userId)
                ->where('invite_status', 'pending')
                ->whereHas('meeting', fn ($q) => $q->whereIn('status', ['ringing', 'pending', 'live']))
                ->with(['meeting.host'])
                ->latest()
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Clean up stale calls — ringing for over 60 seconds.
     */
    public function cleanupStaleCalls(int $staleSeconds = 60): int
    {
        $count = 0;
        try {
            $stale = Meeting::where('status', 'ringing')
                ->where('created_at', '<', now()->subSeconds($staleSeconds))
                ->get();

            foreach ($stale as $meeting) {
                $meeting->update(['status' => 'missed']);

                // Send missed call push to each pending participant
                $pendingParticipants = $meeting->participants()
                    ->where('invite_status', 'pending')
                    ->get();

                $caller = $meeting->host;
                foreach ($pendingParticipants as $p) {
                    $recipient = User::find($p->user_id);
                    if ($caller && $recipient) {
                        UnifiedNotificationService::sendMissedCallNotification($caller, $recipient);
                    }
                    $p->update(['invite_status' => 'missed']);
                }

                $count++;
            }
        } catch (\Throwable $e) {
            Log::warning('CallService::cleanupStaleCalls failed', ['error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * Generate Twilio video token for a user joining a meeting.
     */
    public function generateTwilioToken(Meeting $meeting, User $user): ?array
    {
        $sid = config('services.twilio.account_sid', config('twilio.account_sid'));
        $apiKey = config('services.twilio.api_key', config('twilio.api_key_sid'));
        $apiSecret = config('services.twilio.api_secret', config('twilio.api_key_secret'));

        if (!$sid || !$apiKey || !$apiSecret) {
            Log::error('Twilio credentials missing for token generation');
            return null;
        }

        try {
            $identity = 'user-' . $user->id;
            $roomName = $meeting->provider_room_name;

            // Use Twilio SDK if available, otherwise build JWT manually
            if (class_exists(\Twilio\Jwt\AccessToken::class)) {
                $token = new \Twilio\Jwt\AccessToken($sid, $apiKey, $apiSecret, 3600, $identity);
                $videoGrant = new \Twilio\Jwt\Grants\VideoGrant();
                $videoGrant->setRoom($roomName);
                $token->addGrant($videoGrant);

                return [
                    'token' => $token->toJWT(),
                    'identity' => $identity,
                    'room_name' => $roomName,
                ];
            }

            // Manual JWT construction (no Twilio SDK dependency)
            $header = ['typ' => 'JWT', 'alg' => 'HS256', 'cty' => 'twilio-fpa;v=1'];
            $now = time();
            $payload = [
                'jti' => $apiKey . '-' . $now,
                'iss' => $apiKey,
                'sub' => $sid,
                'exp' => $now + 3600,
                'grants' => [
                    'identity' => $identity,
                    'video' => ['room' => $roomName],
                ],
            ];

            $base64UrlEncode = function (string $data): string {
                return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            };

            $headerEncoded = $base64UrlEncode(json_encode($header));
            $payloadEncoded = $base64UrlEncode(json_encode($payload));
            $signature = $base64UrlEncode(hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $apiSecret, true));

            return [
                'token' => "$headerEncoded.$payloadEncoded.$signature",
                'identity' => $identity,
                'room_name' => $roomName,
            ];
        } catch (\Throwable $e) {
            Log::error('Twilio token generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
