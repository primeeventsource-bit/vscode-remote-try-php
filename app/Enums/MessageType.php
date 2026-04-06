<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Gif = 'gif';
    case Image = 'image';
    case File = 'file';
    case System = 'system';
    case CallEvent = 'call_event';
}
