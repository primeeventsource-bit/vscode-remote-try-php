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
            if ($request->expectsJson() || $request->is('api/*') || $request->is('livewire/*')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }

        if (! in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('livewire/*')) {
                return response()->json(['error' => 'Forbidden — insufficient role'], 403);
            }
            abort(403, 'Forbidden — insufficient role');
        }

        return $next($request);
    }
}
