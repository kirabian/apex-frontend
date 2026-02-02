<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('ram')->nullable();
            $table->string('storage')->nullable();
            // Category: imei (HP), non_imei (Accessories), service (Service)
            $table->enum('category', ['imei', 'non_imei', 'service'])->default('imei');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_types');
    }
};
