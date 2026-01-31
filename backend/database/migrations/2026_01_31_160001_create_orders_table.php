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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // e.g., SHOPEE-12345
            $table->string('platform'); // shopee, tokopedia, etc.
            $table->string('status')->default('pending'); // pending, processed, shipped, completed, cancelled

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete(); // Person In Charge (Packer/Scanner)

            $table->string('customer_name')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('scanned_at')->nullable(); // Waktu scan barcode
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
