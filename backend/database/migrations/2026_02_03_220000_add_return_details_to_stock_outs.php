<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            // Warehouse destination for returns (gudang mana)
            $table->foreignId('return_destination_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();

            // Photo proof (max 10MB validated in controller, path stored here)
            $table->string('proof_image')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropForeign(['return_destination_id']);
            $table->dropColumn(['return_destination_id', 'proof_image']);
        });
    }
};
