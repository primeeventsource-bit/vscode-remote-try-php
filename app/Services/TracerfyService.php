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

        // Build CSV content
        $csv = "first_name,last_name,address,city,state,zip,mail_address,mail_city,mail_state,mailing_zip\n";
        foreach ($leads as $lead) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $this->csvEscape($lead['firstName'] ?? ''),
                $this->csvEscape($lead['lastName'] ?? ''),
                $this->csvEscape($lead['address'] ?? ''),
                $this->csvEscape($lead['city'] ?? ''),
                $this->csvEscape($lead['state'] ?? ''),
                $this->csvEscape($lead['zip'] ?? ''),
                $this->csvEscape($lead['address'] ?? ''),
                $this->csvEscape($lead['city'] ?? ''),
                $this->csvEscape($lead['state'] ?? ''),
                $this->csvEscape($lead['zip'] ?? '')
            );
        }

        // Write temp CSV
        $tmpPath = tempnam(sys_get_temp_dir(), 'tracerfy_') . '.csv';
        file_put_contents($tmpPath, $csv);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                ])
                ->attach('csv_file', file_get_contents($tmpPath), 'leads.csv')
                ->post("{$this->baseUrl}/trace/", [
                    'address_column' => 'address',
                    'city_column' => 'city',
                    'state_column' => 'state',
                    'zip_column' => 'zip',
                    'first_name_column' => 'first_name',
                    'last_name_column' => 'last_name',
                    'mail_address_column' => 'mail_address',
                    'mail_city_column' => 'mail_city',
                    'mail_state_column' => 'mail_state',
                    'mailing_zip_column' => 'mailing_zip',
                    'trace_type' => $traceType,
                ]);
        } finally {
            @unlink($tmpPath);
        }

        if (!$response->successful()) {
            $error = $response->json('message') ?? $response->json('error') ?? $response->body();
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
