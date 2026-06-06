<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camping_zones', function (Blueprint $table) {
            $table->string('type_activitee', 100)->nullable()->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('camping_zones', function (Blueprint $table) {
            $table->dropColumn('type_activitee');
        });
    }
};
