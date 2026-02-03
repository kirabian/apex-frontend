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
        Schema::create('stock_outs', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_id', 20)->unique(); // ID Resi internal (format: O03FEB-K9Z)
            $table->enum('category', ['pindah_cabang', 'kesalahan_input', 'retur', 'shopee']);

            // Pindah Cabang Fields
            $table->foreignId('destination_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('receiver_name')->nullable();
            $table->text('transfer_notes')->nullable();

            // Kesalahan Input Fields
            $table->text('deletion_reason')->nullable();

            // Retur Fields
            $table->string('retur_officer')->nullable();
            $table->string('retur_seal')->nullable();
            $table->text('retur_issue')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();

            // Shopee Fields
            $table->string('shopee_receiver')->nullable();
            $table->string('shopee_phone')->nullable();
            $table->text('shopee_address')->nullable();
            $table->text('shopee_notes')->nullable();
            $table->string('shopee_tracking_no')->nullable(); // Nomor resi Shopee dari scan barcode

            // Meta
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Admin yang proses
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('category');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_outs');
    }
};
