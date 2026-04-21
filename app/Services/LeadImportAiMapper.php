<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stage-B header mapper for the lead import wizard. Called only for
 * spreadsheet headers that the deterministic synonym dictionary
 * (config/lead_import_mappings.php) didn't match.
 *
 * Returns ['field' => string|null, 'confidence' => 'high'|'medium'|'low'].
 * Field is one of the leads-table columns or null for "skip".
 *
 * Never throws. AI/network errors return ['field' => null, 'confidence' => 'low']
 * so the wizard treats the column as unmapped and the user can pick manually.
 */
class LeadImportAiMapper
{
    /** @var array<string> Lead columns the AI is allowed to suggest. */
    private const ALLOWED_FIELDS = [
        'resort', 'owner_name', 'phone1', 'phone2',
        'email', 'city', 'st', 'zip', 'resort_location',
    ];

    public function map(string $header, array $sampleValues = []): array
    {
        $apiKey = config('services.anthropic.api_key');
        if (!$apiKey) {
            return ['field' => null, 'confidence' => 'low', 'reason' => 'no_api_key'];
        }

        $samples = array_slice(array_filter(array_map('trim', $sampleValues), fn($v) => $v !== ''), 0, 3);
        $fieldList = implode(', ', self::ALLOWED_FIELDS);

        $prompt = "You map spreadsheet column headers to fields in a CRM 'leads' table.\n"
            . "Allowed fields: {$fieldList}\n"
            . "  - phone1 = primary phone, phone2 = secondary/alt phone\n"
            . "  - st = US/territory state code (e.g. FL, PR), city = city or county\n"
            . "  - resort = timeshare brand (HILTON, BLUEGREEN, etc.)\n"
            . "  - owner_name = a person's full name\n\n"
            . "Header: " . json_encode($header) . "\n"
            . "Sample values: " . json_encode($samples) . "\n\n"
            . 'Respond with strict JSON only. Format: {"field": "<one of the allowed fields, or null>", "confidence": "high|medium|low"}. No prose, no markdown.';

        try {
            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => 200,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (!$resp->successful()) {
                Log::warning('LeadImportAiMapper non-200', ['status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 300)]);
                return ['field' => null, 'confidence' => 'low', 'reason' => 'http_' . $resp->status()];
            }

            $text = $resp->json('content.0.text', '');
            // Strip ```json fences if the model added them despite instructions
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
            $parsed = json_decode($text, true);

            if (!is_array($parsed) || !array_key_exists('field', $parsed)) {
                return ['field' => null, 'confidence' => 'low', 'reason' => 'unparseable'];
            }

            $field = $parsed['field'];
            if ($field !== null && !in_array($field, self::ALLOWED_FIELDS, true)) {
                // Model hallucinated a field name we don't expose
                return ['field' => null, 'confidence' => 'low', 'reason' => 'unknown_field:' . substr((string)$field, 0, 30)];
            }

            $confidence = in_array($parsed['confidence'] ?? null, ['high', 'medium', 'low'], true)
                ? $parsed['confidence']
                : 'medium';

            return ['field' => $field, 'confidence' => $confidence];
        } catch (\Throwable $e) {
            Log::warning('LeadImportAiMapper threw', ['header' => $header, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            return ['field' => null, 'confidence' => 'low', 'reason' => 'exception'];
        }
    }
}
