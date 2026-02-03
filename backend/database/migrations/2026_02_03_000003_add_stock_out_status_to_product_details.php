<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Add new status values needed for stock out categories
     */
    public function up(): void
    {
        // For PostgreSQL, we need to alter the enum type
        // First drop the constraint, then recreate with new values
        DB::statement("ALTER TABLE product_details DROP CONSTRAINT IF EXISTS product_details_status_check");
        DB::statement("ALTER TABLE product_details ADD CONSTRAINT product_details_status_check CHECK (status::text = ANY (ARRAY['available'::text, 'sold'::text, 'transfer'::text, 'service'::text, 'booked'::text, 'deleted'::text, 'returned'::text]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE product_details DROP CONSTRAINT IF EXISTS product_details_status_check");
        DB::statement("ALTER TABLE product_details ADD CONSTRAINT product_details_status_check CHECK (status::text = ANY (ARRAY['available'::text, 'sold'::text, 'transfer'::text, 'service'::text, 'booked'::text]))");
    }
};
