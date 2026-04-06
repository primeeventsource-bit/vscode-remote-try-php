<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const VALID_ROLES = [
        'master_admin', 'admin', 'fronter', 'closer',
        'fronter_panama', 'closer_panama', 'agent',
    ];

    public function index(Request $request)
    {
        $users = User::select(
            'id', 'name', 'email', 'username', 'role',
            'avatar', 'color', 'status', 'permissions',
            'created_at', 'updated_at'
        )->orderBy('name')->get();

        return response()->json(['users' => $users]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|max:255|unique:users,email',
            'username' => 'required|string|max:100|unique:users,username',
            'password' => 'required|string|min:8',
            'role'     => ['required', 'string', Rule::in(self::VALID_ROLES)],
            'avatar'   => 'nullable|string|max:255',
            'color'    => 'nullable|string|max:50',
            'status'   => 'nullable|string|max:50',
        ]);

        // Only master_admin can create other master_admins
        if ($data['role'] === 'master_admin' && $request->user()->role !== 'master_admin') {
            return response()->json(['error' => 'Only master admins can create master admin accounts'], 403);
        }

        $data['password'] = Hash::make($data['password']);

        // Extract role (not mass-assignable)
        $role = $data['role'];
        unset($data['role']);

        $user = User::create($data);
        $user->role = $role;

        // Permissions: only master_admin can set permissions
        if ($request->user()->role === 'master_admin' && $request->has('permissions')) {
            $perms = $request->input('permissions');
            $user->permissions = is_string($perms) ? json_decode($perms, true) : $perms;
        }

        $user->save();

        return response()->json(['user' => $this->formatUser($user)], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['sometimes', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'role'     => ['sometimes', 'string', Rule::in(self::VALID_ROLES)],
            'avatar'   => 'nullable|string|max:255',
            'color'    => 'nullable|string|max:50',
            'status'   => 'nullable|string|max:50',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Extract role (not mass-assignable) — only master_admin can change
        if (isset($data['role'])) {
            if ($request->user()->role === 'master_admin') {
                $user->role = $data['role'];
            }
            unset($data['role']);
        }

        // Permissions: only master_admin can modify
        if ($request->user()->role === 'master_admin' && $request->has('permissions')) {
            $perms = $request->input('permissions');
            $user->permissions = is_string($perms) ? json_decode($perms, true) : $perms;
        }

        $user->fill($data);
        $user->save();

        return response()->json(['user' => $this->formatUser($user->fresh())]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }

        // Only master_admin can delete master_admins
        if ($user->role === 'master_admin' && auth()->user()->role !== 'master_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'username'    => $user->username,
            'role'        => $user->role,
            'avatar'      => $user->avatar,
            'color'       => $user->color,
            'status'      => $user->status,
            'permissions' => $user->permissions,
        ];
    }
}
