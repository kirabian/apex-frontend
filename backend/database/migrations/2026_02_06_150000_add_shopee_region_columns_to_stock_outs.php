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
            $table->string('shopee_province')->nullable()->after('shopee_address');
            $table->string('shopee_city')->nullable()->after('shopee_province');
            $table->string('shopee_district')->nullable()->after('shopee_city');
            $table->string('shopee_village')->nullable()->after('shopee_district');
            $table->string('shopee_postal_code')->nullable()->after('shopee_village');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn([
                'shopee_province',
                'shopee_city',
                'shopee_district',
                'shopee_village',
                'shopee_postal_code'
            ]);
        });
    }
};
