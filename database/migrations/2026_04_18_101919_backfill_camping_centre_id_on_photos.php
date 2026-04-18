<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-repair migration.
 *
 * Photos uploaded by centre-role users (role_id = 3) must ALWAYS carry
 * camping_centre_id.  Before this fix, storeOrUpdateProfilePhotos set only
 * user_id + album_id, leaving camping_centre_id NULL.  That broke every
 * query that fetches centre images by camping_centre_id.
 *
 * Logic:
 *   For every photo WHERE camping_centre_id IS NULL AND user_id IS NOT NULL:
 *     find their linked camping_centre and stamp camping_centre_id on the photo.
 *
 * down() is intentionally a no-op — this is a one-way data fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Map user_id => camping_centre_id for all centre owners
        $centreOwners = DB::table('camping_centres')
            ->whereNotNull('user_id')
            ->pluck('id', 'user_id'); // collection keyed by user_id

        if ($centreOwners->isEmpty()) {
            return;
        }

        foreach ($centreOwners as $userId => $campingCentreId) {
            DB::table('photos')
                ->where('user_id', $userId)
                ->whereNull('camping_centre_id')
                ->update(['camping_centre_id' => $campingCentreId]);
        }
    }

    public function down(): void
    {
        // Intentionally empty — do not reverse a data-repair migration.
    }
};
