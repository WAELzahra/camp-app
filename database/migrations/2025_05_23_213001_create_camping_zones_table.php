<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camping_zones', function (Blueprint $table) {
            $table->id();

            $table->string('nom');
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('commune')->nullable();
            $table->text('description')->nullable();
            $table->text('full_description')->nullable();

            $table->string('terrain')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('adresse')->nullable();
            $table->string('distance')->nullable();
            $table->string('altitude')->nullable();
            $table->enum('access_type', ['road', 'trail', 'boat', 'mixed'])->nullable();
            $table->string('accessibility')->nullable();

            $table->decimal('rating', 3, 1)->default(0);
            $table->integer('reviews_count')->default(0);

            $table->json('best_season')->nullable();
            $table->json('activities')->nullable();

            $table->json('facilities')->nullable();
            $table->json('rules')->nullable();

            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_website')->nullable();

            $table->boolean('is_public')->default(true);
            $table->boolean('status')->default(false);
            $table->boolean('is_protected_area')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->string('closure_reason')->nullable();
            $table->date('closure_start')->nullable();
            $table->date('closure_end')->nullable();

            $table->enum('danger_level', ['low', 'moderate', 'high', 'extreme'])->default('low');
            $table->integer('max_capacity')->nullable();

            $table->integer('map_zoom_level')->default(14);
            $table->json('polygon_coordinates')->nullable();

            $table->json('emergency_contacts')->nullable();
            $table->string('weather_station_id')->nullable();
            $table->timestamp('last_weather_update')->nullable();

            $table->string('source')->default('interne');
            $table->foreignId('centre_id')->nullable()->constrained('camping_centres')->nullOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camping_zones');
    }
};