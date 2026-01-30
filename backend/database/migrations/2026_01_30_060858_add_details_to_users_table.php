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
        Schema::table('users', function (Blueprint $table) {
            // Relasi ke Cabang
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');

            // Detail Profil
            $table->string('full_name')->nullable();
            $table->string('username')->unique()->nullable(); // idlogin
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('photo')->nullable();

            // Status & UI
            $table->boolean('is_active')->default(true);
            $table->string('theme_color')->default('default'); // Menyimpan pilihan tema
            $table->timestamp('last_seen')->nullable(); // Akan mengikuti zona waktu cabang
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
