<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change enum to string to support more types like 'warehouse'
        // Using raw SQL for better compatibility or just modify column
        Schema::table('branches', function (Blueprint $table) {
            // Note: modifying enum is platform specific.
            // We will drop the column and re-add it as string if it was enum, 
            // OR use DB::statement to alter it.
            // Safest for now: modify() if using DBAL, or raw SQL.
            // Since we can't easily rely on doctrine/dbal being present safely:

            // Let's assume MySQL
            //$table->string('type')->change(); 
            // But 'type' is enum. 
        });

        // PostgreSQL syntax
        // Create a temporary check constraint to allow casting if needed, but for simple ENUM to VARCHAR it should work implicit or with explicit cast.
        // However, since we are changing structure, let's use the standard SQL standard way if possible or PG specific.
        // PG: ALTER TABLE branches ALTER COLUMN type TYPE VARCHAR(255);
        // Also need to drop the default if it conflicts, but usually it's fine.
        // If it was an ENUM, sometimes we need 'USING type::varchar' or similar.

        // First we drop the check constraint if it was an enum constraint (Laravel often creates checks for Enums in PG)
        // But since we can't easily know the constraint name, let's just try the ALTER TYPE.
        DB::statement("ALTER TABLE branches ALTER COLUMN type TYPE VARCHAR(255) USING type::varchar");
        DB::statement("ALTER TABLE branches ALTER COLUMN type SET DEFAULT 'physical'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
