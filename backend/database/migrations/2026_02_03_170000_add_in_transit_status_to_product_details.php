<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Adds 'in_transit' status for items being transferred between branches
     * Also adds 'deleted' and 'returned' status for stock out operations
     */
    public function up(): void
    {
        // For PostgreSQL, Laravel enum is implemented as a CHECK constraint
        // We need to drop the old constraint and add a new one with expanded values

        // First, drop the existing check constraint
        DB::statement("ALTER TABLE product_details DROP CONSTRAINT IF EXISTS product_details_status_check");

        // Add the new check constraint with expanded enum values
        DB::statement("ALTER TABLE product_details ADD CONSTRAINT product_details_status_check CHECK (status::text = ANY (ARRAY['available'::character varying, 'sold'::character varying, 'transfer'::character varying, 'service'::character varying, 'booked'::character varying, 'in_transit'::character varying, 'deleted'::character varying, 'returned'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original constraint
        DB::statement("ALTER TABLE product_details DROP CONSTRAINT IF EXISTS product_details_status_check");
        DB::statement("ALTER TABLE product_details ADD CONSTRAINT product_details_status_check CHECK (status::text = ANY (ARRAY['available'::character varying, 'sold'::character varying, 'transfer'::character varying, 'service'::character varying, 'booked'::character varying]::text[]))");
    }
};
