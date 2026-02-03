<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Change status column from enum to varchar for more flexibility
     */
    public function up(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            // Change status to simple string - more flexible than enum
            $table->string('status', 20)->default('available')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - string is compatible
    }
};
