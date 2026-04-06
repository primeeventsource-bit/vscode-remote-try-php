<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate.
 * Usage: ->middleware('role:master_admin,admin')
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['error' => 'Forbidden — insufficient role'], 403);
        }

        return $next($request);
    }
}
