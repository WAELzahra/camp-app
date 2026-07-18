<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE popups MODIFY popup_kind ENUM('engagement', 'welcome', 'tutorial') NOT NULL DEFAULT 'engagement'");

        Schema::table('popups', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('cta_url');
        });
    }

    public function down(): void
    {
        Schema::table('popups', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });

        DB::statement("ALTER TABLE popups MODIFY popup_kind ENUM('engagement', 'welcome') NOT NULL DEFAULT 'engagement'");
    }
};
