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

        DB::statement("ALTER TABLE branches MODIFY COLUMN type VARCHAR(255) NOT NULL DEFAULT 'physical'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
