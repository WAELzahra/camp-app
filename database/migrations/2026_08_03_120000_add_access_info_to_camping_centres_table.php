<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camping_centres', function (Blueprint $table) {
            // paved | unpaved_2wd_ok | 4x4_required | hiking_only — null means "not declared yet".
            $table->string('road_condition', 20)->nullable()->after('lng');
            $table->text('road_access_notes')->nullable()->after('road_condition');

            $table->boolean('public_transport_accessible')->nullable()->after('road_access_notes');
            $table->text('public_transport_notes')->nullable()->after('public_transport_accessible');
            // at_entrance | short_walk | additional_transport_needed — null means "not declared yet".
            $table->string('public_transport_final_leg', 30)->nullable()->after('public_transport_notes');
        });
    }

    public function down(): void
    {
        Schema::table('camping_centres', function (Blueprint $table) {
            $table->dropColumn([
                'road_condition',
                'road_access_notes',
                'public_transport_accessible',
                'public_transport_notes',
                'public_transport_final_leg',
            ]);
        });
    }
};
