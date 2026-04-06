<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\VideoRoomParticipant;
use Livewire\Component;

/**
 * Global incoming call/meeting notification — mounted in app layout.
 * Polls every 3 seconds. Checks BOTH old video_room_participants
 * AND new meeting_participants tables for pending invites.
 */
class IncomingCallAlert extends Component
{
    public function declineOldInvite(int $participantId): void
    {
        VideoRoomParticipant::where('id', $participantId)
            ->where('user_id', auth()->id())
            ->update(['invite_status' => 'declined']);
    }

    public function declineMeetingInvite(int $participantId): void
    {
        $p = MeetingParticipant::where('id', $participantId)->where('user_id', auth()->id())->first();
        if ($p) {
            $p->update(['invite_status' => 'declined']);
            // If direct call, mark meeting declined too
            $meeting = $p->meeting;
            if ($meeting && $meeting->type === 'direct') {
                $meeting->update(['status' => 'declined']);
            }
        }
    }

    public function render()
    {
        $oldInvite = null;
        $meetingInvite = null;

        // Check old video_room_participants
        try {
            $oldInvite = VideoRoomParticipant::where('user_id', auth()->id())
                ->where('invite_status', 'pending')
                ->whereHas('room', fn ($q) => $q->whereIn('status', ['waiting', 'active']))
                ->with(['room.creator'])
                ->latest()
                ->first();
        } catch (\Throwable $e) {}

        // Check new meeting_participants
        try {
            $meetingInvite = MeetingParticipant::where('user_id', auth()->id())
                ->where('invite_status', 'pending')
                ->whereHas('meeting', fn ($q) => $q->whereIn('status', ['ringing', 'live']))
                ->with(['meeting.host'])
                ->latest()
                ->first();
        } catch (\Throwable $e) {}

        return view('livewire.incoming-call-alert', [
            'invite'        => $oldInvite,
            'meetingInvite' => $meetingInvite,
        ]);
    }
}
