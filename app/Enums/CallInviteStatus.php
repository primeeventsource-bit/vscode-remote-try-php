<?php

namespace App\Enums;

enum CallInviteStatus: string
{
    case Invited = 'invited';
    case Ringing = 'ringing';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Missed = 'missed';
    case Joined = 'joined';
    case Left = 'left';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return in_array($this, [self::Invited, self::Ringing, self::Accepted, self::Joined]);
    }
}
