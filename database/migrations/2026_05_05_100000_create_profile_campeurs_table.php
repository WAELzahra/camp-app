<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_campeurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->onDelete('cascade');
            $table->enum('skill_level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('beginner');
            $table->enum('comfort_level', ['basic', 'standard', 'glamping'])->default('standard');
            $table->enum('budget_range', ['budget', 'moderate', 'premium'])->default('moderate');
            $table->json('preferred_trip_styles')->nullable();
            $table->json('preferred_activities')->nullable();
            $table->json('gear_preferences')->nullable();
            $table->unsignedSmallInteger('total_trips')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_campeurs');
    }
};
