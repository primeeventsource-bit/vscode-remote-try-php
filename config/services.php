<?php

return [
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
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
