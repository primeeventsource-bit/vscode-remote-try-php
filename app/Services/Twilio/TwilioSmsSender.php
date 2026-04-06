<?php

namespace App\Services\Twilio;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends SMS via Twilio REST API (no SDK required).
 * Uses Account SID + Auth Token basic auth.
 */
class TwilioSmsSender
{
    /**
     * Send an SMS message via Twilio.
     * Returns ['success' => bool, 'sid' => string|null, 'status' => string, 'error' => string|null]
     */
    public static function send(string $to, string $body, ?string $from = null, ?string $statusCallbackUrl = null): array
    {
        $accountSid = config('twilio.account_sid');
        $authToken  = config('twilio.auth_token');

        if (! $accountSid || ! $authToken) {
            return ['success' => false, 'sid' => null, 'status' => 'failed', 'error' => 'Twilio credentials not configured'];
        }

        if (! config('twilio.sms_enabled', true)) {
            return ['success' => false, 'sid' => null, 'status' => 'failed', 'error' => 'SMS sending is disabled'];
        }

        $from = $from ?? config('twilio.from_number');
        if (! $from) {
            return ['success' => false, 'sid' => null, 'status' => 'failed', 'error' => 'No from number configured'];
        }

        try {
            $payload = [
                'To'   => $to,
                'From' => $from,
                'Body' => $body,
            ];

            if ($statusCallbackUrl) {
                $payload['StatusCallback'] = $statusCallbackUrl;
            }

            // Use Messaging Service SID if configured
            $msgServiceSid = config('twilio.messaging_service_sid');
            if ($msgServiceSid) {
                $payload['MessagingServiceSid'] = $msgServiceSid;
                unset($payload['From']); // Twilio uses MessagingServiceSid instead
            }

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(15)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'sid'     => $data['sid'] ?? null,
                    'status'  => $data['status'] ?? 'queued',
                    'error'   => null,
                ];
            }

            $errorBody = $response->json();
            $errorMsg = $errorBody['message'] ?? $response->body();
            $errorCode = $errorBody['code'] ?? $response->status();

            Log::error('Twilio SMS send failed', [
                'to' => $to, 'status' => $response->status(),
                'error' => $errorMsg, 'code' => $errorCode,
            ]);

            return [
                'success' => false,
                'sid'     => null,
                'status'  => 'failed',
                'error'   => "Twilio error {$errorCode}: {$errorMsg}",
            ];
        } catch (\Throwable $e) {
            Log::error('Twilio SMS exception', ['to' => $to, 'error' => $e->getMessage()]);
            return ['success' => false, 'sid' => null, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}
