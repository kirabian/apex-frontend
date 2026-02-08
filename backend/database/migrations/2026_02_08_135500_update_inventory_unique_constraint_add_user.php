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
        // Alter inventories table to add user_id to the unique constraint
        Schema::table('inventories', function (Blueprint $table) {
            // First drop existing constraint
            // Try to handle different DB drivers (Postgres constraint names can be tricky)
            // But usually Laravel adds _unique suffix
            $table->dropUnique('inventory_unique_location');
            $table->unique(['product_id', 'placement_type', 'placement_id', 'user_id'], 'inventory_unique_location_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventory_unique_location_user');
            $table->unique(['product_id', 'placement_type', 'placement_id'], 'inventory_unique_location');
        });
    }
};
