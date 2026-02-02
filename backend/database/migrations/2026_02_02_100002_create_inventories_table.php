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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Location (Polymorphic-like)
            $table->string('placement_type'); // 'branch', 'warehouse', 'online_shop'
            $table->unsignedBigInteger('placement_id');

            $table->integer('quantity')->default(0);

            $table->timestamps();

            // Ensure unique product per location
            $table->unique(['product_id', 'placement_type', 'placement_id'], 'inventory_unique_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
