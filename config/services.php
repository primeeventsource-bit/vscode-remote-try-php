<?php

return [
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', 'AC144cda6c0249d7b13930171e0036e2d9'),
        'api_key_sid' => env('TWILIO_API_KEY_SID', 'SK7dddde7820f0606e555754d3adc16208'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET', '3pNFG87oVODXXNffxOXbIWF1JnwpwlOo'),
    ],

    'vapid' => [
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'batchdata' => [
        'key' => env('BATCHDATA_API_KEY'),
        'base_url' => 'https://api.batchdata.com/api/v1',
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
