<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // New: what is being reported
            $table->enum('target_type', ['user', 'center', 'group', 'supplier', 'zone', 'platform'])
                  ->default('platform')
                  ->after('report_type');

            // New: ID of the reported entity (nullable - not needed for zone/platform)
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');

            // New: geographic coordinates for dangerous zone reports
            $table->decimal('location_lat', 10, 7)->nullable()->after('target_id');
            $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['target_type', 'target_id', 'location_lat', 'location_lng']);
        });
    }
};
