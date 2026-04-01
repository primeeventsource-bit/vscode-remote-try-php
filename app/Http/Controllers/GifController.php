<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GifProviderService;
use App\Services\GifUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class GifController extends Controller
{
    public function __construct(
        private readonly GifProviderService $gifProviderService,
        private readonly GifUsageService $gifUsageService,
    ) {
    }

    public function trending(Request $request): JsonResponse
    {
        if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('trending_enabled', true)) {
            return response()->json(['message' => 'Trending GIFs are disabled.'], 403);
        }

        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->authorizeGifAccess($request, false);
        $options = $this->providerOptions($validated);

        try {
            $useCache = empty($validated['cursor']);
            $result = $useCache
                ? Cache::remember($this->cacheKey('trending', $options), now()->addMinutes(10), fn() => $this->gifProviderService->trending($options))
                : $this->gifProviderService->trending($options);

            return $this->gifResponse($result);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Unable to load trending GIFs right now.'], 502);
        }
    }

    public function search(Request $request): JsonResponse
    {
        if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('search_enabled', true)) {
            return response()->json(['message' => 'GIF search is disabled.'], 403);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'cursor' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->authorizeGifAccess($request, false);

        try {
            $result = $this->gifProviderService->search($validated['q'], $this->providerOptions($validated));
            return $this->gifResponse($result);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Unable to search GIFs right now.'], 502);
        }
    }

    public function movies(Request $request): JsonResponse
    {
        if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('movies_enabled', true)) {
            return response()->json(['message' => 'Movie GIFs are disabled.'], 403);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'cursor' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->authorizeGifAccess($request, false);
        $options = $this->providerOptions($validated);
        $cacheable = empty($validated['q']) && empty($validated['cursor']);

        try {
            $result = $cacheable
                ? Cache::remember($this->cacheKey('movies', $options), now()->addMinutes(15), fn() => $this->gifProviderService->movies($validated['q'] ?? null, $options))
                : $this->gifProviderService->movies($validated['q'] ?? null, $options);

            return $this->gifResponse($result);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Unable to load movie GIFs right now.'], 502);
        }
    }

    public function recent(Request $request): JsonResponse
    {
        if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('recent_enabled', true)) {
            return response()->json(['message' => 'Recent GIFs are disabled.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'A valid user is required.'], 401);
        }

        $this->authorizeGifAccess($request, false);

        $items = $this->gifUsageService->recent($user, (int) ($validated['limit'] ?? $this->gifSetting('results_limit', 24)))
            ->map(fn($gif) => [
                'id' => $gif->gif_external_id,
                'title' => $gif->gif_title,
                'url' => $gif->gif_url,
                'preview_url' => $gif->gif_preview_url,
                'provider' => $gif->gif_provider,
            ])
            ->values();

        return response()->json(['data' => $items, 'meta' => ['next_cursor' => null, 'provider' => 'recent']]);
    }

    public function favorites(Request $request): JsonResponse
    {
        if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('favorites_enabled', true)) {
            return response()->json(['message' => 'GIF favorites are disabled.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'A valid user is required.'], 401);
        }

        $this->authorizeGifAccess($request, false);

        $items = $this->gifUsageService->favorites($user, (int) ($validated['limit'] ?? $this->gifSetting('results_limit', 24)))
            ->map(fn($gif) => [
                'favorite_id' => $gif->id,
                'id' => $gif->gif_external_id,
                'title' => $gif->gif_title,
                'url' => $gif->gif_url,
                'preview_url' => $gif->gif_preview_url,
                'provider' => $gif->gif_provider,
            ])
            ->values();

        return response()->json(['data' => $items, 'meta' => ['next_cursor' => null, 'provider' => 'favorites']]);
    }

    public function storeFavorite(Request $request): JsonResponse
    {
        if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('favorites_enabled', true)) {
            return response()->json(['message' => 'GIF favorites are disabled.'], 403);
        }

        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'A valid user is required.'], 401);
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2000'],
            'preview_url' => ['nullable', 'url', 'max:2000'],
            'provider' => ['required', 'string', 'max:30'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->authorizeGifAccess($request, false);

        $favorite = $this->gifUsageService->favorite($user, $validated);
        return response()->json(['data' => $favorite], 201);
    }

    public function destroyFavorite(Request $request, int $id): JsonResponse
    {
        if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('favorites_enabled', true)) {
            return response()->json(['message' => 'GIF favorites are disabled.'], 403);
        }

        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'A valid user is required.'], 401);
        }

        $this->authorizeGifAccess($request, false);

        $deleted = $this->gifUsageService->removeFavorite($user, $id);
        return response()->json(['deleted' => $deleted]);
    }

    private function providerOptions(array $validated): array
    {
        return [
            'provider' => $this->gifSetting('provider', config('services.gifs.provider', 'giphy')),
            'safe_search_enabled' => $this->gifSetting('safe_search_enabled', true),
            'limit' => min((int) ($validated['limit'] ?? $this->gifSetting('results_limit', 24)), (int) $this->gifSetting('results_limit', 24)),
            'cursor' => $validated['cursor'] ?? null,
        ];
    }

    private function gifResponse(array $result): JsonResponse
    {
        return response()->json([
            'data' => $result['items'] ?? [],
            'meta' => [
                'next_cursor' => $result['next_cursor'] ?? null,
                'provider' => $result['provider'] ?? $this->gifSetting('provider', config('services.gifs.provider', 'giphy')),
            ],
        ]);
    }

    private function gifSetting(string $key, mixed $default): mixed
    {
        $row = DB::table('crm_settings')->where('key', 'gifs.' . $key)->value('value');
        return $row === null ? $default : json_decode($row, true);
    }

    private function cacheKey(string $segment, array $options): string
    {
        return 'gifs:' . $segment . ':' . md5(json_encode($options));
    }

    private function resolveUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $userId = $request->integer('user_id');
        return $userId ? User::find($userId) : null;
    }

    private function authorizeGifAccess(Request $request, bool $manage): void
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return;
        }

        if ($user->hasRole('master_admin')) {
            return;
        }

        if ($manage && !$user->hasPerm('manage_gif_settings')) {
            abort(403, 'You do not have permission to manage GIF settings.');
        }

        if (!$manage && !$user->hasPerm('view_gif_picker') && !$user->hasPerm('view_chat')) {
            abort(403, 'You do not have permission to use the GIF picker.');
        }
    }
}
