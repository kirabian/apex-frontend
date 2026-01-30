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
        Schema::create('branches', function (Blueprint $table) {
            $table->id(); // Ini jadi ID Cabang
            $table->string('code')->unique(); // Kode Cabang (contoh: PST-JKT01)
            $table->string('name'); // Nama Cabang
            $table->text('address')->nullable(); // Alamat Lengkap
            $table->enum('timezone', ['WIB', 'WITA', 'WIT'])->default('WIB'); // Zona Waktu
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
