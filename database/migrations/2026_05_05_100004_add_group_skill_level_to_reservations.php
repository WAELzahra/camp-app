<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->enum('group_skill_level', ['beginner', 'intermediate', 'advanced', 'mixed'])->nullable()->after('note');
            $table->string('trip_purpose')->nullable()->after('group_skill_level');
        });

        Schema::table('reservations_events', function (Blueprint $table) {
            $table->enum('group_skill_level', ['beginner', 'intermediate', 'advanced', 'mixed'])->nullable()->after('phone');
            $table->string('trip_purpose')->nullable()->after('group_skill_level');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropColumn(['group_skill_level', 'trip_purpose']);
        });

        Schema::table('reservations_events', function (Blueprint $table) {
            $table->dropColumn(['group_skill_level', 'trip_purpose']);
        });
    }
};
