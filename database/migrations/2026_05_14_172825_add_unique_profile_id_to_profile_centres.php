<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate ProfileCentres before adding the constraint.
        // For each profile_id with duplicates, keep the row with the highest
        // price_per_night (most complete setup) and delete the rest.
        $duplicateProfileIds = DB::table('profile_centres')
            ->select('profile_id')
            ->groupBy('profile_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('profile_id');

        foreach ($duplicateProfileIds as $profileId) {
            $ids = DB::table('profile_centres')
                ->where('profile_id', $profileId)
                ->orderByDesc('price_per_night')
                ->orderBy('id')
                ->pluck('id');

            $keep = $ids->first();

            DB::table('profile_centres')
                ->where('profile_id', $profileId)
                ->where('id', '!=', $keep)
                ->delete();

            // Unlink any camping_centres that pointed to the deleted ProfileCentres
            // so they don't become orphaned with a broken profile_centre_id FK.
            DB::table('camping_centres')
                ->where('profile_centre_id', '!=', $keep)
                ->whereIn('profile_centre_id', $ids->slice(1)->values()->toArray())
                ->update(['profile_centre_id' => $keep]);
        }

        Schema::table('profile_centres', function (Blueprint $table) {
            $table->unique('profile_id', 'profile_centres_profile_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('profile_centres', function (Blueprint $table) {
            $table->dropUnique('profile_centres_profile_id_unique');
        });
    }
};
