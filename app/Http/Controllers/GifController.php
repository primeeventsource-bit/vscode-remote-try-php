<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GifProviderService;
use App\Services\GifUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        try {
            if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('trending_enabled', true)) {
                return response()->json(['message' => 'Trending GIFs are disabled.'], 403);
            }

            $validated = $request->validate([
                'cursor' => ['nullable', 'string', 'max:80'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
                'user_id' => ['nullable', 'integer'],
            ]);

            $options = $this->providerOptions($validated);
            $useCache = empty($validated['cursor']);
            $result = $useCache
                ? Cache::remember($this->cacheKey('trending', $options), now()->addMinutes(10), fn() => $this->gifProviderService->trending($options))
                : $this->gifProviderService->trending($options);

            return $this->gifResponse($result);
        } catch (Throwable $e) {
            Log::error('GIF trending failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Unable to load trending GIFs.', 'data' => [], 'meta' => ['next_cursor' => null]], 502);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('search_enabled', true)) {
                return response()->json(['message' => 'GIF search is disabled.'], 403);
            }

            $validated = $request->validate([
                'q' => ['required', 'string', 'min:2', 'max:120'],
                'cursor' => ['nullable', 'string', 'max:80'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
                'user_id' => ['nullable', 'integer'],
            ]);

            $result = $this->gifProviderService->search($validated['q'], $this->providerOptions($validated));
            return $this->gifResponse($result);
        } catch (Throwable $e) {
            Log::error('GIF search failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Unable to search GIFs.', 'data' => [], 'meta' => ['next_cursor' => null]], 502);
        }
    }

    public function movies(Request $request): JsonResponse
    {
        try {
            if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('movies_enabled', true)) {
                return response()->json(['message' => 'Movie GIFs are disabled.'], 403);
            }

            $validated = $request->validate([
                'q' => ['nullable', 'string', 'max:120'],
                'cursor' => ['nullable', 'string', 'max:80'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
                'user_id' => ['nullable', 'integer'],
            ]);

            $options = $this->providerOptions($validated);
            $cacheable = empty($validated['q']) && empty($validated['cursor']);
            $result = $cacheable
                ? Cache::remember($this->cacheKey('movies', $options), now()->addMinutes(15), fn() => $this->gifProviderService->movies($validated['q'] ?? null, $options))
                : $this->gifProviderService->movies($validated['q'] ?? null, $options);

            return $this->gifResponse($result);
        } catch (Throwable $e) {
            Log::error('GIF movies failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Unable to load movie GIFs.', 'data' => [], 'meta' => ['next_cursor' => null]], 502);
        }
    }

    public function recent(Request $request): JsonResponse
    {
        try {
            if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('recent_enabled', true)) {
                return response()->json(['message' => 'Recent GIFs are disabled.'], 403);
            }

            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['data' => [], 'meta' => ['next_cursor' => null, 'provider' => 'recent']]);
            }

            $limit = (int) ($request->input('limit', $this->gifSetting('results_limit', 24)));
            $items = $this->gifUsageService->recent($user, $limit)
                ->map(fn($gif) => [
                    'id' => $gif->gif_external_id,
                    'title' => $gif->gif_title,
                    'url' => $gif->gif_url,
                    'preview_url' => $gif->gif_preview_url,
                    'provider' => $gif->gif_provider,
                ])
                ->values();

            return response()->json(['data' => $items, 'meta' => ['next_cursor' => null, 'provider' => 'recent']]);
        } catch (Throwable $e) {
            Log::error('GIF recent failed', ['error' => $e->getMessage()]);
            return response()->json(['data' => [], 'meta' => ['next_cursor' => null]], 502);
        }
    }

    public function favorites(Request $request): JsonResponse
    {
        try {
            if (!$this->gifSetting('module_enabled', true) || !$this->gifSetting('favorites_enabled', true)) {
                return response()->json(['message' => 'GIF favorites are disabled.'], 403);
            }

            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['data' => [], 'meta' => ['next_cursor' => null, 'provider' => 'favorites']]);
            }

            $limit = (int) ($request->input('limit', $this->gifSetting('results_limit', 24)));
            $items = $this->gifUsageService->favorites($user, $limit)
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
        } catch (Throwable $e) {
            Log::error('GIF favorites failed', ['error' => $e->getMessage()]);
            return response()->json(['data' => [], 'meta' => ['next_cursor' => null]], 502);
        }
    }

    public function storeFavorite(Request $request): JsonResponse
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['message' => 'User required.'], 401);
            }

            $validated = $request->validate([
                'id' => ['required', 'string', 'max:100'],
                'title' => ['nullable', 'string', 'max:255'],
                'url' => ['required', 'url', 'max:2000'],
                'preview_url' => ['nullable', 'url', 'max:2000'],
                'provider' => ['required', 'string', 'max:30'],
            ]);

            $favorite = $this->gifUsageService->favorite($user, $validated);
            return response()->json(['data' => $favorite], 201);
        } catch (Throwable $e) {
            Log::error('GIF favorite store failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to save favorite.'], 500);
        }
    }

    public function destroyFavorite(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['message' => 'User required.'], 401);
            }

            $deleted = $this->gifUsageService->removeFavorite($user, $id);
            return response()->json(['deleted' => $deleted]);
        } catch (Throwable $e) {
            Log::error('GIF favorite delete failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to remove favorite.'], 500);
        }
    }

    private function providerOptions(array $validated): array
    {
        return [
            'provider' => $this->gifSetting('provider', config('services.gifs.provider', 'giphy')),
            'safe_search_enabled' => $this->gifSetting('safe_search_enabled', true),
            'limit' => min((int) ($validated['limit'] ?? 24), 50),
            'cursor' => $validated['cursor'] ?? null,
        ];
    }

    private function gifResponse(array $result): JsonResponse
    {
        return response()->json([
            'data' => $result['items'] ?? [],
            'meta' => [
                'next_cursor' => $result['next_cursor'] ?? null,
                'provider' => $result['provider'] ?? 'giphy',
            ],
        ]);
    }

    private function gifSetting(string $key, mixed $default): mixed
    {
        try {
            $row = DB::table('crm_settings')->where('key', 'gifs.' . $key)->value('value');
            return $row === null ? $default : json_decode($row, true);
        } catch (Throwable $e) {
            return $default;
        }
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
}
