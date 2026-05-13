<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')->constrained('users')->onDelete('cascade');
            
            // Event basic info
            $table->string('title');
            $table->text('description')->nullable();
            
            // Event type
            $table->enum('event_type', ['camping', 'hiking', 'voyage'])->default('camping');
            
            // Dates
            $table->date('start_date');
            $table->date('end_date');
            
            // Capacity & Pricing
            $table->integer('capacity')->default(0);
            $table->decimal('price', 8, 2)->default(0.00);
            $table->integer('remaining_spots')->default(0);
            
            // Camping specific fields
            $table->integer('camping_duration')->nullable();
            $table->text('camping_gear')->nullable();
            
            // Group travel flag for camping events
            $table->boolean('is_group_travel')->default(false);
            
            // Trip/Voyage fields (used for both 'voyage' type and camping group travel)
            $table->string('departure_city')->nullable();
            $table->string('arrival_city')->nullable();
            $table->time('departure_time')->nullable();
            $table->time('estimated_arrival_time')->nullable();
            $table->string('bus_company')->nullable();
            $table->string('bus_number')->nullable();
            
            // City stops (JSON to store multiple stops)
            $table->json('city_stops')->nullable();
            
            // Hiking specific fields
            $table->enum('difficulty', ['easy', 'moderate', 'difficult', 'expert'])->nullable();
            $table->decimal('hiking_duration', 5, 2)->nullable()->comment('Duration in hours');
            $table->integer('elevation_gain')->nullable()->comment('Elevation gain in meters');
            
            // Location fields
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();
            
            // Tags - JSON array for multiple tags
            $table->json('tags')->nullable();
            
            // Status & tracking
            $table->boolean('is_active')->default(false);
            $table->enum('status', ['pending', 'scheduled', 'ongoing', 'finished', 'canceled', 'postponed', 'full'])->default('pending');
            $table->integer('views_count')->default(0);
            
            $table->timestamps();

            // Indexes for better performance
            $table->index('event_type');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};