<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Meeting')]
class MeetingRoom extends Component
{
    public ?string $uuid = null;
    public ?int $meetingId = null;
    public string $meetingStatus = 'loading';

    public function mount(?string $uuid = null): void
    {
        $this->uuid = $uuid;

        if ($uuid) {
            $meeting = Meeting::where('uuid', $uuid)->first();
            if ($meeting) {
                $this->meetingId = $meeting->id;
                $this->meetingStatus = $meeting->status;
            } else {
                $this->meetingStatus = 'not_found';
            }
        }
    }

    public function render()
    {
        $meeting = $this->meetingId ? Meeting::with(['participants.user', 'host'])->find($this->meetingId) : null;

        if ($meeting) {
            $this->meetingStatus = $meeting->status;
        }

        $participants = $meeting ? $meeting->participants->map(fn ($p) => [
            'id'     => $p->user_id,
            'name'   => $p->user?->name ?? 'Unknown',
            'avatar' => $p->user?->avatar ?? substr($p->user?->name ?? '?', 0, 2),
            'color'  => $p->user?->color ?? '#6b7280',
            'role'   => $p->role,
            'status' => $p->attendance_status,
            'joined' => (bool) $p->joined_at,
        ]) : collect();

        $isHost = $meeting && $meeting->host_user_id === auth()->id();

        return view('livewire.meeting-room', compact('meeting', 'participants', 'isHost'));
    }
}
