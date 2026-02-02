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
    public function index(Request $request)
    {
        $query = Product::with([
            'productDetails' => function ($q) {
                $q->where('status', 'available');
            },
            'inventories'
        ]);

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('sku', 'like', '%' . $request->search . '%');
        }

        $products = $query->latest()->paginate(20);

        // Append calculated stock
        $products->getCollection()->transform(function ($product) {
            if ($product->type === 'hp') {
                $product->stock = $product->productDetails->count();
            } else {
                $product->stock = $product->inventories->sum('quantity');
            }
            return $product;
        });

        return response()->json($products);
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
            'imeis.*.imei' => 'required_if:type,hp|string|distinct', // Check unique in logic too
            // 'imeis.*.color' => 'nullable|string',
            'imeis.*.ram' => 'nullable|string',
            'imeis.*.storage' => 'nullable|string',
            'imeis.*.condition' => 'required_if:type,hp|in:new,second',
            'imeis.*.cost_price' => 'required_if:type,hp|numeric|min:0',
            'imeis.*.selling_price' => 'required_if:type,hp|numeric|min:0',
        ]);

        $user = Auth::user();
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
            else {
                foreach ($request->imeis as $item) {
                    // Check Duplicate IMEI globally
                    if (ProductDetail::where('imei', $item['imei'])->exists()) {
                        throw new \Exception("IMEI {$item['imei']} already exists.");
                    }

                    ProductDetail::create([
                        'product_id' => $product->id,
                        'imei' => $item['imei'],
                        'color' => $item['color'] ?? null,
                        'ram' => $item['ram'] ?? null,
                        'storage' => $item['storage'] ?? null,
                        'condition' => $item['condition'],
                        'status' => 'available',
                        'placement_type' => $request->placement_type,
                        'placement_id' => $request->placement_id,
                        'cost_price' => $item['cost_price'],
                        'selling_price' => $item['selling_price'],
                        'distributor_id' => $distributorId, // GUNAKAN VARIABEL INI
                    ]);
                }

                // Log (Aggregate for HP stock in? or detailed?)
                // Usually just log that we added X items
                InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => 1, // Placeholder
                    'user_id' => $user->id,
                    'type' => 'in',
                    'quantity' => count($request->imeis),
                    'balance_after' => ProductDetail::where('product_id', $product->id)->where('status', 'available')->count(),
                    'description' => "Stock In " . count($request->imeis) . " units (HP)",
                    'reference_id' => 'STOCK-IN-HP-' . time()
                ]);
            }

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
}
