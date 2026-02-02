<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->enum('type', ['physical', 'online'])->default('physical')->after('id');
            $table->string('platform')->nullable()->after('type'); // shopee, tiktok
            $table->string('url')->nullable()->after('platform');
            $table->string('api_key')->nullable()->after('url');
            $table->string('api_secret')->nullable()->after('api_key');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('code_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['type', 'platform', 'url', 'api_key', 'api_secret']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('code_id');
        });
    }
};
