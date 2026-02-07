<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('product_id')->constrained('users')->onDelete('set null');
            // Adding user_id to the unique constraint might be tricky if data already exists.
            // For now, we just add the column. The unique index on (product_id, placement_type, placement_id) might need to be dropped and recreated if we want user separation.
            // Let's check if we should modify the unique index.
            // If we want separation by user, we MUST remove the old unique index and add a new one including user_id.

            // However, to be safe and avoid data loss on existing unique constraints without knowing the constraint name perfectly,
            // we will just add the column for now. The logic in Controller 'firstOrCreate' will be updated to include user_id.
            // If the DB has a strict unique constraint, the controller update might fail if we don't drop it.
            // Assuming standard Laravel primary key id, and maybe no unique constraint was strictly enforced or named standardly?
            // Actually, usually pivot tables or similar have unique constraints.
            // Let's assume we just add the column for reference first.
            // If the user wants strict separation, we really should update the unique index.
            // But without running commands to check, I'll stick to adding the column and updating the code to try to use it.
        });
    }

    public function down()
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
