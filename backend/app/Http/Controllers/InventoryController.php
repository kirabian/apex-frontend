<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    // List Inventory
    // List Inventory (Granular / Unit based)
    // Filtered by branch - only super_admin can see all
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = ProductDetail::with(['product', 'distributor', 'user']);

        // ============================================
        // BRANCH FILTER:
        // - Roles in $unrestrictedRoles can see ALL or filter by any branch
        // - Other roles with branch_id are LOCKED to their branch
        // ============================================

        $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];

        // If user is NOT in unrestricted roles AND has a branch_id, lock them to their branch
        if (!in_array($user->role, $unrestrictedRoles) && $user->branch_id) {
            $query->where('placement_type', 'branch')
                ->where('placement_id', $user->branch_id);
        }

        // Optional: filter by specific branch (for super_admin or admin_produk viewing specific branch)
        if ($request->has('branch_id')) {
            $query->where('placement_type', 'branch')
                ->where('placement_id', $request->branch_id);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('imei', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by Status (Default available)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'available');
        }

        // Filter by placement type (branch/warehouse/online_shop)
        if ($request->has('placement_type')) {
            $query->where('placement_type', $request->placement_type);
        }

        $items = $query->latest()->paginate(20);

        // Transform results to include placement name
        // This resolves "branch #6" to "PSTORE BIG JAKARTA"
        $items->getCollection()->transform(function ($item) {
            $item->placement_name = $item->placement ? $item->placement->name : null;

            // For returned items, include proof_image and return details
            if ($item->status === 'returned') {
                $returnStockOut = $item->latestReturnStockOut();
                if ($returnStockOut) {
                    $item->proof_image = $returnStockOut->proof_image
                        ? asset('storage/' . $returnStockOut->proof_image)
                        : null;
                    $item->customer_name = $returnStockOut->customer_name;
                    $item->retur_issue = $returnStockOut->retur_issue;
                    $item->retur_officer = $returnStockOut->retur_officer;
                    $item->return_date = $returnStockOut->created_at;
                }
            }

            return $item;
        });

        return response()->json($items);
    }

    // Stock In (Input Barang)
    public function stockIn(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'type' => 'required|in:hp,non-hp', // Matches product type

            // Placement (Ideally auto-detected from user, but allowed if explicit)
            'placement_type' => 'required|in:branch,warehouse,online_shop',
            'placement_id' => 'required|integer',

            // For Non-HP
            'quantity' => 'required_if:type,non-hp|integer|min:1',

            // For HP
            'imeis' => 'required_if:type,hp|array',
            'imeis.*.imei' => ['required_if:type,hp', 'string', 'distinct', 'max:20', 'regex:/^[a-zA-Z0-9]+$/'], // No newlines allowed
            // 'imeis.*.color' => 'nullable|string',
            'imeis.*.ram' => 'nullable|string',
            'imeis.*.storage' => 'nullable|string',
            'storage' => 'nullable|string', // Allow root storage
            'imeis.*.condition' => 'required_if:type,hp|in:new,second',
            'imeis.*.cost_price' => 'required_if:type,hp|numeric|min:0',
            'imeis.*.selling_price' => 'required_if:type,hp|numeric|min:0',
        ]);

        $user = Auth::user();

        // Determine Ownership User (Who 'owns' the stock)
        // If inventory_user_id is passed (from shared account selection), use that.
        // Otherwise use logged in user.
        $ownerUserId = $user->id;
        if ($request->has('inventory_user_id') && $request->inventory_user_id) {
            // Verify access? For now assume if they can see it they can use it (filtered by UI)
            $ownerUserId = $request->inventory_user_id;
        }

        DB::beginTransaction();

        try {
            $distributorId = $request->distributor_id;
            if (!$distributorId && $request->new_distributor_name) {
                $newDist = \App\Models\Distributor::create([
                    'name' => $request->new_distributor_name,
                    'is_active' => true
                ]);
                $distributorId = $newDist->id;
            }

            if (!$distributorId) {
                throw new \Exception("Distributor harus dipilih atau diisi manual.");
            }
            $product = Product::findOrFail($request->product_id);

            // 1. Handle Non-HP (Quantity Based)
            if ($request->type === 'non-hp') {
                $inventory = Inventory::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'placement_type' => $request->placement_type,
                        'placement_id' => $request->placement_id
                    ],
                    ['quantity' => 0]
                );

                $inventory->increment('quantity', $request->quantity);

                // Log
                InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => 1, // Fallback or need to make nullable if placement isn't branch
                    // TODO: Update InventoryLog to support polymorphic placement too? Or just use description for now.
                    // For now, let's assume we map placement_id -> branch_id if type is branch, or null.
                    'user_id' => $user->id,
                    'type' => 'in',
                    'quantity' => $request->quantity,
                    'balance_after' => $inventory->quantity,
                    'description' => "Stock In from Distributor " . ($request->distributor_id),
                    'reference_id' => 'STOCK-IN-' . time()
                ]);
            }

            // 2. Handle HP (IMEI Based)
            // 2. Handle HP (IMEI Based)
            else {
                // Determine details array key
                $details = $request->imeis ?? $validated['details'] ?? [];

                $inserted_count = 0;
                $duplicates = [];

                foreach ($details as $item) {
                    // Check Duplicate IMEI globally
                    if (ProductDetail::where('imei', $item['imei'])->exists()) {
                        $duplicates[] = $item['imei'];
                        continue;
                    }

                    ProductDetail::create([
                        'product_id' => $product->id,
                        'imei' => $item['imei'],
                        'color' => $item['color'] ?? null,
                        'ram' => $request->ram ?? null, // Use parent spec
                        'storage' => $request->storage ?? null, // Use parent spec
                        'condition' => $item['condition'],
                        'status' => 'available',
                        'placement_type' => $request->placement_type,
                        'placement_id' => $request->placement_id,
                        'cost_price' => $item['cost_price'],
                        'selling_price' => $item['selling_price'],
                        'distributor_id' => $distributorId,
                        'user_id' => $ownerUserId,
                    ]);
                    $inserted_count++;
                }

                // Log
                if ($inserted_count > 0) {
                    InventoryLog::create([
                        'product_id' => $product->id,
                        'branch_id' => 1,
                        'user_id' => $user->id,
                        'type' => 'in',
                        'quantity' => $inserted_count,
                        'balance_after' => ProductDetail::where('product_id', $product->id)->where('status', 'available')->count(),
                        'description' => "Stock In {$inserted_count} units (HP)",
                        'reference_id' => 'STOCK-IN-HP-' . time()
                    ]);
                }

                DB::commit();

                return response()->json([
                    'message' => 'Stock in processed',
                    'success' => true,
                    'inserted_count' => $inserted_count,
                    'duplicates' => $duplicates
                ], 201);
            }

            // Update Master Product Price (Sync with latest Stock In Selling Price)
            if ($request->type === 'hp' && count($request->imeis) > 0) {
                // Use the first item's selling price as the master price
                $product->update(['price' => $request->imeis[0]['selling_price']]);
            }
            // For non-hp, we might need similar logic if price is passed, but currently it's quantity only?
            // Checking validation: Non-HP doesn't seem to have price input in stockIn validation?
            // Actually it does not. Non-HP stock in is just quantity.
            // So only update for HP for now.

            DB::commit();
            return response()->json(['message' => 'Stock in successful'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Helper to get products for dropdown
    public function getProducts(Request $request)
    {
        $query = Product::query();
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->name) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        return response()->json($query->select('id', 'name', 'type', 'sku', 'brand')->limit(20)->get());
    }

    // Update item status (e.g., accept return: returned -> available)
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,sold,returned,deleted,out'
        ]);

        $item = ProductDetail::findOrFail($id);
        $item->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diubah',
            'data' => $item
        ]);
    }
    // Create Dedicated Inventory Account
    public function createAccount(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Entering createAccount', ['user_id' => Auth::id(), 'request' => $request->all()]);

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $user = Auth::user();
        if (!$user->branch_id && !$user->warehouse_id && !$user->online_shop_id && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Anda tidak memiliki lokasi fisik untuk membuat akun inventory.'], 403);
        }

        // Generate Credentials
        // Use microtime to collision avoidance
        $timestamp = microtime(true);
        $random = rand(100, 999);
        // Normalize timestamp for string
        $tsString = str_replace('.', '', (string) $timestamp);

        $username = 'inv.' . substr($tsString, -8) . '.' . $random;
        $email = $username . '@apex-inventory.com';
        $password = 'inventory123'; // Default password

        DB::beginTransaction();
        try {
            // Ensure Role Exists
            $roleName = 'inventory';
            if (!\Spatie\Permission\Models\Role::where('name', $roleName)->exists()) {
                \Spatie\Permission\Models\Role::create(['name' => $roleName, 'guard_name' => 'web']);
            }

            $newUser = \App\Models\User::create([
                'name' => $request->name,
                'full_name' => $request->name,
                'username' => $username,
                'code_id' => 'INV-' . substr($tsString, -10) . $random,
                'email' => $email,
                'password' => $password,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $user->warehouse_id,
                'online_shop_id' => $user->online_shop_id,
                'distributor_id' => $user->distributor_id,
                'created_by' => $user->id, // Mark ownership
                'is_active' => true,
                'theme_color' => 'default',
            ]);

            $newUser->assignRole($roleName);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Akun inventory berhasil dibuat.',
                'data' => $newUser
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Create Inventory Account Error: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // FIXER: Split merged IMEIs (Temporary Tool)
    public function fixMergedImeis()
    {
        $details = ProductDetail::where(function ($q) {
            $q->where('imei', 'like', "%\n%")
                ->orWhere('imei', 'like', "% %")
                ->orWhere('imei', 'like', "%,%");
        })->get();

        $fixedCount = 0;
        $newRowsCount = 0;

        DB::beginTransaction();
        try {
            foreach ($details as $detail) {
                // Split by newline, comma, space (including multiple spaces)
                $imeis = preg_split('/[\s,\n]+/', $detail->imei, -1, PREG_SPLIT_NO_EMPTY);
                $imeis = array_values(array_unique($imeis)); // Unique just in case

                if (count($imeis) > 1) {
                    // Update original with first IMEI
                    $detail->update(['imei' => $imeis[0]]);
                    $fixedCount++;

                    // Create new rows for the rest
                    for ($i = 1; $i < count($imeis); $i++) {
                        $newDetail = $detail->replicate();
                        $newDetail->imei = $imeis[$i];
                        $newDetail->save();
                        $newRowsCount++;
                    }
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Fixer executed',
                'fixed_rows' => $fixedCount,
                'new_rows_created' => $newRowsCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
