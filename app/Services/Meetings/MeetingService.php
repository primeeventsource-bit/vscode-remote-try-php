<?php

namespace App\Services\Meetings;

use App\Models\Meeting;
use App\Models\MeetingEvent;
use App\Models\MeetingParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MeetingService
{
    /**
     * Start a direct video call from DM bubble chat.
     * Creates or reuses an active meeting between two users.
     */
    public static function startDirectCall(User $caller, int $otherUserId, ?int $chatId = null): ?Meeting
    {
        $roomName = MeetingRoomNamingService::forDirectCall($caller->id, $otherUserId);

        // Reuse active meeting if exists
        $existing = Meeting::where('provider_room_name', $roomName)
            ->whereIn('status', ['pending', 'ringing', 'live'])
            ->first();

        if ($existing) return $existing;

        return DB::transaction(function () use ($caller, $otherUserId, $chatId, $roomName) {
            $meeting = Meeting::create([
                'type'                => 'direct',
                'source_type'         => 'bubble_dm',
                'source_id'           => $chatId,
                'provider'            => 'twilio',
                'provider_room_name'  => $roomName,
                'title'               => 'Direct Call',
                'host_user_id'        => $caller->id,
                'status'              => 'ringing',
                'max_participants'    => 2,
            ]);

            // Caller = host, auto-accepted
            MeetingParticipant::create([
                'meeting_id'          => $meeting->id,
                'user_id'             => $caller->id,
                'role'                => 'host',
                'invite_status'       => 'accepted',
                'attendance_status'   => 'not_joined',
                'participant_identity' => 'user-' . $caller->id,
            ]);

            // Other user = pending invite
            MeetingParticipant::create([
                'meeting_id'          => $meeting->id,
                'user_id'             => $otherUserId,
                'role'                => 'participant',
                'invite_status'       => 'pending',
                'attendance_status'   => 'not_joined',
                'participant_identity' => 'user-' . $otherUserId,
            ]);

            MeetingEvent::create([
                'meeting_id' => $meeting->id,
                'user_id'    => $caller->id,
                'event_type' => 'meeting_created',
                'created_at' => now(),
            ]);

            return $meeting;
        });
    }

    /**
     * Start a group meeting from group chat or meetings module.
     */
    public static function startGroupMeeting(User $host, array $participantUserIds, ?string $title = null, ?int $chatId = null): ?Meeting
    {
        $roomName = $chatId
            ? MeetingRoomNamingService::forGroupChat($chatId)
            : MeetingRoomNamingService::forMeeting((string) \Illuminate\Support\Str::uuid());

        return DB::transaction(function () use ($host, $participantUserIds, $title, $chatId, $roomName) {
            $meeting = Meeting::create([
                'type'                => 'group',
                'source_type'         => $chatId ? 'group_chat' : 'meetings_module',
                'source_id'           => $chatId,
                'provider'            => 'twilio',
                'provider_room_name'  => $roomName,
                'title'               => $title ?: $host->name . "'s Meeting",
                'host_user_id'        => $host->id,
                'status'              => 'ringing',
            ]);

            // Host
            MeetingParticipant::create([
                'meeting_id'          => $meeting->id,
                'user_id'             => $host->id,
                'role'                => 'host',
                'invite_status'       => 'accepted',
                'participant_identity' => 'user-' . $host->id,
            ]);

            // Invited participants
            foreach (array_unique($participantUserIds) as $uid) {
                if ($uid == $host->id) continue;
                MeetingParticipant::create([
                    'meeting_id'          => $meeting->id,
                    'user_id'             => $uid,
                    'role'                => 'participant',
                    'invite_status'       => 'pending',
                    'participant_identity' => 'user-' . $uid,
                ]);
            }

            MeetingEvent::create([
                'meeting_id' => $meeting->id,
                'user_id'    => $host->id,
                'event_type' => 'meeting_created',
                'created_at' => now(),
            ]);

            return $meeting;
        });
    }

    /**
     * Accept a meeting invite.
     */
    public static function accept(Meeting $meeting, User $user): void
    {
        $participant = $meeting->participants()->where('user_id', $user->id)->first();
        if ($participant) {
            $participant->update(['invite_status' => 'accepted']);
        }

        if ($meeting->status === 'ringing') {
            $meeting->markLive();
        }
    }

    /**
     * Mark participant as joined.
     */
    public static function joinRoom(Meeting $meeting, User $user): void
    {
        $participant = $meeting->participants()->where('user_id', $user->id)->first();
        if ($participant) {
            $participant->markJoined();
        }

        if (! $meeting->isLive()) {
            $meeting->markLive();
        }

        MeetingEvent::create([
            'meeting_id' => $meeting->id,
            'user_id'    => $user->id,
            'event_type' => 'participant_joined',
            'created_at' => now(),
        ]);
    }

    /**
     * Leave a meeting (individual).
     */
    public static function leave(Meeting $meeting, User $user): void
    {
        $participant = $meeting->participants()->where('user_id', $user->id)->first();
        if ($participant) {
            $participant->markLeft();
        }

        MeetingEvent::create([
            'meeting_id' => $meeting->id,
            'user_id'    => $user->id,
            'event_type' => 'participant_left',
            'created_at' => now(),
        ]);

        // Auto-end if no participants remain
        if ($meeting->activeParticipants()->count() === 0) {
            $meeting->markEnded();
        }
    }

    /**
     * End meeting for all (host action).
     */
    public static function endMeeting(Meeting $meeting, User $user): void
    {
        $meeting->markEnded();

        MeetingEvent::create([
            'meeting_id' => $meeting->id,
            'user_id'    => $user->id,
            'event_type' => 'meeting_ended',
            'created_at' => now(),
        ]);
    }

    /**
     * Decline a meeting invite.
     */
    public static function decline(Meeting $meeting, User $user): void
    {
        $participant = $meeting->participants()->where('user_id', $user->id)->first();
        if ($participant) {
            $participant->update(['invite_status' => 'declined']);
        }

        // If direct call and callee declines, mark meeting declined
        if ($meeting->type === 'direct') {
            $meeting->update(['status' => 'declined']);
        }
    }
}
