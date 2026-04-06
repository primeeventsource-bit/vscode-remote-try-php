<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Azure Blob Storage — production durable disk
        'azure' => [
            'driver' => 's3',
            'key' => env('AZURE_STORAGE_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('AZURE_STORAGE_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AZURE_STORAGE_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'bucket' => env('AZURE_STORAGE_CONTAINER', env('AWS_BUCKET')),
            'url' => env('AZURE_STORAGE_URL', env('AWS_URL')),
            'endpoint' => env('AZURE_STORAGE_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'visibility' => 'public',
        ],

        // Uploads disk — resolves to azure in production, public locally
        'uploads' => [
            'driver' => 'local',
            'root' => storage_path('app/public/uploads'),
            'url' => env('APP_URL') . '/storage/uploads',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
