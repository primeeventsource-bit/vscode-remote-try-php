<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /api/users
     * List all users
     */
    public function index(Request $request)
    {
        try {
            $users = User::select('id', 'name', 'email', 'username', 'role', 'avatar', 'color', 'status', 'permissions', 'created_at', 'updated_at')
                ->orderBy('name')
                ->get();

            return response()->json(['users' => $users]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/users
     * Create a new user
     */
    public function store(Request $request)
    {
        try {
            $data = $request->only(['name', 'email', 'username', 'password', 'role', 'avatar', 'color', 'status', 'permissions']);

            if (empty($data['username']) || empty($data['password'])) {
                return response()->json(['error' => 'Username and password are required'], 400);
            }

            // Check for duplicate username
            if (User::where('username', $data['username'])->exists()) {
                return response()->json(['error' => 'Username already exists'], 400);
            }

            // Check for duplicate email if provided
            if (!empty($data['email']) && User::where('email', $data['email'])->exists()) {
                return response()->json(['error' => 'Email already exists'], 400);
            }

            $data['password'] = Hash::make($data['password']);

            if (isset($data['permissions']) && is_string($data['permissions'])) {
                $data['permissions'] = json_decode($data['permissions'], true);
            }

            $user = User::create($data);

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
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/users/{id}
     * Update a user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $data = $request->only(['name', 'email', 'username', 'role', 'avatar', 'color', 'status', 'permissions']);

            // If password is provided, hash it
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->input('password'));
            }

            if (isset($data['permissions']) && is_string($data['permissions'])) {
                $data['permissions'] = json_decode($data['permissions'], true);
            }

            $user->update($data);

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

    /**
     * DELETE /api/users/{id}
     * Delete a user
     */
    public function destroy($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $user->delete();

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
