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
        $query = User::with(['branch', 'warehouse', 'onlineShop', 'distributor', 'roles']);

        // Jika bukan super_admin, filter berdasarkan placement user login
        if (!$user->hasRole('super_admin')) {
            // Logic filtering yg lebih kompleks jika user bukan superadmin
            // Untuk sekarang kita biarkan logic branch_id saja dulu atau sesuaikan nanti
            if ($user->branch_id)
                $query->where('branch_id', $user->branch_id);
            // Tambahan logic jika user login adalah kepala gudang dll (future improvement)
        }

        // Filters
        if ($request->has('branch_id'))
            $query->where('branch_id', $request->branch_id);
        if ($request->has('warehouse_id'))
            $query->where('warehouse_id', $request->warehouse_id);
        if ($request->has('role'))
            $query->role($request->role);

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        try {
            // Determine placement based on input. Only one should be set ideally, or handled by frontend.
            $branchId = $request->branch_id ?: null;
            $warehouseId = $request->warehouse_id ?: null;
            $onlineShopId = $request->online_shop_id ?: null;
            $distributorId = $request->distributor_id ?: null;

            $user = \App\Models\User::create([
                'name' => $request->full_name,
                'full_name' => $request->full_name,
                'username' => $request->username,
                'code_id' => $request->code_id,
                'email' => $request->username . '@apexpos.com',
                'password' => $request->password,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'online_shop_id' => $onlineShopId,
                'distributor_id' => $distributorId,
                'is_active' => $request->is_active ?? true,
                'theme_color' => 'default',
            ]);

            if ($request->role) {
                $user->assignRole($request->role);
            }

            return response()->json([
                'success' => true,
                'data' => $user->load('roles', 'branch', 'warehouse', 'onlineShop', 'distributor')
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
        // Simple auth check for now
        return response()->json(['success' => true, 'data' => $user->load('roles', 'branch', 'warehouse', 'onlineShop', 'distributor')]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'username' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
            'code_id' => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|exists:roles,name',
            'branch_id' => 'nullable|exists:branches,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'online_shop_id' => 'nullable|exists:online_shops,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        // Logic to clear other placements if one is selected? 
        // For now trusting frontend to send nulls for others, or we explicitly nullify others?
        // Let's rely on payload.

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json(['success' => true, 'data' => $user->load('roles', 'branch', 'warehouse', 'onlineShop', 'distributor')]);
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
