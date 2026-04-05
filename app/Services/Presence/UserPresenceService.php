<?php

namespace App\Services\Presence;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPresenceService
{
    private static function ready(): bool
    {
        return Schema::hasColumn('users', 'presence_status');
    }

    private static function getSetting(string $key, mixed $default): mixed
    {
        try {
            $raw = DB::table('crm_settings')->where('key', $key)->value('value');
            if ($raw !== null) return json_decode($raw, true) ?? $default;
        } catch (\Throwable $e) {}
        return $default;
    }

    private static function idleThreshold(): int
    {
        return (int) self::getSetting('presence.idle_threshold_seconds', 300);
    }

    private static function offlineTimeout(): int
    {
        return (int) self::getSetting('presence.offline_timeout_seconds', 90);
    }

    public static function markHeartbeat(User $user, bool $isActive = true): void
    {
        if (!self::ready()) return;

        $now = now();
        $data = ['last_seen_at' => $now];

        if ($isActive) {
            $data['last_active_at'] = $now;
            $data['idle_since_at'] = null;
            $data['presence_status'] = 'online';
        } else {
            self::recomputePresence($user);
            return;
        }

        $user->update($data);
    }

    public static function markActive(User $user): void
    {
        if (!self::ready()) return;
        $user->update([
            'presence_status' => 'online',
            'last_active_at' => now(),
            'last_seen_at' => now(),
            'idle_since_at' => null,
        ]);
    }

    public static function markOffline(User $user): void
    {
        if (!self::ready()) return;
        $user->update([
            'presence_status' => 'offline',
            'idle_since_at' => null,
        ]);
    }

    public static function recomputePresence(User $user): void
    {
        if (!self::ready()) return;

        $now = now();
        $lastSeen = $user->last_seen_at;
        $lastActive = $user->last_active_at;

        if (!$lastSeen || $now->diffInSeconds($lastSeen) > self::offlineTimeout()) {
            if ($user->presence_status !== 'offline') {
                $user->update(['presence_status' => 'offline', 'idle_since_at' => null]);
            }
            return;
        }

        if ($lastActive && $now->diffInSeconds($lastActive) > self::idleThreshold()) {
            $idleSince = $user->idle_since_at ?? $lastActive;
            if ($user->presence_status !== 'idle') {
                $user->update(['presence_status' => 'idle', 'idle_since_at' => $idleSince]);
            }
            return;
        }

        if ($user->presence_status !== 'online') {
            $user->update(['presence_status' => 'online', 'idle_since_at' => null]);
        }
    }

    public static function recomputeStaleUsers(): int
    {
        if (!self::ready()) return 0;

        $threshold = now()->subSeconds(self::offlineTimeout());

        return DB::table('users')
            ->whereIn('presence_status', ['online', 'idle'])
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)
                  ->orWhereNull('last_seen_at');
            })
            ->update([
                'presence_status' => 'offline',
                'idle_since_at' => null,
            ]);
    }
}
