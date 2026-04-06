<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;

return Application::configure(basePath: dirname(__DIR__))
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
            'auth.token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'role'       => \App\Http\Middleware\RequireRole::class,
            'permission' => \App\Http\Middleware\RequirePermission::class,
        ]);

        // Web middleware additions
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // API middleware additions
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
