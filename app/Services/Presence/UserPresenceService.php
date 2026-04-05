<?php

namespace App\Services\Presence;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPresenceService
{
    private const IDLE_THRESHOLD = 300;    // 5 minutes
    private const OFFLINE_TIMEOUT = 90;    // 90 seconds no heartbeat

    private static function ready(): bool
    {
        return Schema::hasColumn('users', 'presence_status');
    }

    /**
     * Called every 30s from browser. Updates last_seen_at and recomputes state.
     */
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
            // Tab visible but user not interacting
            self::recomputePresence($user);
            return;
        }

        $user->update($data);
    }

    /**
     * Mark user as explicitly active (interaction event).
     */
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

    /**
     * Mark user offline (logout, session end).
     */
    public static function markOffline(User $user): void
    {
        if (!self::ready()) return;

        $user->update([
            'presence_status' => 'offline',
            'idle_since_at' => null,
        ]);
    }

    /**
     * Recompute presence based on timestamps.
     */
    public static function recomputePresence(User $user): void
    {
        if (!self::ready()) return;

        $now = now();
        $lastSeen = $user->last_seen_at;
        $lastActive = $user->last_active_at;

        // No heartbeat for too long → offline
        if (!$lastSeen || $now->diffInSeconds($lastSeen) > self::OFFLINE_TIMEOUT) {
            if ($user->presence_status !== 'offline') {
                $user->update(['presence_status' => 'offline', 'idle_since_at' => null]);
            }
            return;
        }

        // Heartbeat alive but inactive for too long → idle
        if ($lastActive && $now->diffInSeconds($lastActive) > self::IDLE_THRESHOLD) {
            $idleSince = $user->idle_since_at ?? $lastActive;
            if ($user->presence_status !== 'idle') {
                $user->update(['presence_status' => 'idle', 'idle_since_at' => $idleSince]);
            }
            return;
        }

        // Active and heartbeat alive → online
        if ($user->presence_status !== 'online') {
            $user->update(['presence_status' => 'online', 'idle_since_at' => null]);
        }
    }

    /**
     * Scan all users and mark stale ones offline. Run by scheduler.
     */
    public static function recomputeStaleUsers(): int
    {
        if (!self::ready()) return 0;

        $threshold = now()->subSeconds(self::OFFLINE_TIMEOUT);

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
