<?php

namespace App\Services\VideoCall;

use App\Models\User;
use App\Models\VideoCallLog;
use App\Models\VideoRoom;
use App\Models\VideoRoomParticipant;
use Illuminate\Support\Facades\DB;

class VideoRoomService
{
    public static function createGroupRoom(User $creator, array $participantIds, ?string $name = null): VideoRoom
    {
        return DB::transaction(function () use ($creator, $participantIds, $name) {
            $room = VideoRoom::create([
                'name' => $name ?: $creator->name . "'s Group Call",
                'created_by' => $creator->id,
                'type' => 'group',
                'status' => 'waiting',
            ]);

            // Add creator as host
            VideoRoomParticipant::create([
                'room_id' => $room->id,
                'user_id' => $creator->id,
                'invited_by' => $creator->id,
                'role' => 'host',
                'invite_status' => 'accepted',
            ]);

            // Add selected participants
            self::inviteParticipants($room, $participantIds, $creator);

            VideoCallLog::create([
                'room_id' => $room->id,
                'user_id' => $creator->id,
                'event_type' => 'created',
            ]);

            return $room;
        });
    }

    public static function inviteParticipants(VideoRoom $room, array $userIds, User $inviter): void
    {
        $existing = $room->participants()->pluck('user_id')->toArray();

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId === $inviter->id) continue;
            if (in_array($userId, $existing)) continue;

            $user = User::find($userId);
            if (!$user || $user->status === 'suspended' || $user->status === 'disabled') continue;

            VideoRoomParticipant::create([
                'room_id' => $room->id,
                'user_id' => $userId,
                'invited_by' => $inviter->id,
                'role' => 'participant',
                'invite_status' => 'pending',
            ]);

            VideoCallLog::create([
                'room_id' => $room->id,
                'user_id' => $userId,
                'event_type' => 'invited',
                'meta' => ['invited_by' => $inviter->id],
            ]);
        }
    }

    public static function joinRoom(VideoRoom $room, User $user): void
    {
        $participant = $room->participants()->where('user_id', $user->id)->first();
        if (!$participant) return;

        $participant->markJoined();
        $room->markStarted();

        VideoCallLog::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'event_type' => 'joined',
        ]);
    }

    public static function declineInvite(VideoRoom $room, User $user): void
    {
        $participant = $room->participants()->where('user_id', $user->id)->first();
        if (!$participant) return;

        $participant->markDeclined();

        VideoCallLog::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'event_type' => 'declined',
        ]);
    }

    public static function leaveRoom(VideoRoom $room, User $user): void
    {
        $participant = $room->participants()->where('user_id', $user->id)->first();
        if (!$participant) return;

        $participant->markLeft();

        VideoCallLog::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'event_type' => 'left',
        ]);
    }

    public static function endRoom(VideoRoom $room, User $endedBy): void
    {
        $room->markEnded();

        VideoCallLog::create([
            'room_id' => $room->id,
            'user_id' => $endedBy->id,
            'event_type' => 'ended',
        ]);
    }

    /**
     * Get all eligible agents for the picker, with optional search.
     */
    public static function searchableAgents(?string $search = null, array $excludeIds = []): \Illuminate\Support\Collection
    {
        $query = User::whereNotIn('id', $excludeIds)
            ->whereNotIn('status', ['suspended', 'disabled'])
            ->orderBy('name');

        if ($search) {
            $s = $search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('role', 'like', "%{$s}%")
                  ->orWhere('username', 'like', "%{$s}%");
            });
        }

        return $query->get(['id', 'name', 'email', 'role', 'avatar', 'avatar_path', 'avatar_emoji', 'color', 'status']);
    }
}
