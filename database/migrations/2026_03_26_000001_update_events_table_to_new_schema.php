<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign key on circuit_id before altering
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign('events_circuit_id_foreign');
        });

        // 2. Rename columns via CHANGE (MySQL 5.7 compatible — preserves data)
        DB::statement('ALTER TABLE events CHANGE `category` `event_type` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE events CHANGE `date_sortie` `start_date` DATE NOT NULL');
        DB::statement('ALTER TABLE events CHANGE `date_retoure` `end_date` DATE NOT NULL');
        DB::statement('ALTER TABLE events CHANGE `ville_passente` `city_stops` JSON NULL');
        DB::statement('ALTER TABLE events CHANGE `nbr_place_total` `capacity` INT(11) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE events CHANGE `nbr_place_restante` `remaining_spots` INT(11) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE events CHANGE `prix_place` `price` DECIMAL(8,2) NOT NULL DEFAULT 0.00');

        // 3. Drop circuit_id
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('circuit_id');
        });

        // 4. Fix column types via raw SQL
        DB::statement("ALTER TABLE events MODIFY `event_type` ENUM('camping','hiking','voyage') NOT NULL DEFAULT 'camping'");
        DB::statement("ALTER TABLE events MODIFY `status` ENUM('pending','scheduled','ongoing','finished','canceled','postponed','full') NOT NULL DEFAULT 'pending'");

        // 5. Add new columns
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
            $table->dropColumn([
                'camping_duration', 'camping_gear', 'is_group_travel',
                'departure_city', 'arrival_city', 'departure_time', 'estimated_arrival_time',
                'bus_company', 'bus_number',
                'difficulty', 'hiking_duration', 'elevation_gain',
                'latitude', 'longitude', 'address',
                'views_count',
            ]);
        });

        DB::statement('ALTER TABLE events CHANGE `event_type` `category` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE events CHANGE `start_date` `date_sortie` DATE NOT NULL');
        DB::statement('ALTER TABLE events CHANGE `end_date` `date_retoure` DATE NOT NULL');
        DB::statement('ALTER TABLE events CHANGE `city_stops` `ville_passente` JSON NULL');
        DB::statement('ALTER TABLE events CHANGE `capacity` `nbr_place_total` INT(11) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE events CHANGE `remaining_spots` `nbr_place_restante` INT(11) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE events CHANGE `price` `prix_place` DECIMAL(8,2) NOT NULL DEFAULT 0.00');

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('circuit_id')->nullable()->constrained('circuits')->onDelete('set null');
        });
    }
};
