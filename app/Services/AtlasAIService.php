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
        $response = Http::timeout(45)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 4000,
                'system' => 'You extract timeshare deed records from raw text. GRANTEE=buyer(lead). GRANTOR=seller(resort). Skip mortgages/liens/satisfactions. Return ONLY a JSON array, no markdown: [{"grantee":"","grantor":"","date":"","address":"","instrument":"","type":""}]',
                'messages' => [
                    ['role' => 'user', 'content' => "Extract all deed transfer records from {$county} County, {$state}:\n\n{$text}"],
                ],
            ]);

        return $this->parseJsonResponse($response);
    }

    public function parsePDF(string $base64Data, string $county, string $state): array
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 4000,
                'system' => 'You extract timeshare deed records from PDF documents. Extract: GRANTEE=buyer, GRANTOR=seller/resort, recording date, property/unit, instrument number, deed type. Return ONLY JSON array: [{"grantee":"","grantor":"","date":"","address":"","instrument":"","type":""}]',
                'messages' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64Data]],
                        ['type' => 'text', 'text' => "This is a deed PDF from {$county} County, {$state}. Extract all grantee/grantor/date/property info."],
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
        $data = $response->json();
        $raw = collect($data['content'] ?? [])->pluck('text')->join('');
        $clean = trim(str_replace(['```json', '```'], '', $raw));
        $results = json_decode($clean, true);

        if (!is_array($results)) {
            throw new \RuntimeException('AI returned invalid JSON: ' . substr($clean, 0, 200));
        }

        return $results;
    }
}
