<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates API bearer tokens against the api_tokens table.
 * Sets auth()->user() so downstream code works normally.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // If user is already authenticated via web session (Livewire/Blade fetch calls), allow through
        if (auth()->check()) {
            return $next($request);
        }

        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Unauthorized — token required'], 401);
        }

        $record = DB::table('api_tokens')
            ->where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json(['error' => 'Unauthorized — invalid or expired token'], 401);
        }

        $user = User::find($record->user_id);

        if (! $user) {
            return response()->json(['error' => 'Unauthorized — user not found'], 401);
        }

        // Set the authenticated user for the request lifecycle
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
