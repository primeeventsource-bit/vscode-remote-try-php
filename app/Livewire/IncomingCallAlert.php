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
        if (!$p) return;

        $meeting = $p->meeting;
        if ($meeting) {
            app(\App\Services\Chat\CallService::class)->declineInvite($meeting, auth()->user());
        } else {
            $p->update(['invite_status' => 'declined']);
        }
    }

    public function render()
    {
        $oldInvite = null;
        $meetingInvite = null;

        // Check unified meetings system via CallService
        $meetingInvite = app(\App\Services\Chat\CallService::class)->getPendingInvite(auth()->id());

        // Check legacy video_room_participants (backward compat)
        if (!$meetingInvite) {
            try {
                $oldInvite = VideoRoomParticipant::where('user_id', auth()->id())
                    ->where('invite_status', 'pending')
                    ->whereHas('room', fn ($q) => $q->whereIn('status', ['waiting', 'active']))
                    ->with(['room.creator'])
                    ->latest()
                    ->first();
            } catch (\Throwable $e) {}
        }

        return view('livewire.incoming-call-alert', [
            'invite'        => $oldInvite,
            'meetingInvite' => $meetingInvite,
        ]);
    }
}
