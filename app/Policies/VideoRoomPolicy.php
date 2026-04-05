<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoRoom;

class VideoRoomPolicy
{
    public function createGroupCall(User $user): bool
    {
        return $user->hasRole('master_admin', 'admin');
    }

    public function join(User $user, VideoRoom $room): bool
    {
        if ($room->isEnded()) return false;
        return $room->canBeJoinedBy($user);
    }

    public function invite(User $user, VideoRoom $room): bool
    {
        return $user->id === $room->created_by || $user->hasRole('master_admin');
    }

    public function end(User $user, VideoRoom $room): bool
    {
        // Direct calls: either participant can end
        if ($room->isDirect()) {
            return $room->hasParticipant($user);
        }
        // Group calls: only creator or master admin
        return $user->id === $room->created_by || $user->hasRole('master_admin');
    }
}
