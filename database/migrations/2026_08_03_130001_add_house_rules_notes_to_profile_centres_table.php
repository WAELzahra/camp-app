<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_centres', function (Blueprint $table) {
            $table->text('house_rules_notes')->nullable()->after('public_transport_final_leg');
        });
    }

    public function down(): void
    {
        Schema::table('profile_centres', function (Blueprint $table) {
            $table->dropColumn('house_rules_notes');
        });
    }
};
