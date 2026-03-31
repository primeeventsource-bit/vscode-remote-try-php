<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (QueryException $exception, $request) {
            $message = $exception->getMessage();

            $isSchemaIssue = str_contains($message, 'Base table or view not found')
                || str_contains($message, 'no such table')
                || str_contains($message, 'Unknown column')
                || str_contains($message, 'Invalid object name');

            if (! $isSchemaIssue) {
                return null;
            }

            if ($request->expectsJson() || str_starts_with($request->path(), 'livewire')) {
                return response()->json([
                    'message' => 'CRM data is not ready yet. Run the latest migrations and reload the page.',
                    'error' => 'schema_not_ready',
                ], 503);
            }

            if ($request->user()) {
                return redirect()->route('dashboard')
                    ->with('error', 'CRM data is not ready yet. Run the latest migrations and reload the page.');
            }

            return redirect()->route('login')
                ->with('error', 'CRM data is not ready yet. Run the latest migrations and reload the page.');
        });
    })->create();
