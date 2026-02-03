<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductDetail;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Fix stuck 'in_transit' items by moving them to destination branch and setting status to 'available'
     */
    public function up(): void
    {
        // Get all items that are stuck in transit
        $stuckItems = ProductDetail::where('status', 'in_transit')->get();

        foreach ($stuckItems as $item) {
            // Find the latest stock out record for this item
            // We need to join with stock_out_items pivot table
            $lastStockOut = DB::table('stock_outs')
                ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
                ->where('stock_out_items.product_detail_id', $item->id)
                ->where('stock_outs.category', 'pindah_cabang')
                ->orderBy('stock_outs.created_at', 'desc')
                ->select('stock_outs.*')
                ->first();

            if ($lastStockOut && $lastStockOut->destination_branch_id) {
                // Move item to destination branch and set as available
                $item->update([
                    'status' => 'available',
                    'placement_type' => 'branch',
                    'placement_id' => $lastStockOut->destination_branch_id
                ]);
            } else {
                // Fallback: if no valid destination found, revert to available without moving (or keep as is? User said "hapus fitur")
                // Safer to set available so it's not hidden
                $item->update(['status' => 'available']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No simple revert possible as we don't know the exact previous state
    }
};
