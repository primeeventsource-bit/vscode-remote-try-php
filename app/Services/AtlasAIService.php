<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AtlasAIService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        // Check database first, then fall back to config/env
        $this->apiKey = $this->loadKeyFromDB() ?: (config('services.anthropic.key') ?? '');
        $this->model = config('services.anthropic.model') ?? 'claude-haiku-4-5-20251001';
    }

    protected function loadKeyFromDB(): ?string
    {
        try {
            $row = \Illuminate\Support\Facades\DB::table('crm_settings')
                ->where('key', 'anthropic.api_key')->first();
            if ($row && !empty($row->value)) {
                return decrypt(json_decode($row->value, true));
            }
        } catch (\Throwable $e) {}
        return null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function parseText(string $text, string $county, string $state): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Anthropic API key not configured.');
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
                'system' => 'Extract timeshare deed records. GRANTEE=buyer. GRANTOR=seller/resort. Skip mortgages/liens. Return ONLY JSON array: [{"grantee":"","grantor":"","date":"","address":"","instrument":"","type":""}]',
                'messages' => [
                    ['role' => 'user', 'content' => "Extract deed records from {$county} County, {$state}:\n\n{$text}"],
                ],
            ]);

        return $this->extractJson($response);
    }

    public function parsePDF(string $base64Data, string $county, string $state): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Anthropic API key not configured.');
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
                'system' => 'Extract deed records from PDF. GRANTEE=buyer, GRANTOR=seller. Return ONLY JSON array: [{"grantee":"","grantor":"","date":"","address":"","instrument":"","type":""}]',
                'messages' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64Data]],
                        ['type' => 'text', 'text' => "Extract deed records from {$county} County, {$state}. JSON only."],
                    ]],
                ],
            ]);

        return $this->extractJson($response);
    }

    protected function extractJson($response): array
    {
        if ($response->failed()) {
            $err = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('AI API error: ' . substr($err, 0, 300));
        }

        $data = $response->json();
        $raw = collect($data['content'] ?? [])->where('type', 'text')->pluck('text')->join('');
        $clean = trim(str_replace(['```json', '```'], '', $raw));

        $result = json_decode($clean, true);
        if (is_array($result)) return array_is_list($result) ? $result : [$result];

        if (preg_match('/(\[[\s\S]*\])/', $clean, $m)) {
            $result = json_decode($m[1], true);
            if (is_array($result)) return $result;
        }

        if (preg_match('/(\{[\s\S]*\})/', $clean, $m)) {
            $result = json_decode($m[1], true);
            if (is_array($result)) return [$result];
        }

        $lower = strtolower($raw);
        if (str_contains($lower, 'no deed') || str_contains($lower, 'no records') || str_contains($lower, 'could not find') || str_contains($lower, 'does not contain')) {
            return [];
        }

        throw new \RuntimeException('AI returned invalid response: ' . substr($raw, 0, 200));
    }
}
