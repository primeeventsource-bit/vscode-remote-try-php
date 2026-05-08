<?php

return [
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'twilio' => [
        // No defaults — production must supply credentials via env. Empty values
        // force a 503 at the call site rather than silently using a stale key.
        'account_sid'    => env('TWILIO_ACCOUNT_SID'),
        'api_key_sid'    => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),
    ],

    'vapid' => [
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'tracerfy' => [
        'key' => env('TRACERFY_API_KEY'),
        'base_url' => 'https://tracerfy.com/v1/api',
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    'gifs' => [
        'provider' => env('GIF_PROVIDER', 'giphy'),
        'timeout' => (int) env('GIF_API_TIMEOUT', 6),
        'giphy' => [
            'api_key' => env('GIPHY_API_KEY'),
        ],
        'tenor' => [
            'api_key' => env('TENOR_API_KEY'),
            'client_key' => env('TENOR_CLIENT_KEY', env('APP_NAME', 'prime-crm')),
        ],
    ],

];
