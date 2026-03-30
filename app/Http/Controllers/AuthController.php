<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * POST /api/login
     * Body: {username, password}
     * Returns: {user, token}
     */
    public function login(Request $request)
    {
        try {
            $username = $request->input('username');
            $password = $request->input('password');

            if (!$username || !$password) {
                return response()->json(['error' => 'Username and password are required'], 400);
            }

            $user = User::where('username', $username)->first();

            if (!$user) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            if (!Hash::check($password, $user->password)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            // Generate a simple token and store it
            $token = Str::random(64);

            DB::table('api_tokens')->insert([
                'user_id' => $user->id,
                'token' => hash('sha256', $token),
                'created_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);

            // Update user status to active
            $user->update(['status' => 'active']);

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                    'color' => $user->color,
                    'status' => $user->status,
                    'permissions' => $user->permissions,
                ],
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/logout
     * Invalidate the current session token
     */
    public function logout(Request $request)
    {
        try {
            $token = $request->bearerToken();

            if ($token) {
                DB::table('api_tokens')
                    ->where('token', hash('sha256', $token))
                    ->delete();
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/me
     * Return the currently authenticated user
     */
    public function me(Request $request)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $record = DB::table('api_tokens')
                ->where('token', hash('sha256', $token))
                ->where('expires_at', '>', now())
                ->first();

            if (!$record) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $user = User::find($record->user_id);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                    'color' => $user->color,
                    'status' => $user->status,
                    'permissions' => $user->permissions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
