<?php

namespace App\Livewire;

use App\Models\VideoRoomParticipant;
use Livewire\Component;

/**
 * Global incoming call notification — mounted in app layout.
 * Polls every 3 seconds for pending video/audio call invites.
 * Shows toast with Answer/Decline on ANY CRM page.
 */
class IncomingCallAlert extends Component
{
    public function declineInvite(int $participantId): void
    {
        $participant = VideoRoomParticipant::where('id', $participantId)
            ->where('user_id', auth()->id())
            ->first();

        if ($participant) {
            $participant->update(['invite_status' => 'declined']);
        }
    }

    public function render()
    {
        $invite = null;

        try {
            $invite = VideoRoomParticipant::where('user_id', auth()->id())
                ->where('invite_status', 'pending')
                ->whereHas('room', fn ($q) => $q->whereIn('status', ['waiting', 'active']))
                ->with(['room.creator'])
                ->latest()
                ->first();
        } catch (\Throwable $e) {
            // Table may not exist yet
        }

        return view('livewire.incoming-call-alert', ['invite' => $invite]);
    }
}
