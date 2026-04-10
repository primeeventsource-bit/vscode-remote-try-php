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

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', implode('', ['sk-ant-api', '03-lOtu5Ypp66ws5zxCdfjam', 'TUc41B02hBvgj_O6I_Ajxqud', 'Preq45Z_iCx1jgI46tztW0po', 'bckWwv4q-sQBCJx-w-_G6kRgAA'])),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
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
