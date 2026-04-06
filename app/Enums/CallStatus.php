<?php

namespace App\Enums;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case Accepted = 'accepted';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Ended = 'ended';
    case Failed = 'failed';
    case Missed = 'missed';
    case Declined = 'declined';

    public function isActive(): bool
    {
        return in_array($this, [self::Ringing, self::Accepted, self::Connecting, self::Connected]);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ended, self::Failed, self::Missed, self::Declined]);
    }
}
