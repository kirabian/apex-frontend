<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::with(['branch', 'roles']);

        // Jika bukan super_admin, hanya tampilkan user dari branch yang sama
        if (!$user->hasRole('super_admin')) {
            $query->where('branch_id', $user->branch_id);
        }

        // Filter by branch_id if provided (for super_admin filtering)
        if ($request->has('branch_id') && $user->hasRole('super_admin')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by Role
        if ($request->has('role')) {
            $query->role($request->role);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        try {
            $branchId = $request->branch_id ?: null;

            $user = \App\Models\User::create([
                'name' => $request->full_name,
                'full_name' => $request->full_name,
                'username' => $request->username,
                // TAMBAHKAN INI: Bikin email dummy agar database tidak error
                'email' => $request->username . '@apexpos.com',
                'password' => $request->password,
                'branch_id' => $branchId,
                'is_active' => $request->is_active ?? true,
                'theme_color' => 'default',
            ]);

            if ($request->role) {
                $user->assignRole($request->role);
            }

            return response()->json([
                'success' => true,
                'data' => $user->load('roles', 'branch')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    public function show(User $user)
    {
        $currentUser = request()->user();

        if (!$currentUser->hasRole('super_admin') && $currentUser->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $user->load('roles', 'branch')]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Check scope
        if (!$currentUser->hasRole('super_admin') && $currentUser->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'username' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|exists:roles,name',
            'branch_id' => 'nullable|exists:branches,id',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['password'])) {
            // $validated['password'] = Hash::make($validated['password']); 
            // Removed because model casts 'password' => 'hashed'
        }

        // Prevent changing branch if not super_admin
        if (!$currentUser->hasRole('super_admin')) {
            unset($validated['branch_id']);
        }

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json(['success' => true, 'data' => $user->load('roles', 'branch')]);
    }

    public function destroy(User $user)
    {
        $currentUser = request()->user();
        if (!$currentUser->hasRole('super_admin') && $currentUser->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->delete();
        return response()->json(['success' => true]);
    }
}
