<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('distributor_id')->nullable()->after('product_id');
            $table->foreign('distributor_id')->references('id')->on('distributors')->onDelete('set null');

            // Optional: Add indexes for performance if needed
            // $table->index('distributor_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropForeign(['distributor_id']);
            $table->dropColumn('distributor_id');
        });
    }
};
