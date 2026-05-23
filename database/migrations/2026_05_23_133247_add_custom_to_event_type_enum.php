<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'custom' to the event_type ENUM on the events table.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE events MODIFY COLUMN event_type ENUM('camping','hiking','voyage','custom') NOT NULL DEFAULT 'camping'");
    }

    /**
     * Reverse: remove 'custom' (migrate existing rows to 'camping' first).
     */
    public function down(): void
    {
        DB::statement("UPDATE events SET event_type = 'camping' WHERE event_type = 'custom'");
        DB::statement("ALTER TABLE events MODIFY COLUMN event_type ENUM('camping','hiking','voyage') NOT NULL DEFAULT 'camping'");
    }
};
