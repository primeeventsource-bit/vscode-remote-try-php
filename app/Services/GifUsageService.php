<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFavoriteGif;
use App\Models\UserRecentGif;
use Illuminate\Support\Collection;

class GifUsageService
{
    public function recent(User $user, int $limit = 24): Collection
    {
        return UserRecentGif::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_used_at')
            ->limit(max(1, min(50, $limit)))
            ->get();
    }

    public function favorites(User $user, int $limit = 24): Collection
    {
        return UserFavoriteGif::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(max(1, min(50, $limit)))
            ->get();
    }

    public function recordRecent(User $user, array $gif): UserRecentGif
    {
        $record = UserRecentGif::query()->firstOrNew([
            'user_id' => $user->id,
            'gif_external_id' => (string) ($gif['id'] ?? ''),
            'gif_provider' => (string) ($gif['provider'] ?? ''),
        ]);

        $record->fill([
            'gif_url' => $gif['url'] ?? null,
            'gif_preview_url' => $gif['preview_url'] ?? null,
            'gif_title' => $gif['title'] ?? null,
            'used_count' => ((int) $record->used_count) + 1,
            'last_used_at' => now(),
        ]);

        $record->save();

        return $record;
    }

    public function favorite(User $user, array $gif): UserFavoriteGif
    {
        return UserFavoriteGif::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'gif_external_id' => (string) ($gif['id'] ?? ''),
                'gif_provider' => (string) ($gif['provider'] ?? ''),
            ],
            [
                'gif_url' => $gif['url'] ?? null,
                'gif_preview_url' => $gif['preview_url'] ?? null,
                'gif_title' => $gif['title'] ?? null,
            ]
        );
    }

    public function removeFavorite(User $user, int $id): bool
    {
        return (bool) UserFavoriteGif::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();
    }
}
