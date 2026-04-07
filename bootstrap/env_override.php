<?php

/**
 * Azure .env Override — PHYSICALLY REWRITES the .env file.
 *
 * On Azure App Service, Oryx copies an OLD .env from the container image
 * to /var/www/html. That old .env has DB_CONNECTION=mysql and
 * SESSION_DRIVER=cookie which crashes everything.
 *
 * This script detects the bad .env and OVERWRITES IT with correct values
 * before Laravel's Dotenv parser reads it.
 */

$envPath = dirname(__DIR__) . '/.env';

// Quick check: does the current .env have the wrong DB connection?
if (file_exists($envPath)) {
    $content = @file_get_contents($envPath);
    if ($content && str_contains($content, 'DB_CONNECTION=mysql')) {
        // BAD .env detected — overwrite with correct production values
        $correctEnv = <<<'ENV'
APP_NAME="Prime CRM"
APP_ENV=production
APP_KEY=base64:2ZHu1y2i380yzWLyWbwZUndnpcu6ra4Bo7VOK+dfxEo=
APP_DEBUG=false
APP_URL=https://crmprime.online
DB_CONNECTION=sqlsrv
DB_HOST=primecrmdbserver.database.windows.net
DB_PORT=1433
DB_DATABASE=primecrmdb
DB_USERNAME=primeadmin
DB_PASSWORD='$Credit123'
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
LOG_CHANNEL=stderr
LOG_LEVEL=warning
OPENAI_MODEL=gpt-4o-mini
ENV;

        // Try to overwrite — may fail if filesystem is read-only
        @file_put_contents($envPath, $correctEnv);

        // Also force-set via putenv as backup
        $lines = explode("\n", $correctEnv);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && !str_starts_with($line, '#') && str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $val = trim($val, "'\"");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
                putenv("{$key}={$val}");
            }
        }
    }
}
