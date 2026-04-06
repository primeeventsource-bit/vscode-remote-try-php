<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates ephemeral TURN/ICE credentials via Twilio Network Traversal Service.
 * Credentials are short-lived (default TTL: 3600s) and safe to pass to the browser.
 * The API Key Secret NEVER leaves the server.
 */
class TwilioIceService
{
    /**
     * Get ephemeral ICE servers from Twilio.
     * Returns array of iceServers ready for RTCPeerConnection.
     */
    public static function getIceServers(int $ttl = 3600): array
    {
        $accountSid = config('services.twilio.account_sid') ?: env('TWILIO_ACCOUNT_SID');
        $apiKeySid  = config('services.twilio.api_key_sid') ?: env('TWILIO_API_KEY_SID');
        $apiSecret  = config('services.twilio.api_key_secret') ?: env('TWILIO_API_KEY_SECRET');

        if (! $accountSid || ! $apiKeySid || ! $apiSecret) {
            // Fallback to free STUN only if Twilio not configured
            return [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302'],
            ];
        }

        try {
            $response = Http::withBasicAuth($apiKeySid, $apiSecret)
                ->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Tokens.json", [
                    'Ttl' => $ttl,
                ]);

            if ($response->failed()) {
                Log::error('Twilio ICE token request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return self::fallbackServers();
            }

            $data = $response->json();
            $servers = $data['ice_servers'] ?? [];

            if (empty($servers)) {
                return self::fallbackServers();
            }

            // Map Twilio response to WebRTC iceServers format
            return collect($servers)->map(function ($server) {
                $entry = ['urls' => $server['url'] ?? $server['urls'] ?? ''];
                if (! empty($server['username'])) $entry['username'] = $server['username'];
                if (! empty($server['credential'])) $entry['credential'] = $server['credential'];
                return $entry;
            })->toArray();
        } catch (\Throwable $e) {
            Log::error('Twilio ICE service error', ['error' => $e->getMessage()]);
            return self::fallbackServers();
        }
    }

    private static function fallbackServers(): array
    {
        return [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
        ];
    }
}
