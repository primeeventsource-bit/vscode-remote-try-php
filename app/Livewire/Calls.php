<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Services\Chat\CallService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Unified Calls workspace — replaces separate VideoCall + Meetings pages.
 * Supports both voice and video via Twilio.
 */
#[Layout('components.layouts.app')]
#[Title('Prime Connect')]
class Calls extends Component
{
    public string $tab = 'active';       // active, past, create
    public string $callType = 'video';   // video, voice
    public string $meetingName = '';
    public array $invitedUserIds = [];
    public bool $showCreate = false;

    // Active call state
    public ?int $activeCallId = null;

    public function createCall(): void
    {
        $user = auth()->user();
        if (!$user) return;

        $callService = app(CallService::class);
        $participantIds = array_map('intval', $this->invitedUserIds);

        if (empty($participantIds)) return;

        $title = trim($this->meetingName) ?: ($this->callType === 'voice' ? 'Voice Call' : 'Video Call');

        if (count($participantIds) === 1) {
            // Direct call
            $meeting = $callService->startDirectCall($user, $participantIds[0]);
        } else {
            // Group call
            $meeting = $callService->startGroupMeeting($user, $participantIds, $title);
        }

        if ($meeting) {
            $this->redirect('/meeting/' . $meeting->uuid);
        }

        $this->showCreate = false;
        $this->meetingName = '';
        $this->invitedUserIds = [];
    }

    public function joinMeeting(int $meetingId): void
    {
        $meeting = Meeting::find($meetingId);
        if ($meeting && !$meeting->isEnded()) {
            $this->redirect('/meeting/' . $meeting->uuid);
        }
    }

    public function endMeeting(int $meetingId): void
    {
        $meeting = Meeting::find($meetingId);
        if (!$meeting) return;

        $user = auth()->user();
        $isHost = $meeting->host_user_id === $user->id;
        $isAdmin = $user->hasRole('master_admin', 'admin');

        if ($isHost || $isAdmin) {
            app(CallService::class)->endMeeting($meeting, $user);
        }
    }

    public function render()
    {
        $user = auth()->user();

        // Active calls/meetings
        $activeMeetings = Meeting::whereIn('status', ['pending', 'ringing', 'live'])
            ->orderByDesc('created_at')
            ->with(['host', 'participants.user'])
            ->get();

        // Past calls (last 50)
        $pastMeetings = Meeting::whereIn('status', ['ended', 'declined', 'missed', 'failed'])
            ->orderByDesc('ended_at')
            ->limit(50)
            ->with(['host'])
            ->get();

        // Pending invites for current user
        $pendingInvites = MeetingParticipant::where('user_id', $user->id)
            ->where('invite_status', 'pending')
            ->whereHas('meeting', fn ($q) => $q->whereIn('status', ['ringing', 'live', 'pending']))
            ->with(['meeting.host'])
            ->get();

        $allUsers = User::orderBy('name')->get();
        $isAdmin = $user->hasRole('master_admin', 'admin');

        return view('livewire.calls', compact(
            'activeMeetings', 'pastMeetings', 'pendingInvites', 'allUsers', 'isAdmin'
        ));
    }
}
