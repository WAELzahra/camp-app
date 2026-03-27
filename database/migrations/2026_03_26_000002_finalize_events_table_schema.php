<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Convert existing data to valid enum values before MODIFY
        // Hiking-type categories → 'hiking'
        DB::statement("
            UPDATE events
            SET event_type = 'hiking'
            WHERE event_type IN ('Randonnée','Escalade','Ski','VTT','Observation','Expédition','Escalad','Randonn\u00e9e')
               OR event_type LIKE 'Randonn%'
               OR event_type LIKE 'Escalad%'
        ");
        // Voyage-type → 'voyage'
        DB::statement("
            UPDATE events
            SET event_type = 'voyage'
            WHERE event_type IN ('Kayak','Expédition','Expédition')
        ");
        // Everything else → 'camping'
        DB::statement("
            UPDATE events
            SET event_type = 'camping'
            WHERE event_type NOT IN ('camping','hiking','voyage')
        ");

        // 2. Change event_type to ENUM
        DB::statement("ALTER TABLE events MODIFY `event_type` ENUM('camping','hiking','voyage') NOT NULL DEFAULT 'camping'");

        // 3. Extend status enum to include 'ongoing'
        DB::statement("ALTER TABLE events MODIFY `status` ENUM('pending','scheduled','ongoing','finished','canceled','postponed','full') NOT NULL DEFAULT 'pending'");

        // 4. Add new columns
        Schema::table('events', function (Blueprint $table) {
            // Camping
            $table->integer('camping_duration')->nullable()->after('remaining_spots');
            $table->text('camping_gear')->nullable()->after('camping_duration');
            $table->boolean('is_group_travel')->default(false)->after('camping_gear');
            // Voyage
            $table->string('departure_city')->nullable()->after('is_group_travel');
            $table->string('arrival_city')->nullable()->after('departure_city');
            $table->time('departure_time')->nullable()->after('arrival_city');
            $table->time('estimated_arrival_time')->nullable()->after('departure_time');
            $table->string('bus_company')->nullable()->after('estimated_arrival_time');
            $table->string('bus_number')->nullable()->after('bus_company');
            // Hiking
            $table->enum('difficulty', ['easy', 'moderate', 'difficult', 'expert'])->nullable()->after('bus_number');
            $table->decimal('hiking_duration', 5, 2)->nullable()->after('difficulty');
            $table->integer('elevation_gain')->nullable()->after('hiking_duration');
            // Location
            $table->decimal('latitude', 10, 8)->nullable()->after('elevation_gain');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('address')->nullable()->after('longitude');
            // Tracking
            $table->integer('views_count')->default(0)->after('address');
            // Indexes
            $table->index('event_type');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            $table->dropColumn([
                'camping_duration', 'camping_gear', 'is_group_travel',
                'departure_city', 'arrival_city', 'departure_time', 'estimated_arrival_time',
                'bus_company', 'bus_number',
                'difficulty', 'hiking_duration', 'elevation_gain',
                'latitude', 'longitude', 'address', 'views_count',
            ]);
        });

        DB::statement("ALTER TABLE events MODIFY `status` ENUM('pending','scheduled','finished','canceled','postponed','full') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE events MODIFY `event_type` VARCHAR(255) NULL");
    }
};
