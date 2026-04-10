<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TracerfyService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://tracerfy.com/v1/api';

    public function __construct()
    {
        $this->apiKey = $this->loadKeyFromDB() ?: (config('services.tracerfy.key') ?? '');
    }

    protected function loadKeyFromDB(): ?string
    {
        try {
            $row = DB::table('crm_settings')->where('key', 'tracerfy.api_key')->first();
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

    /**
     * Instant single-lead lookup (synchronous, 5 credits/hit, 0 on miss)
     */
    public function lookup(array $lead): array
    {
        $payload = [
            'address' => $lead['address'] ?? '',
            'city' => $lead['city'] ?? '',
            'state' => $lead['state'] ?? '',
            'zip' => $lead['zip'] ?? '',
            'find_owner' => false,
            'first_name' => $lead['firstName'] ?? '',
            'last_name' => $lead['lastName'] ?? '',
        ];

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/trace/lookup/", $payload);

        if (!$response->successful()) {
            $error = $response->json('message') ?? $response->json('error') ?? $response->body();
            throw new \RuntimeException("Tracerfy API error ({$response->status()}): " . substr($error, 0, 500));
        }

        return $response->json();
    }

    /**
     * Batch trace via CSV upload (async — returns queue_id)
     */
    public function batchTrace(array $leads, string $traceType = 'normal'): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Tracerfy API key not configured. Add it in Atlas Settings.');
        }

        // Filter to leads with at least a name
        $validLeads = array_values(array_filter($leads, fn($l) => !empty(trim(($l['firstName'] ?? '') . ' ' . ($l['lastName'] ?? '')))));
        if (empty($validLeads)) {
            throw new \RuntimeException('No valid leads to trace. Leads need at least a name.');
        }

        // Build JSON data array (alternative to CSV upload per Tracerfy API)
        $jsonRows = [];
        foreach ($validLeads as $lead) {
            $jsonRows[] = [
                'first_name' => $lead['firstName'] ?? '',
                'last_name' => $lead['lastName'] ?? '',
                'address' => $lead['address'] ?? '',
                'city' => $lead['city'] ?? '',
                'state' => $lead['state'] ?? '',
                'zip' => $lead['zip'] ?? '',
                'mail_address' => $lead['address'] ?? '',
                'mail_city' => $lead['city'] ?? '',
                'mail_state' => $lead['state'] ?? '',
                'mailing_zip' => $lead['zip'] ?? '',
            ];
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])
            ->asMultipart()
            ->post("{$this->baseUrl}/trace/", [
                ['name' => 'json_data', 'contents' => json_encode($jsonRows)],
                ['name' => 'address_column', 'contents' => 'address'],
                ['name' => 'city_column', 'contents' => 'city'],
                ['name' => 'state_column', 'contents' => 'state'],
                ['name' => 'zip_column', 'contents' => 'zip'],
                ['name' => 'first_name_column', 'contents' => 'first_name'],
                ['name' => 'last_name_column', 'contents' => 'last_name'],
                ['name' => 'mail_address_column', 'contents' => 'mail_address'],
                ['name' => 'mail_city_column', 'contents' => 'mail_city'],
                ['name' => 'mail_state_column', 'contents' => 'mail_state'],
                ['name' => 'mailing_zip_column', 'contents' => 'mailing_zip'],
                ['name' => 'trace_type', 'contents' => $traceType],
            ]);

        if (!$response->successful()) {
            $error = $response->json('message') ?? $response->json('non_field_errors.0') ?? $response->json('error') ?? $response->body();
            throw new \RuntimeException("Tracerfy API error ({$response->status()}): " . substr($error, 0, 500));
        }

        return $response->json();
    }

    /**
     * Check queue status
     */
    public function getQueue(int $queueId): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
            ->get("{$this->baseUrl}/queue/{$queueId}");

        if (!$response->successful()) {
            $error = $response->json('message') ?? $response->body();
            throw new \RuntimeException("Tracerfy queue error ({$response->status()}): " . substr($error, 0, 500));
        }

        return $response->json();
    }

    /**
     * Get all queues (to check pending status)
     */
    public function getQueues(): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
            ->get("{$this->baseUrl}/queues/");

        if (!$response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get account analytics (balance, etc.)
     */
    public function getAnalytics(): array
    {
        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
            ->get("{$this->baseUrl}/analytics/");

        if (!$response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Normalize a single result record from queue results
     */
    public function normalizeResult(array $result): array
    {
        $phones = [];

        // Primary phone
        if (!empty($result['primary_phone'])) {
            $phones[] = [
                'number' => $this->formatPhone($result['primary_phone']),
                'type' => $result['primary_phone_type'] ?? 'unknown',
            ];
        }

        // Mobile phones 1-5
        for ($i = 1; $i <= 5; $i++) {
            $num = $result["mobile_{$i}"] ?? '';
            if (!empty($num) && strlen(preg_replace('/\D/', '', $num)) >= 10) {
                $phones[] = [
                    'number' => $this->formatPhone($num),
                    'type' => 'Mobile',
                ];
            }
        }

        // Landlines 1-3
        for ($i = 1; $i <= 3; $i++) {
            $num = $result["landline_{$i}"] ?? '';
            if (!empty($num) && strlen(preg_replace('/\D/', '', $num)) >= 10) {
                $phones[] = [
                    'number' => $this->formatPhone($num),
                    'type' => 'Landline',
                ];
            }
        }

        // Deduplicate by number
        $seen = [];
        $phones = array_filter($phones, function ($p) use (&$seen) {
            $digits = preg_replace('/\D/', '', $p['number']);
            if (in_array($digits, $seen)) return false;
            $seen[] = $digits;
            return true;
        });

        // Emails 1-5
        $emails = [];
        for ($i = 1; $i <= 5; $i++) {
            $email = $result["email_{$i}"] ?? '';
            if (!empty($email) && str_contains($email, '@')) {
                $emails[] = $email;
            }
        }

        return [
            'phones' => array_values(array_slice($phones, 0, 5)),
            'emails' => array_slice($emails, 0, 3),
            'confidence' => count($phones) >= 3 ? 'high' : (count($phones) > 0 ? 'medium' : 'none'),
            'firstName' => $result['first_name'] ?? '',
            'lastName' => $result['last_name'] ?? '',
        ];
    }

    /**
     * Normalize an instant lookup response
     */
    public function normalizeLookup(array $response): array
    {
        if (!($response['hit'] ?? false) || empty($response['persons'])) {
            return ['phones' => [], 'emails' => [], 'confidence' => 'none'];
        }

        $phones = [];
        $emails = [];

        foreach ($response['persons'] as $person) {
            foreach ($person['phones'] ?? [] as $p) {
                if (!empty($p['number']) && !($p['dnc'] ?? false)) {
                    $phones[] = [
                        'number' => $this->formatPhone($p['number']),
                        'type' => $p['type'] ?? 'unknown',
                    ];
                }
            }
            foreach ($person['emails'] ?? [] as $e) {
                $addr = $e['email'] ?? '';
                if (!empty($addr) && str_contains($addr, '@')) {
                    $emails[] = $addr;
                }
            }
        }

        return [
            'phones' => array_slice($phones, 0, 5),
            'emails' => array_slice($emails, 0, 3),
            'confidence' => count($phones) >= 3 ? 'high' : (count($phones) > 0 ? 'medium' : 'none'),
        ];
    }

    protected function formatPhone(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);
        $digits = preg_replace('/^1/', '', $digits);
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
        }
        return $number;
    }

    protected function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
