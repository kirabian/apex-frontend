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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->foreignId('online_shop_id')->nullable()->constrained('online_shops')->onDelete('set null');
            $table->foreignId('distributor_id')->nullable()->constrained('distributors')->onDelete('set null');

            // Make branch_id nullable if it isn't already (it usually is or should be for non-physical staff)
            // But if it's already nullable, this won't hurt. If it's not, we modify it.
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['online_shop_id']);
            $table->dropForeign(['distributor_id']);
            $table->dropColumn(['warehouse_id', 'online_shop_id', 'distributor_id']);
        });
    }
};
