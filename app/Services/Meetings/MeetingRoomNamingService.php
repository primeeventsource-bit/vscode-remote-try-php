<?php

namespace App\Services\Meetings;

/**
 * Canonical room naming — NEVER let frontend invent room names.
 * This prevents the "I only see myself" bug caused by users joining different rooms.
 */
class MeetingRoomNamingService
{
    public static function forDirectCall(int $userA, int $userB): string
    {
        $ids = [$userA, $userB];
        sort($ids);
        return 'dm-' . $ids[0] . '-' . $ids[1];
    }

    public static function forGroupChat(int $chatId): string
    {
        return 'group-' . $chatId;
    }

    public static function forMeeting(string $uuid): string
    {
        return 'meeting-' . $uuid;
    }
}
