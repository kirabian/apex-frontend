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
            $table->string('giveaway_receiver')->nullable()->after('shopee_postal_code');
            $table->string('giveaway_phone')->nullable()->after('giveaway_receiver');
            $table->text('giveaway_address')->nullable()->after('giveaway_phone');
            $table->string('giveaway_province')->nullable()->after('giveaway_address');
            $table->string('giveaway_city')->nullable()->after('giveaway_province');
            $table->string('giveaway_district')->nullable()->after('giveaway_city');
            $table->string('giveaway_village')->nullable()->after('giveaway_district');
            $table->string('giveaway_postal_code')->nullable()->after('giveaway_village');
            $table->text('giveaway_notes')->nullable()->after('giveaway_postal_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn([
                'giveaway_receiver',
                'giveaway_phone',
                'giveaway_address',
                'giveaway_province',
                'giveaway_city',
                'giveaway_district',
                'giveaway_village',
                'giveaway_postal_code',
                'giveaway_notes'
            ]);
        });
    }
};
