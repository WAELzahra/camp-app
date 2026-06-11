<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Densifies camper favorites so the collaborative-filtering model has real
 * signal. The key is OVERLAP: campers of the same archetype favorite from the
 * same terrain-aligned zone pool, so "users like you also liked X" emerges.
 *
 * Each campeur gets ~12 zone favorites + a few gear favorites, drawn
 * deterministically from their archetype's terrain pool. Idempotent: skips
 * (user, favoritable) pairs that already exist.
 */
class DenseFavoritesSeeder extends Seeder
{
    /** archetype -> preferred terrain types */
    private const ARCH_TERRAINS = [
        'A' => ['mountain', 'forest'],
        'B' => ['coastal'],
        'C' => ['forest', 'mountain'],
        'D' => ['desert'],
        'E' => ['coastal', 'wetland'],
        'F' => ['mountain', 'forest'],
    ];

    public function run(): void
    {
        $now = now()->toDateTimeString();

        $campeurRoleId = DB::table('roles')->where('name', 'campeur')->value('id');
        $campeurs = DB::table('users')
            ->where('role_id', $campeurRoleId)
            ->select('id', 'preferences')
            ->get();

        // Terrain -> ordered zone-id pool (status=1). A fixed pool per terrain
        // means same-archetype campers overlap heavily.
        $zonesByTerrain = [];
        foreach (DB::table('camping_zones')->where('status', 1)->where('is_closed', 0)
                     ->select('id', 'terrain_type')->orderBy('id')->get() as $z) {
            $zonesByTerrain[$z->terrain_type ?? 'plain'][] = $z->id;
        }

        $gearIds = DB::table('materielles')->where('status', 'up')->orderBy('id')
            ->pluck('id')->all();

        // Existing pairs to avoid duplicates.
        $existing = [];
        foreach (DB::table('favorites')->select('user_id', 'favoritable_id', 'favoritable_type')->get() as $f) {
            $existing["{$f->user_id}:{$f->favoritable_type}:{$f->favoritable_id}"] = true;
        }

        $rows = [];
        foreach ($campeurs as $c) {
            $arch = json_decode($c->preferences, true)['archetype'] ?? 'C';
            $terrains = self::ARCH_TERRAINS[$arch] ?? ['forest'];

            // Build a candidate pool from the archetype terrains.
            $pool = [];
            foreach ($terrains as $t) {
                $pool = array_merge($pool, $zonesByTerrain[$t] ?? []);
            }
            if (empty($pool)) {
                continue;
            }

            // Pick ~12 zones. A small per-user offset keeps strong overlap while
            // adding slight individual variation.
            $count = 12;
            $offset = $c->id % 5;
            for ($k = 0; $k < $count; $k++) {
                $zid = $pool[($offset + $k) % count($pool)];
                $key = "{$c->id}:zone:{$zid}";
                if (! isset($existing[$key])) {
                    $existing[$key] = true;
                    $rows[] = ['user_id' => $c->id, 'favoritable_id' => $zid,
                               'favoritable_type' => 'zone', 'created_at' => $now, 'updated_at' => $now];
                }
            }

            // A few gear favorites too.
            if (! empty($gearIds)) {
                for ($k = 0; $k < 4; $k++) {
                    $gid = $gearIds[($c->id + $k) % count($gearIds)];
                    $key = "{$c->id}:equipment:{$gid}";
                    if (! isset($existing[$key])) {
                        $existing[$key] = true;
                        $rows[] = ['user_id' => $c->id, 'favoritable_id' => $gid,
                                   'favoritable_type' => 'equipment', 'created_at' => $now, 'updated_at' => $now];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('favorites')->insert($chunk);
        }
        $this->command?->info('✅ ' . count($rows) . ' favorites added (dense, archetype-aligned).');
    }
}
