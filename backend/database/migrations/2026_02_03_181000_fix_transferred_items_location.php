<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductDetail;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Fix items that have been transferred (available status) but location is not updated
     */
    public function up(): void
    {
        // Get all StockOuts of type 'pindah_cabang' created recently (e.g., last 24 hours to be safe, or just all)
        // We look for StockOuts where the items might not have been moved correctly
        $transfers = DB::table('stock_outs')
            ->where('category', 'pindah_cabang')
            ->whereNotNull('destination_branch_id')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($transfers as $transfer) {
            // Get items for this transfer
            $items = DB::table('stock_out_items')
                ->where('stock_out_id', $transfer->id)
                ->pluck('product_detail_id');

            // Update items that are 'available' but not at the destination branch
            ProductDetail::whereIn('id', $items)
                ->where('status', 'available')
                ->where(function ($query) use ($transfer) {
                    $query->where('placement_type', '!=', 'branch')
                        ->orWhere('placement_id', '!=', $transfer->destination_branch_id);
                })
                ->update([
                    'placement_type' => 'branch',
                    'placement_id' => $transfer->destination_branch_id
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revert
    }
};
