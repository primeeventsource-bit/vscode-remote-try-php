<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\VideoRoom;
use App\Models\VideoRoomParticipant;
use App\Models\VideoSignal;
use App\Services\VideoCall\VideoRoomService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Video Call')]
class VideoCall extends Component
{
    // Room state
    public ?string $roomUuid = null;
    public ?int $roomId = null;
    public string $roomStatus = 'none'; // none, waiting, active, ended

    // Modal state
    public bool $showCreateModal = false;
    public string $agentSearch = '';
    public array $selectedAgentIds = [];
    public string $callName = '';

    // In-room state
    public bool $micEnabled = true;
    public bool $cameraEnabled = true;

    public function mount(?string $room = null): void
    {
        if ($room) {
            $videoRoom = VideoRoom::where('uuid', $room)->first();
            if ($videoRoom && $videoRoom->canBeJoinedBy(auth()->user())) {
                $this->roomUuid = $videoRoom->uuid;
                $this->roomId = $videoRoom->id;
                $this->roomStatus = $videoRoom->status;
            }
        }
    }

    // ── Modal: Create Group Call ─────────────────────────────

    public function openCreateModal(): void
    {
        if (!Gate::allows('createGroupCall', VideoRoom::class)) return;
        $this->showCreateModal = true;
        $this->selectedAgentIds = [];
        $this->agentSearch = '';
        $this->callName = '';
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function toggleAgent(int $id): void
    {
        if (in_array($id, $this->selectedAgentIds)) {
            $this->selectedAgentIds = array_values(array_diff($this->selectedAgentIds, [$id]));
        } else {
            $this->selectedAgentIds[] = $id;
        }
    }

    public function removeAgent(int $id): void
    {
        $this->selectedAgentIds = array_values(array_diff($this->selectedAgentIds, [$id]));
    }

    public function addAllAgents(): void
    {
        $agents = VideoRoomService::searchableAgents(null, [auth()->id()]);
        $this->selectedAgentIds = $agents->pluck('id')->toArray();
    }

    public function clearAll(): void
    {
        $this->selectedAgentIds = [];
    }

    public function createGroupCall(): void
    {
        $user = auth()->user();
        if (!Gate::allows('createGroupCall', VideoRoom::class)) return;

        if (empty($this->selectedAgentIds)) {
            session()->flash('video_error', 'Select at least one agent.');
            return;
        }

        try {
            $room = VideoRoomService::createGroupRoom(
                $user,
                $this->selectedAgentIds,
                $this->callName ?: null
            );

            $this->showCreateModal = false;
            $this->roomUuid = $room->uuid;
            $this->roomId = $room->id;
            $this->roomStatus = 'waiting';

            // Auto-join as creator
            VideoRoomService::joinRoom($room, $user);
            $this->roomStatus = 'active';
        } catch (\Throwable $e) {
            report($e);
            session()->flash('video_error', 'Failed to create call.');
        }
    }

    // ── Room actions ────────────────────────────────────────

    public function joinRoom(): void
    {
        $room = $this->getRoom();
        if (!$room || !Gate::allows('join', $room)) return;

        VideoRoomService::joinRoom($room, auth()->user());
        $this->roomStatus = 'active';
    }

    public function declineInvite(): void
    {
        $room = $this->getRoom();
        if (!$room) return;

        VideoRoomService::declineInvite($room, auth()->user());
        $this->roomUuid = null;
        $this->roomId = null;
        $this->roomStatus = 'none';
    }

    public function leaveRoom(): void
    {
        $room = $this->getRoom();
        if (!$room) return;

        VideoRoomService::leaveRoom($room, auth()->user());
        $this->roomUuid = null;
        $this->roomId = null;
        $this->roomStatus = 'none';
    }

    public function endRoom(): void
    {
        $room = $this->getRoom();
        if (!$room || !Gate::allows('end', $room)) return;

        VideoRoomService::endRoom($room, auth()->user());
        $this->roomUuid = null;
        $this->roomId = null;
        $this->roomStatus = 'ended';
    }

    public function toggleMic(): void { $this->micEnabled = !$this->micEnabled; }
    public function toggleCamera(): void { $this->cameraEnabled = !$this->cameraEnabled; }

    // ── Signaling (database-backed, polled by JS) ───────────

    public function sendSignal(int $toUserId, string $type, string $payload): void
    {
        $room = $this->getRoom();
        if (!$room) return;

        // Validate target is in room
        if (!$room->participants()->where('user_id', $toUserId)->exists()) return;

        VideoSignal::create([
            'room_id' => $room->id,
            'from_user_id' => auth()->id(),
            'to_user_id' => $toUserId,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    public function pollSignals(): array
    {
        if (!$this->roomId) return [];

        $signals = VideoSignal::where('room_id', $this->roomId)
            ->where('to_user_id', auth()->id())
            ->where('consumed', false)
            ->orderBy('id')
            ->limit(20)
            ->get();

        $result = [];
        foreach ($signals as $sig) {
            $result[] = [
                'id' => $sig->id,
                'from' => $sig->from_user_id,
                'type' => $sig->type,
                'payload' => $sig->payload,
            ];
            $sig->update(['consumed' => true]);
        }

        return $result;
    }

    // ── Helpers ──────────────────────────────────────────────

    private function getRoom(): ?VideoRoom
    {
        if (!$this->roomId) return null;
        return VideoRoom::find($this->roomId);
    }

    // ── Render ───────────────────────────────────────────────

    public function render()
    {
        $user = auth()->user();
        $canCreate = Gate::allows('createGroupCall', VideoRoom::class);

        // Available agents for picker
        $availableAgents = collect();
        if ($this->showCreateModal) {
            $availableAgents = VideoRoomService::searchableAgents(
                $this->agentSearch ?: null,
                [auth()->id()]
            );
        }

        $selectedAgents = !empty($this->selectedAgentIds)
            ? User::whereIn('id', $this->selectedAgentIds)->get()
            : collect();

        // Room data
        $room = null;
        $participants = collect();
        if ($this->roomId) {
            $room = VideoRoom::with(['participants.user'])->find($this->roomId);
            if ($room) {
                $participants = $room->participants->map(fn($p) => [
                    'id' => $p->user_id,
                    'name' => $p->user->name ?? 'Unknown',
                    'avatar' => $p->user->avatar ?? '',
                    'avatar_path' => $p->user->avatar_path ?? null,
                    'avatar_emoji' => $p->user->avatar_emoji ?? null,
                    'color' => $p->user->color ?? '#6b7280',
                    'role' => $p->role,
                    'status' => $p->invite_status,
                    'joined' => (bool) $p->joined_at,
                    'left' => (bool) $p->left_at,
                    'mic' => $p->mic_enabled,
                    'camera' => $p->camera_enabled,
                ]);
                $this->roomStatus = $room->status;
            }
        }

        // Pending invites for current user
        $pendingInvite = null;
        try {
            $pendingInvite = VideoRoomParticipant::where('user_id', $user->id)
                ->where('invite_status', 'pending')
                ->whereHas('room', fn($q) => $q->whereIn('status', ['waiting', 'active']))
                ->with(['room.creator'])
                ->first();
        } catch (\Throwable $e) {}

        // Active rooms list (for admin)
        $activeRooms = collect();
        if ($canCreate) {
            try {
                $activeRooms = VideoRoom::whereIn('status', ['waiting', 'active'])
                    ->with('creator')
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get();
            } catch (\Throwable $e) {}
        }

        return view('livewire.video-call', compact(
            'canCreate', 'availableAgents', 'selectedAgents',
            'room', 'participants', 'pendingInvite', 'activeRooms'
        ));
    }
}
