<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate.
 * Usage: ->middleware('permission:manage_users')
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        foreach ($permissions as $perm) {
            if (! $user->hasPerm($perm)) {
                return response()->json(['error' => 'Forbidden — missing permission: ' . $perm], 403);
            }
        }

        return $next($request);
    }
}
