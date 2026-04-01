<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GifProviderService
{
    public function trending(array $options = []): array
    {
        return match ($this->provider($options)) {
            'tenor' => $this->tenorTrending($options),
            default => $this->giphyTrending($options),
        };
    }

    public function search(string $query, array $options = []): array
    {
        return match ($this->provider($options)) {
            'tenor' => $this->tenorSearch($query, $options),
            default => $this->giphySearch($query, $options),
        };
    }

    public function movies(?string $query, array $options = []): array
    {
        $term = trim((string) $query);
        if ($term === '') {
            $term = 'movie reactions';
        } elseif (!preg_match('/movie|film|cinema|scene|hollywood/i', $term)) {
            $term .= ' movie';
        }

        return $this->search($term, $options);
    }

    private function provider(array $options): string
    {
        return strtolower((string) ($options['provider'] ?? config('services.gifs.provider', 'giphy')));
    }

    private function timeout(): int
    {
        return max(3, (int) config('services.gifs.timeout', 6));
    }

    private function safeEnabled(array $options): bool
    {
        return (bool) ($options['safe_search_enabled'] ?? true);
    }

    private function limit(array $options): int
    {
        return max(1, min(50, (int) ($options['limit'] ?? 24)));
    }

    private function giphyApiKey(): string
    {
        // Try config first, then env directly, then hardcoded fallback
        $key = (string) config('services.gifs.giphy.api_key');
        if ($key === '') {
            $key = (string) env('GIPHY_API_KEY');
        }
        if ($key === '') {
            // Fallback to the key provided by the user
            $key = 'BdTfn7gBYzhPwesTx4GitLl7Q8buMpUh';
        }
        return $key;
    }

    private function giphyTrending(array $options): array
    {
        $apiKey = $this->giphyApiKey();

        $offset = (int) ($options['cursor'] ?? 0);
        $limit = $this->limit($options);

        $response = Http::timeout($this->timeout())
            ->get('https://api.giphy.com/v1/gifs/trending', [
                'api_key' => $apiKey,
                'limit' => $limit,
                'offset' => $offset,
                'rating' => $this->safeEnabled($options) ? 'pg' : 'pg-13',
            ])
            ->throw()
            ->json();

        $items = array_map(fn(array $gif) => $this->normalizeGiphyGif($gif), $response['data'] ?? []);
        $pagination = $response['pagination'] ?? [];
        $next = null;
        if (($pagination['offset'] ?? 0) + ($pagination['count'] ?? 0) < ($pagination['total_count'] ?? 0)) {
            $next = (string) (($pagination['offset'] ?? 0) + ($pagination['count'] ?? 0));
        }

        return ['items' => $items, 'next_cursor' => $next, 'provider' => 'giphy'];
    }

    private function giphySearch(string $query, array $options): array
    {
        $apiKey = $this->giphyApiKey();

        $offset = (int) ($options['cursor'] ?? 0);
        $limit = $this->limit($options);

        $response = Http::timeout($this->timeout())
            ->get('https://api.giphy.com/v1/gifs/search', [
                'api_key' => $apiKey,
                'q' => $query,
                'limit' => $limit,
                'offset' => $offset,
                'rating' => $this->safeEnabled($options) ? 'pg' : 'pg-13',
                'lang' => 'en',
            ])
            ->throw()
            ->json();

        $items = array_map(fn(array $gif) => $this->normalizeGiphyGif($gif), $response['data'] ?? []);
        $pagination = $response['pagination'] ?? [];
        $next = null;
        if (($pagination['offset'] ?? 0) + ($pagination['count'] ?? 0) < ($pagination['total_count'] ?? 0)) {
            $next = (string) (($pagination['offset'] ?? 0) + ($pagination['count'] ?? 0));
        }

        return ['items' => $items, 'next_cursor' => $next, 'provider' => 'giphy'];
    }

    private function tenorTrending(array $options): array
    {
        return $this->tenorRequest('featured', null, $options);
    }

    private function tenorSearch(string $query, array $options): array
    {
        return $this->tenorRequest('search', $query, $options);
    }

    private function tenorRequest(string $endpoint, ?string $query, array $options): array
    {
        $apiKey = (string) config('services.gifs.tenor.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Tenor API key is not configured.');
        }

        $params = [
            'key' => $apiKey,
            'client_key' => (string) config('services.gifs.tenor.client_key', 'prime-crm'),
            'limit' => $this->limit($options),
            'media_filter' => 'tinygif,gif,mediumgif',
            'contentfilter' => $this->safeEnabled($options) ? 'medium' : 'off',
        ];

        if ($query !== null) {
            $params['q'] = $query;
        }

        if (!empty($options['cursor'])) {
            $params['pos'] = $options['cursor'];
        }

        $response = Http::timeout($this->timeout())
            ->get('https://tenor.googleapis.com/v2/' . $endpoint, $params)
            ->throw()
            ->json();

        $items = array_map(fn(array $gif) => $this->normalizeTenorGif($gif), $response['results'] ?? []);

        return [
            'items' => $items,
            'next_cursor' => $response['next'] ?? null,
            'provider' => 'tenor',
        ];
    }

    private function normalizeGiphyGif(array $gif): array
    {
        $original = Arr::get($gif, 'images.original.url');
        $preview = Arr::get($gif, 'images.fixed_width_small.url', Arr::get($gif, 'images.preview_gif.url', $original));

        return [
            'id' => (string) ($gif['id'] ?? ''),
            'title' => (string) ($gif['title'] ?? 'GIF'),
            'url' => (string) $original,
            'preview_url' => (string) $preview,
            'provider' => 'giphy',
            'width' => (int) Arr::get($gif, 'images.fixed_width_small.width', 0),
            'height' => (int) Arr::get($gif, 'images.fixed_width_small.height', 0),
        ];
    }

    private function normalizeTenorGif(array $gif): array
    {
        $formats = $gif['media_formats'] ?? [];
        $full = $formats['gif'] ?? $formats['mediumgif'] ?? [];
        $preview = $formats['tinygif'] ?? $formats['mediumgif'] ?? $full;

        return [
            'id' => (string) ($gif['id'] ?? ''),
            'title' => (string) ($gif['content_description'] ?? $gif['title'] ?? 'GIF'),
            'url' => (string) ($full['url'] ?? ''),
            'preview_url' => (string) ($preview['url'] ?? $full['url'] ?? ''),
            'provider' => 'tenor',
            'width' => (int) (($preview['dims'][0] ?? $full['dims'][0] ?? 0)),
            'height' => (int) (($preview['dims'][1] ?? $full['dims'][1] ?? 0)),
        ];
    }
}
