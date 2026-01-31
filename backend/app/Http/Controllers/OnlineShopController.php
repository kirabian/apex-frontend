<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OnlineShopController extends Controller
{
    // Scan Barcode / OCR input
    public function scan(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'type' => 'nullable|string|in:order,product'
        ]);

        $code = $request->code;
        $type = $request->type;

        // Try to find order first
        $order = Order::where('order_number', $code)->with('items.product')->first();

        if ($order) {
            return response()->json([
                'type' => 'order',
                'data' => $order
            ]);
        }

        // Try to find product
        $product = Product::where('barcode', $code)->orWhere('sku', $code)->first();

        if ($product) {
            return response()->json([
                'type' => 'product',
                'data' => $product
            ]);
        }

        return response()->json(['message' => 'Data tidak ditemukan'], 404);
    }

    // Create or Update Order from Scan
    public function updateOrder(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'status' => 'required|string',
            'customer_name' => 'nullable|string',
            'platform' => 'required|string',
        ]);

        $order = Order::updateOrCreate(
            ['order_number' => $request->order_number],
            [
                'platform' => $request->platform,
                'status' => $request->status,
                'customer_name' => $request->customer_name,
                'branch_id' => $request->user()->branch_id,
                'pic_id' => $request->user()->id,
                'scanned_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'data' => $order]);
    }

    // Inventory History with Restrictions
    public function inventory(Request $request)
    {
        $user = $request->user();
        $query = InventoryLog::with(['product', 'user', 'branch'])
            ->orderBy('created_at', 'desc');

        // Filter by branch
        if (!$user->hasRole('super_admin') && !$user->hasRole('leader_shopee')) {
            $query->where('branch_id', $user->branch_id);
        }

        // ROLE: Toko Online - View Current & Last Month ONLY
        if ($user->hasRole('toko_online')) {
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $query->where('created_at', '>=', $startOfLastMonth);
        }

        // ROLE: Leader Shopee - View All (Analyst)
        // No restriction on date, but maybe different view columns

        $logs = $query->paginate(20);

        return response()->json($logs);
    }

    // Analysis for Leader
    public function analysis(Request $request)
    {
        // Simple aggregate data
        $startOfMonth = Carbon::now()->startOfMonth();

        $stats = [
            'orders_today' => Order::whereDate('scanned_at', Carbon::today())->count(),
            'orders_this_month' => Order::where('created_at', '>=', $startOfMonth)->count(),
            'platform_distribution' => Order::select('platform', DB::raw('count(*) as total'))
                ->groupBy('platform')
                ->get(),
        ];

        return response()->json($stats);
    }
}
