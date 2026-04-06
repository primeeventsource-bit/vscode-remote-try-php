<?php

namespace App\Services\Twilio;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;

/**
 * Generates Twilio Video access tokens for group room participation.
 * Uses API Key SID + API Secret to sign JWT — no Twilio SDK required.
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
        $accountSid = config('twilio.account_sid') ?? config('services.twilio.account_sid');
        $apiKeySid  = config('twilio.api_key_sid') ?? config('services.twilio.api_key_sid');
        $apiSecret  = config('twilio.api_key_secret') ?? config('services.twilio.api_key_secret');

        if (! $accountSid || ! $apiKeySid || ! $apiSecret) {
            Log::error('Twilio Video token generation failed: missing credentials');
            return null;
        }

        try {
            $now = time();

            // Build the JWT payload with Twilio Video grant
            $payload = [
                'jti'   => $apiKeySid . '-' . $now,
                'iss'   => $apiKeySid,
                'sub'   => $accountSid,
                'nbf'   => $now,
                'exp'   => $now + $ttl,
                'grants' => [
                    'identity' => $identity,
                    'video'    => [
                        'room' => $roomName,
                    ],
                ],
            ];

            // Build JWT header
            $header = [
                'typ' => 'JWT',
                'alg' => 'HS256',
                'cty' => 'twilio-fpa;v=1',
            ];

            return JWT::encode($payload, $apiSecret, 'HS256', $apiKeySid, $header);
        } catch (\Throwable $e) {
            Log::error('Twilio Video token generation error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
