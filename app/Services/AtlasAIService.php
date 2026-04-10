<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AtlasAIService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key') ?? '';
        $this->model = config('services.anthropic.model') ?? 'claude-sonnet-4-20250514';
    }

    public function parseText(string $text, string $county, string $state): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY not configured. Set it in Laravel Cloud environment variables.');
        }

        $response = Http::timeout(45)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 4000,
                'system' => "You extract timeshare deed records from raw text. GRANTEE=buyer(lead). GRANTOR=seller(resort). Skip mortgages/liens/satisfactions.\n\nIMPORTANT: Return ONLY a raw JSON array with NO extra text, NO markdown fences. Example:\n[{\"grantee\":\"JOHN DOE\",\"grantor\":\"WESTGATE RESORTS\",\"date\":\"2025-04-01\",\"address\":\"123 Main St\",\"instrument\":\"2025-001234\",\"type\":\"Warranty Deed\"}]",
                'messages' => [
                    ['role' => 'user', 'content' => "Extract all deed transfer records from {$county} County, {$state}:\n\n{$text}"],
                ],
            ]);

        return $this->parseJsonResponse($response);
    }

    public function parsePDF(string $base64Data, string $county, string $state): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY not configured. Set it in Laravel Cloud environment variables.');
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 4000,
                'system' => "You extract timeshare deed records from PDF documents. Extract: GRANTEE=buyer, GRANTOR=seller/resort, recording date, property/unit, instrument number, deed type.\n\nIMPORTANT: Return ONLY a raw JSON array with NO extra text, NO markdown fences. Example:\n[{\"grantee\":\"JOHN DOE\",\"grantor\":\"WESTGATE RESORTS\",\"date\":\"2025-04-01\",\"address\":\"Unit 123\",\"instrument\":\"2025-001234\",\"type\":\"Warranty Deed\"}]",
                'messages' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64Data]],
                        ['type' => 'text', 'text' => "This is a deed PDF from {$county} County, {$state}. Extract all grantee/grantor/date/property info. Return only the JSON array."],
                    ]],
                ],
            ]);

        return $this->parseJsonResponse($response);
    }

    public function lookupPhone(string $name, string $county, string $state, string $address = ''): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1000,
                'tools' => [
                    ['type' => 'web_search_20250305', 'name' => 'web_search'],
                ],
                'system' => 'You are a skip tracing assistant. Search public records and people-search directories for phone numbers. Return ONLY JSON: {"phones":["xxx-xxx-xxxx"],"confidence":"high|medium|low|none","sources":["source1"],"notes":""}',
                'messages' => [
                    ['role' => 'user', 'content' => "Find phone numbers for: {$name}\nLocation: {$county} County, {$state}\nProperty: {$address}\n\nReturn top 3 most likely phone numbers."],
                ],
            ]);

        $data = $response->json();
        $raw = collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->join('');

        try {
            return json_decode(
                trim(str_replace(['```json', '```'], '', $raw)),
                true
            ) ?: ['phones' => [], 'confidence' => 'none', 'sources' => [], 'notes' => 'Parse failed'];
        } catch (\Exception $e) {
            preg_match_all('/\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $raw, $matches);
            return [
                'phones' => array_slice($matches[0] ?? [], 0, 3),
                'confidence' => 'low',
                'sources' => ['ai-search'],
                'notes' => 'Extracted from search results',
            ];
        }
    }

    protected function parseJsonResponse($response): array
    {
        if ($response->failed()) {
            $err = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('Anthropic API error: ' . substr($err, 0, 300));
        }

        $data = $response->json();
        $raw = collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->join('');

        // Strip markdown code fences
        $clean = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $clean, $m)) {
            $clean = trim($m[1]);
        }

        // Try to find JSON array [...]
        if (($start = strpos($clean, '[')) !== false) {
            $sub = substr($clean, $start);
            $depth = 0;
            for ($i = 0; $i < strlen($sub); $i++) {
                if ($sub[$i] === '[') $depth++;
                elseif ($sub[$i] === ']') $depth--;
                if ($depth === 0) {
                    $sub = substr($sub, 0, $i + 1);
                    break;
                }
            }
            $results = json_decode($sub, true);
            if (is_array($results)) {
                return $results;
            }
        }

        // Try to find JSON object {...} and wrap in array
        if (($start = strpos($clean, '{')) !== false) {
            $sub = substr($clean, $start);
            $depth = 0;
            for ($i = 0; $i < strlen($sub); $i++) {
                if ($sub[$i] === '{') $depth++;
                elseif ($sub[$i] === '}') $depth--;
                if ($depth === 0) {
                    $sub = substr($sub, 0, $i + 1);
                    break;
                }
            }
            $results = json_decode($sub, true);
            if (is_array($results)) {
                return [$results]; // wrap single object in array
            }
        }

        // Try decoding the whole cleaned string
        $results = json_decode($clean, true);
        if (is_array($results)) {
            return array_is_list($results) ? $results : [$results];
        }

        // If Claude says no records found, return empty
        $lower = strtolower($raw);
        if (str_contains($lower, 'no deed') || str_contains($lower, 'no records') || str_contains($lower, 'could not find') || str_contains($lower, 'does not contain') || str_contains($lower, 'no timeshare')) {
            return [];
        }

        throw new \RuntimeException('AI response could not be parsed: ' . substr($raw, 0, 300));
    }
}
