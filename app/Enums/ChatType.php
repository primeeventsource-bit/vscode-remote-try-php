<?php

namespace App\Enums;

enum ChatType: string
{
    case Direct = 'direct';
    case Group = 'group';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct Message',
            self::Group => 'Group Chat',
            self::Meeting => 'Meeting Chat',
        };
    }
}
