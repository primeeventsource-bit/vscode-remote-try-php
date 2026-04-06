<?php

namespace App\Services\Twilio;

use Illuminate\Support\Facades\Log;

/**
 * Generates Twilio Video access tokens for group room participation.
 * Uses API Key SID + API Secret to sign JWT — no external SDK required.
 *
 * Each token grants a specific user access to a specific room.
 * Tokens are short-lived (default 4 hours).
 */
class TwilioVideoTokenService
{
    /**
     * Generate a Twilio Video access token for a user + room.
     *
     * @param string $identity Unique participant identity (e.g., "user-42")
     * @param string $roomName The Twilio Video room name to grant access to
     * @param int $ttl Token lifetime in seconds (default 4 hours)
     * @return string|null JWT access token, or null on failure
     */
    public static function generateToken(string $identity, string $roomName, int $ttl = 14400): ?string
    {
        // Read from config first, fall back to env() directly for Azure App Service
        // where config:cache may run before env vars are injected
        $accountSid = config('twilio.account_sid') ?: config('services.twilio.account_sid') ?: env('TWILIO_ACCOUNT_SID');
        $apiKeySid  = config('twilio.api_key_sid') ?: config('services.twilio.api_key_sid') ?: env('TWILIO_API_KEY_SID');
        $apiSecret  = config('twilio.api_key_secret') ?: config('services.twilio.api_key_secret') ?: env('TWILIO_API_KEY_SECRET');

        if (!$accountSid || !$apiKeySid || !$apiSecret) {
            Log::error('Twilio Video token generation failed: missing credentials', [
                'account_sid_set' => !empty($accountSid),
                'api_key_set' => !empty($apiKeySid),
                'api_secret_set' => !empty($apiSecret),
            ]);
            return null;
        }

        try {
            $now = time();

            // JWT Header — Twilio requires cty: twilio-fpa;v=1
            $header = [
                'typ' => 'JWT',
                'alg' => 'HS256',
                'cty' => 'twilio-fpa;v=1',
            ];

            // JWT Payload with Twilio Video grant
            $payload = [
                'jti'    => $apiKeySid . '-' . $now,
                'iss'    => $apiKeySid,
                'sub'    => $accountSid,
                'nbf'    => $now,
                'exp'    => $now + $ttl,
                'grants' => [
                    'identity' => $identity,
                    'video'    => ['room' => $roomName],
                ],
            ];

            // Try firebase/php-jwt if available
            if (class_exists(\Firebase\JWT\JWT::class)) {
                return \Firebase\JWT\JWT::encode($payload, $apiSecret, 'HS256', $apiKeySid, $header);
            }

            // Manual JWT construction (zero dependencies)
            return self::buildJwt($header, $payload, $apiSecret, $apiKeySid);
        } catch (\Throwable $e) {
            Log::error('Twilio Video token generation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Build a JWT manually without any external library.
     */
    private static function buildJwt(array $header, array $payload, string $secret, string $kid): string
    {
        // Add kid to header
        $header['kid'] = $kid;

        $base64UrlEncode = function (string $data): string {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        };

        $headerEncoded = $base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = $base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = $base64UrlEncode(
            hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true)
        );

        return "{$headerEncoded}.{$payloadEncoded}.{$signature}";
    }
}
