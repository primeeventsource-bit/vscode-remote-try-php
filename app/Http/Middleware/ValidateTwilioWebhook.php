<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Twilio webhook request signatures.
 * Uses X-Twilio-Signature header with auth token HMAC.
 */
class ValidateTwilioWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('twilio.validate_webhook_signature', true)) {
            return $next($request);
        }

        $authToken = config('twilio.auth_token');
        if (! $authToken) {
            Log::warning('Twilio webhook validation skipped: no auth token configured');
            return $next($request);
        }

        $signature = $request->header('X-Twilio-Signature');
        if (! $signature) {
            Log::warning('Twilio webhook rejected: missing X-Twilio-Signature');
            return response('Forbidden', 403);
        }

        $url = $request->fullUrl();
        $params = $request->post() ?? [];

        // Build the validation string: URL + sorted POST params
        ksort($params);
        $dataString = $url;
        foreach ($params as $key => $value) {
            $dataString .= $key . $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $dataString, $authToken, true));

        if (! hash_equals($expected, $signature)) {
            Log::warning('Twilio webhook rejected: invalid signature', ['url' => $url]);
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
