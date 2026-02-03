<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StockOutController extends Controller
{
    // List all stock outs
    public function index(Request $request)
    {
        $query = StockOut::with(['user', 'destinationBranch', 'items.product']);

        if ($request->category) {
            $query->byCategory($request->category);
        }

        if ($request->search) {
            $query->search($request->search);
        }

        return response()->json(
            $query->latest()->paginate($request->per_page ?? 20)
        );
    }

    // Create stock out
    public function store(Request $request)
    {
        $request->validate([
            'category' => ['required', Rule::in(['pindah_cabang', 'kesalahan_input', 'retur', 'shopee'])],
            'product_detail_ids' => 'required|array|min:1',
            'product_detail_ids.*' => 'exists:product_details,id',

            // Pindah Cabang
            'destination_branch_id' => 'required_if:category,pindah_cabang|nullable|exists:branches,id',
            'receiver_name' => 'required_if:category,pindah_cabang|nullable|string|max:255',
            'transfer_notes' => 'nullable|string',

            // Kesalahan Input
            'deletion_reason' => 'required_if:category,kesalahan_input|nullable|string',

            // Retur
            'retur_officer' => 'required_if:category,retur|nullable|string|max:255',
            'retur_seal' => 'nullable|string|max:255',
            'retur_issue' => 'required_if:category,retur|nullable|string',
            'customer_name' => 'required_if:category,retur|nullable|string|max:255',
            'customer_phone' => 'required_if:category,retur|nullable|string|max:50',

            // Shopee
            'shopee_receiver' => 'required_if:category,shopee|nullable|string|max:255',
            'shopee_phone' => 'required_if:category,shopee|nullable|string|max:50',
            'shopee_address' => 'required_if:category,shopee|nullable|string',
            'shopee_notes' => 'nullable|string',
            'shopee_tracking_no' => 'required_if:category,shopee|nullable|string|max:100',
        ]);

        DB::beginTransaction();

        try {
            // Verify all items are available
            $productDetails = ProductDetail::whereIn('id', $request->product_detail_ids)
                ->where('status', 'available')
                ->get();

            if ($productDetails->count() !== count($request->product_detail_ids)) {
                throw new \Exception('Beberapa barang sudah tidak tersedia atau sudah keluar stok.');
            }

            // Create stock out record
            $stockOut = StockOut::create([
                'receipt_id' => StockOut::generateReceiptId(),
                'category' => $request->category,
                'user_id' => Auth::id(),
                // Pindah Cabang
                'destination_branch_id' => $request->destination_branch_id,
                'receiver_name' => $request->receiver_name,
                'transfer_notes' => $request->transfer_notes,
                // Kesalahan Input
                'deletion_reason' => $request->deletion_reason,
                // Retur
                'retur_officer' => $request->retur_officer,
                'retur_seal' => $request->retur_seal,
                'retur_issue' => $request->retur_issue,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                // Shopee
                'shopee_receiver' => $request->shopee_receiver,
                'shopee_phone' => $request->shopee_phone,
                'shopee_address' => $request->shopee_address,
                'shopee_notes' => $request->shopee_notes,
                'shopee_tracking_no' => $request->shopee_tracking_no,
            ]);

            // Attach items and update status
            $newStatus = $this->getStatusByCategory($request->category);

            foreach ($productDetails as $detail) {
                $stockOut->items()->attach($detail->id);
                $detail->update(['status' => $newStatus]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Stok berhasil dikeluarkan',
                'data' => $stockOut->load(['items.product', 'user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // Get single stock out
    public function show($id)
    {
        $stockOut = StockOut::with(['items.product', 'user', 'destinationBranch'])
            ->where('id', $id)
            ->orWhere('receipt_id', $id)
            ->firstOrFail();

        return response()->json($stockOut);
    }

    // Track by IMEI or Receipt ID
    public function track(Request $request)
    {
        $query = $request->q;

        if (!$query || strlen($query) < 3) {
            return response()->json(['message' => 'Query minimal 3 karakter'], 422);
        }

        $results = [];

        // ==========================================
        // PART 1: Search STOCK IN (Product Details)
        // ==========================================
        $productDetails = ProductDetail::with(['product', 'distributor', 'user'])
            ->where('imei', 'like', "%{$query}%")
            ->get();

        foreach ($productDetails as $detail) {
            $results[] = [
                'type' => 'stock_in',
                'id' => 'IN-' . $detail->id,
                'imei' => $detail->imei,
                'product_name' => $detail->product?->name,
                'product_brand' => $detail->product?->brand,
                'ram' => $detail->ram,
                'storage' => $detail->storage,
                'condition' => $detail->condition,
                'status' => $detail->status,
                'placement_type' => $detail->placement_type,
                'placement_id' => $detail->placement_id,
                'distributor' => $detail->distributor?->name,
                'input_by' => $detail->user?->name ?? $detail->user?->username,
                'cost_price' => $detail->cost_price,
                'selling_price' => $detail->selling_price,
                'created_at' => $detail->created_at,
            ];
        }

        // ==========================================
        // PART 2: Search STOCK OUT
        // ==========================================
        // Search by Receipt ID
        $byReceipt = StockOut::with(['items.product', 'user', 'destinationBranch'])
            ->where('receipt_id', 'like', "%{$query}%")
            ->get();

        // Search by IMEI in stock out items
        $byImei = StockOut::with(['items.product', 'user', 'destinationBranch'])
            ->whereHas('items', function ($q) use ($query) {
                $q->where('imei', 'like', "%{$query}%");
            })
            ->get();

        // Search by Shopee tracking
        $byShopee = StockOut::with(['items.product', 'user', 'destinationBranch'])
            ->where('shopee_tracking_no', 'like', "%{$query}%")
            ->get();

        $stockOuts = $byReceipt->merge($byImei)->merge($byShopee)->unique('id');

        foreach ($stockOuts as $out) {
            $results[] = [
                'type' => 'stock_out',
                'id' => $out->receipt_id,
                'category' => $out->category,
                'items' => $out->items->map(fn($i) => [
                    'imei' => $i->imei,
                    'product_name' => $i->product?->name,
                ]),
                'destination_branch' => $out->destinationBranch?->name,
                'receiver_name' => $out->receiver_name,
                'customer_name' => $out->customer_name,
                'shopee_receiver' => $out->shopee_receiver,
                'shopee_tracking_no' => $out->shopee_tracking_no,
                'deletion_reason' => $out->deletion_reason,
                'processed_by' => $out->user?->name ?? $out->user?->username,
                'created_at' => $out->created_at,
            ];
        }

        // Sort by created_at desc
        usort($results, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return response()->json([
            'query' => $query,
            'count' => count($results),
            'data' => $results
        ]);
    }

    // Helper: Get status based on category
    private function getStatusByCategory(string $category): string
    {
        return match ($category) {
            'pindah_cabang' => 'transfer',
            'kesalahan_input' => 'deleted',
            'retur' => 'returned',
            'shopee' => 'sold',
            default => 'out'
        };
    }
}
