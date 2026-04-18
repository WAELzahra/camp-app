<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('camping_zones', 'image')) {
            return;
        }

        // Migrate any existing images into the photos table before dropping the column
        $zones = \DB::table('camping_zones')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->get(['id', 'image']);

        foreach ($zones as $zone) {
            $alreadyExists = \DB::table('photos')
                ->where('camping_zone_id', $zone->id)
                ->where('is_cover', true)
                ->exists();

            if (!$alreadyExists) {
                \DB::table('photos')->insert([
                    'path_to_img'     => $zone->image,
                    'camping_zone_id' => $zone->id,
                    'is_cover'        => true,
                    'order'           => 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        Schema::table('camping_zones', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('camping_zones', 'image')) {
            Schema::table('camping_zones', function (Blueprint $table) {
                $table->string('image')->nullable()->after('nom');
            });
        }
    }
};
