<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Adds 'in_transit' status for items being transferred between branches
     */
    public function up(): void
    {
        // For PostgreSQL - modify the enum type
        DB::statement("ALTER TYPE product_details_status ADD VALUE IF NOT EXISTS 'in_transit'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PostgreSQL doesn't allow removing enum values easily
        // Would need to recreate the type which is complex
    }
};
