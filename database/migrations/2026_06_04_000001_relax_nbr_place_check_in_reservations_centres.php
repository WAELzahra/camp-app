<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the `nbr_place > 1` CHECK constraint that prevented solo bookings
 * (group_size = 1).  The application already enforces nbr_place >= 1 via
 * max(1, ...) in BookingPreparationService, so no DB-level check is needed.
 *
 * MySQL 8.0.16+ enforces CHECK constraints; older versions parse but ignore
 * them.  This migration handles both cases gracefully.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $rows = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'reservations_centres'
                  AND CONSTRAINT_TYPE = 'CHECK'
            ");

            foreach ($rows as $row) {
                DB::statement("ALTER TABLE `reservations_centres` DROP CHECK `{$row->CONSTRAINT_NAME}`");
            }
        } catch (\Throwable) {
            // MySQL < 8.0.16 does not enforce CHECK constraints — nothing to drop.
        }
    }

    public function down(): void
    {
        // Restore the original (incorrect) constraint only if explicitly rolled back.
        try {
            DB::statement(
                'ALTER TABLE `reservations_centres` ADD CONSTRAINT `reservations_centres_nbr_place_check` CHECK (`nbr_place` > 1)'
            );
        } catch (\Throwable) {
            // Ignore if the constraint already exists or MySQL doesn't support it.
        }
    }
};
