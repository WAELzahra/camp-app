<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_centres', function (Blueprint $table) {
            // camping | gite | maison | auberge | ecolodge — null means "not declared yet"
            // (the API infers from the centre name until the owner/admin sets it).
            $table->string('host_type', 20)->nullable()->after('category')->index();
        });
    }

    public function down(): void
    {
        Schema::table('profile_centres', function (Blueprint $table) {
            $table->dropIndex(['host_type']);
            $table->dropColumn('host_type');
        });
    }
};
