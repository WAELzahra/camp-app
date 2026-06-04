<?php

namespace Database\Seeders;

use App\Models\CampingCentre;
use App\Models\ProfileCentre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Promotes seeded profile_centres into real PARTNER camping_centres — i.e. rows
 * in camping_centres linked to an owning user + profile_centre, public and
 * approved. Without this link the partner-booking flow has nothing to return
 * and always falls back to external/discovery centres.
 *
 * Idempotent: skips a profile centre that already has a linked camping_centre.
 */
class PartnerCentresSeeder extends Seeder
{
    public function run(): void
    {
        $now      = now();
        $profiles = ProfileCentre::all();
        $created  = 0;

        foreach ($profiles as $pc) {
            // Resolve the owning user via profile_centres → profiles → users
            $ownerUserId = DB::table('profiles')->where('id', $pc->profile_id)->value('user_id');
            if (! $ownerUserId) {
                continue;
            }

            // Already linked? leave it.
            if (CampingCentre::where('profile_centre_id', $pc->id)->exists()) {
                continue;
            }

            $region   = $this->regionFor($pc);
            $name     = $pc->name ?: 'Centre de camping';

            CampingCentre::create([
                'nom'               => $name,
                'type'              => 'centre',
                'description'       => 'Centre partenaire TunisiaCamp — réservable en ligne.',
                'adresse'           => $name . ', ' . $region,
                'lat'               => $pc->latitude,
                'lng'               => $pc->longitude,
                'image'             => null,
                'status'            => 1,
                'validation_status' => 'approved',
                'user_id'           => $ownerUserId,
                'profile_centre_id' => $pc->id,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $created++;
        }

        $this->command?->info("✅ {$created} partner camping_centres linked from profile_centres.");
    }

    /**
     * Best-effort region label from the profile centre's coordinates/name so the
     * adresse contains a region the planner can filter on.
     */
    private function regionFor(ProfileCentre $pc): string
    {
        $name = mb_strtolower($pc->name ?? '');
        $map  = [
            'cap bon'    => 'Nabeul',   'hammamet'  => 'Nabeul',
            'hammam'     => 'Bizerte',  'ain draham' => 'Jendouba', 'aïn draham' => 'Jendouba',
            'tabarka'    => 'Jendouba', 'douz'      => 'Kébili',    'nefta'      => 'Tozeur',
            'ksar ghilane' => 'Tataouine', 'béja'    => 'Béja',     'beja'       => 'Béja',
            'ichkeul'    => 'Bizerte',  'sousse'    => 'Sousse',    'zaghouan'   => 'Zaghouan',
            'sfax'       => 'Sfax',     'kairouan'  => 'Kairouan',
        ];
        foreach ($map as $kw => $region) {
            if (str_contains($name, $kw)) {
                return $region;
            }
        }

        // Fall back to a rough lat/lng → region guess
        $lat = (float) $pc->latitude;
        return match (true) {
            $lat >= 36.7 => 'Bizerte',
            $lat >= 36.3 => 'Nabeul',
            $lat >= 35.5 => 'Sousse',
            $lat >= 34.0 => 'Sfax',
            default      => 'Kébili',
        };
    }
}
