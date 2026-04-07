<?php

// Force-set critical env vars BEFORE anything else loads.
// Azure's Oryx copies an OLD .env with wrong values (mysql, cookie session).
// This guarantees correct DB, session, queue config regardless of .env content.
require_once __DIR__ . '/env_override.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;

// Also try to fix .env file from env.production if available
$basePath = dirname(__DIR__);
$envProductionPath = $basePath . '/env.production';
$envPath = $basePath . '/.env';
if (file_exists($envProductionPath)) {
    $currentEnv = file_exists($envPath) ? file_get_contents($envPath) : '';
    if (!str_contains($currentEnv, 'TWILIO_ACCOUNT_SID')) {
        @copy($envProductionPath, $envPath);
    }
}

return Application::configure(basePath: $basePath)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register named middleware aliases
        $middleware->alias([
            'auth.token'     => \App\Http\Middleware\AuthenticateApiToken::class,
            'role'           => \App\Http\Middleware\RequireRole::class,
            'permission'     => \App\Http\Middleware\RequirePermission::class,
            'twilio.webhook' => \App\Http\Middleware\ValidateTwilioWebhook::class,
        ]);

        // Exempt Twilio webhooks from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/*',
        ]);

        // Web middleware additions
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // API middleware additions — include session so browser fetch() calls work
        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON responses for API routes
        $exceptions->render(function (\Throwable $exception, $request) {
            if (str_starts_with($request->path(), 'api/')) {
                $status = method_exists($exception, 'getStatusCode')
                    ? $exception->getStatusCode()
                    : 500;

                $message = $status === 500
                    ? 'Internal server error'
                    : ($exception->getMessage() ?: 'Server error');

                return response()->json(['error' => $message], $status);
            }
            return null;
        });

        // Schema-not-ready handler
        $exceptions->render(function (QueryException $exception, $request) {
            $msg = $exception->getMessage();
            $isSchemaIssue = str_contains($msg, 'Base table or view not found')
                || str_contains($msg, 'no such table')
                || str_contains($msg, 'Unknown column')
                || str_contains($msg, 'Invalid object name');

            if (! $isSchemaIssue) {
                return null;
            }

            if ($request->expectsJson() || str_starts_with($request->path(), 'livewire')) {
                return response()->json([
                    'message' => 'CRM data is not ready yet. Run the latest migrations and reload.',
                    'error'   => 'schema_not_ready',
                ], 503);
            }

            return $request->user()
                ? redirect()->route('dashboard')->with('error', 'Run migrations and reload.')
                : redirect()->route('login')->with('error', 'Run migrations and reload.');
        });
    })->create();
