<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('camping_centres', function (Blueprint $table) {
            $table->boolean('is_partner')->default(false)->after('validation_status');
        });

        // Backfill: mark existing partner centres based on the old computed logic
        DB::statement("
            UPDATE camping_centres
            SET is_partner = 1
            WHERE validation_status = 'approved'
               OR profile_centre_id IS NOT NULL
        ");

        // Also mark centres whose linked user is active
        DB::statement("
            UPDATE camping_centres cc
            INNER JOIN users u ON cc.user_id = u.id AND u.is_active = 1
            SET cc.is_partner = 1
        ");
    }

    public function down(): void {
        Schema::table('camping_centres', function (Blueprint $table) {
            $table->dropColumn('is_partner');
        });
    }
};
