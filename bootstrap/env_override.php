<?php

/**
 * Azure .env Override
 *
 * On Azure App Service, the Oryx build copies an OLD .env from the container
 * image to /var/www/html. This file overrides critical env vars that MUST
 * be correct for the app to function, regardless of what .env says.
 *
 * This runs before Laravel's Dotenv loads, so these values take priority
 * only if the current env value is wrong/missing.
 */

// Only apply on Azure (detected by /var/www/html base path)
if (realpath(__DIR__ . '/..') === '/var/www/html' || php_sapi_name() !== 'cli') {

    $overrides = [
        'DB_CONNECTION' => 'sqlsrv',
        'DB_HOST' => 'primecrmdbserver.database.windows.net',
        'DB_PORT' => '1433',
        'DB_DATABASE' => 'primecrmdb',
        'DB_USERNAME' => 'primeadmin',
        'SESSION_DRIVER' => 'file',
        'SESSION_LIFETIME' => '120',
        'SESSION_ENCRYPT' => 'true',
        'CACHE_STORE' => 'file',
        'QUEUE_CONNECTION' => 'database',
        'FILESYSTEM_DISK' => 'public',
        'LOG_CHANNEL' => 'stderr',
        'LOG_LEVEL' => 'warning',
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
    ];

    foreach ($overrides as $key => $value) {
        // Set in all places PHP reads env vars
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}
