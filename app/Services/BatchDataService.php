<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BatchDataService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = $this->loadKeyFromDB() ?: (config('services.batchdata.key') ?? '');
        $this->baseUrl = config('services.batchdata.base_url') ?? 'https://api.batchdata.com/api/v1';
    }

    protected function loadKeyFromDB(): ?string
    {
        try {
            $row = \Illuminate\Support\Facades\DB::table('crm_settings')
                ->where('key', 'batchdata.api_key')->first();
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

    public function skipTrace(array $leads): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('BatchData API key not configured. Add it in Atlas Settings.');
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/property/skip-trace", [
                'requests' => collect($leads)->map(fn($l) => [
                    'firstName' => $l['firstName'] ?? '',
                    'lastName'  => $l['lastName'] ?? '',
                    'address'   => $l['address'] ?? '',
                    'city'      => $l['city'] ?? '',
                    'state'     => $l['state'] ?? '',
                    'postalCode'=> $l['postalCode'] ?? '',
                ])->toArray(),
            ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? $response->json('message') ?? 'Unknown error';
            throw new \RuntimeException("BatchData API error: {$error}");
        }

        return $response->json('results') ?? $response->json('data') ?? [];
    }

    public function normalizeResult(array $result): array
    {
        $phones = [];
        $phoneData = $result['phones'] ?? $result['phoneNumbers'] ?? $result['contacts']['phones'] ?? [];

        foreach ($phoneData as $p) {
            $number = $p['phoneNumber'] ?? $p['number'] ?? $p['phone'] ?? '';
            if (strlen(preg_replace('/\D/', '', $number)) >= 10) {
                $dnc = $p['dnc'] ?? $p['dncFlag'] ?? false;
                if ($dnc) continue; // Skip DNC numbers

                $phones[] = [
                    'number' => $this->formatPhone($number),
                    'type'   => $p['phoneType'] ?? $p['type'] ?? $p['lineType'] ?? 'unknown',
                ];
            }
        }

        $emails = [];
        $emailData = $result['emails'] ?? $result['emailAddresses'] ?? $result['contacts']['emails'] ?? [];
        foreach ($emailData as $e) {
            $addr = $e['emailAddress'] ?? $e['email'] ?? $e['address'] ?? '';
            if (str_contains($addr, '@')) $emails[] = $addr;
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
}
