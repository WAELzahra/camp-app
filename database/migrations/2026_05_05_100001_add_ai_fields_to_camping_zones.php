<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camping_zones', function (Blueprint $table) {
            $table->boolean('is_beginner_friendly')->default(false)->after('danger_level');
            $table->enum('terrain_type', ['forest', 'mountain', 'desert', 'coastal', 'plain', 'wetland'])->nullable()->after('is_beginner_friendly');
            $table->tinyInteger('min_temp_celsius')->nullable()->after('terrain_type');
            $table->tinyInteger('max_temp_celsius')->nullable()->after('min_temp_celsius');
        });
    }

    public function down(): void
    {
        Schema::table('camping_zones', function (Blueprint $table) {
            $table->dropColumn(['is_beginner_friendly', 'terrain_type', 'min_temp_celsius', 'max_temp_celsius']);
        });
    }
};
