<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\VideoRoom;
use App\Models\VideoRoomInvite;
use App\Models\VideoRoomParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Meetings')]
class Meetings extends Component
{
    public string $tab = 'active';
    public bool $showCreate = false;
    public string $meetingName = '';
    public string $meetingType = 'instant';
    public array $invitedUserIds = [];

    public function createMeeting(): void
    {
        $user = auth()->user();
        if (! $user) return;

        $name = trim($this->meetingName) ?: $user->name . "'s Meeting";

        $room = VideoRoom::create([
            'uuid'       => (string) Str::uuid(),
            'name'       => $name,
            'room_name'  => 'meeting-' . Str::random(12),
            'type'       => 'group',
            'room_type'  => $this->meetingType,
            'provider'   => 'twilio',
            'status'     => 'waiting',
            'created_by' => $user->id,
        ]);

        // Add creator as host
        VideoRoomParticipant::create([
            'room_id'       => $room->id,
            'user_id'       => $user->id,
            'role'          => 'host',
            'invite_status' => 'accepted',
        ]);

        // Invite selected users
        foreach (array_unique(array_map('intval', $this->invitedUserIds)) as $uid) {
            if ($uid === $user->id) continue;

            VideoRoomParticipant::create([
                'room_id'       => $room->id,
                'user_id'       => $uid,
                'role'          => 'participant',
                'invite_status' => 'pending',
                'invited_by'    => $user->id,
            ]);

            try {
                VideoRoomInvite::create([
                    'video_room_id'      => $room->id,
                    'invited_user_id'    => $uid,
                    'invited_by_user_id' => $user->id,
                    'invite_type'        => 'direct',
                    'invite_status'      => 'pending',
                    'delivered_at'       => now(),
                ]);
            } catch (\Throwable $e) {}
        }

        // Log event
        try {
            DB::table('video_room_events')->insert([
                'video_room_id' => $room->id,
                'user_id'       => $user->id,
                'event_type'    => 'room_created',
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {}

        // Reset form
        $this->showCreate = false;
        $this->meetingName = '';
        $this->meetingType = 'instant';
        $this->invitedUserIds = [];

        // Redirect host to the meeting room
        $this->redirect(route('video-call', ['room' => $room->uuid]));
    }

    public function endMeeting(int $roomId): void
    {
        $room = VideoRoom::find($roomId);
        if (! $room) return;

        $user = auth()->user();
        if ($room->created_by !== $user->id && ! $user->hasRole('master_admin', 'admin')) return;

        $room->markEnded();

        try {
            DB::table('video_room_events')->insert([
                'video_room_id' => $room->id,
                'user_id'       => $user->id,
                'event_type'    => 'room_ended',
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {}
    }

    public function render()
    {
        $user = auth()->user();

        // Active meetings I'm in or invited to
        $activeMeetings = VideoRoom::where('type', 'group')
            ->whereIn('status', ['waiting', 'active'])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with(['creator', 'participants.user' => fn ($q) => $q->select('id', 'name', 'avatar', 'color')])
            ->orderByDesc('created_at')
            ->get();

        // Past meetings
        $pastMeetings = VideoRoom::where('type', 'group')
            ->where('status', 'ended')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with('creator')
            ->orderByDesc('ended_at')
            ->limit(20)
            ->get();

        // My pending invites
        $pendingInvites = VideoRoomParticipant::where('user_id', $user->id)
            ->where('invite_status', 'pending')
            ->whereHas('room', fn ($q) => $q->whereIn('status', ['waiting', 'active'])->where('type', 'group'))
            ->with(['room.creator'])
            ->get();

        $allUsers = User::select('id', 'name', 'role', 'avatar', 'color')->orderBy('name')->get();
        $isAdmin = $user->hasRole('master_admin', 'admin');

        return view('livewire.meetings', compact(
            'activeMeetings', 'pastMeetings', 'pendingInvites', 'allUsers', 'isAdmin'
        ));
    }
}
