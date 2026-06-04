<?php

namespace Database\Seeders;

use App\Models\CampingCentre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Creates the full partner chain for every camping_centre that has
 * profile_centre_id IS NULL, then links the centre to the new records.
 *
 * Realistic data strategy
 * ───────────────────────
 * • lat/lng  → copied from camping_centres (accurate from CampingCentresSeeder)
 * • Category → derived from camping_centres.type first, then adresse region
 * • Prices   → rounded to nearest 5 TND; base range per accommodation type;
 *              per-centre variation uses a golden-ratio hash of the centre ID
 * • Equipment / services → per-type rules with deterministic per-centre variation
 *
 * NOTE: PHP's spread operator inside array literals re-indexes integer keys,
 * so all optional service rows are added with explicit if-blocks, never with
 * the inline ...(cond ? [key => val] : []) pattern.
 *
 * Idempotent: any centre with profile_centre_id NOT NULL is skipped.
 * Each centre is wrapped in its own transaction.
 */
class PartnerCentreSeeder extends Seeder
{
    /** Fallback prices/units used when service_categories has unexpected IDs. */
    private const SVC_DEFAULTS = [
        1  => ['price' => 15.00, 'unit' => 'person/night'],
        2  => ['price' => 80.00, 'unit' => 'night'],
        3  => ['price' => 20.00, 'unit' => 'person'],
        4  => ['price' => 30.00, 'unit' => 'person'],
        5  => ['price' => 35.00, 'unit' => 'person'],
        6  => ['price' => 50.00, 'unit' => 'night'],
        7  => ['price' => 15.00, 'unit' => 'night'],
        8  => ['price' => 75.00, 'unit' => 'person'],
        9  => ['price' => 25.00, 'unit' => 'day'],
        10 => ['price' => 40.00, 'unit' => 'person'],
        11 => ['price' => 10.00, 'unit' => 'day'],
    ];

    public function run(): void
    {
        $alreadyPartner = CampingCentre::whereNotNull('profile_centre_id')->count();

        $centres = CampingCentre::whereNull('profile_centre_id')->get();
        $total   = $centres->count();

        $serviceCats = DB::table('service_categories')
            ->whereIn('id', array_keys(self::SVC_DEFAULTS))
            ->get()
            ->keyBy('id')
            ->all();

        $centreRoleId = DB::table('roles')->where('id', 3)->value('id') ?? 3;

        $now     = now()->toDateTimeString();
        $created = 0;
        $failed  = 0;
        $i       = 0;

        foreach ($centres as $centre) {
            try {
                DB::transaction(function () use ($centre, $now, $serviceCats, $centreRoleId): void {
                    $this->processCentre($centre, $now, $serviceCats, $centreRoleId);
                });
                $created++;
            } catch (\Throwable $e) {
                Log::error('partner_centre_seeder_failed', [
                    'centre_id' => $centre->id,
                    'nom'       => $centre->nom,
                    'error'     => $e->getMessage(),
                ]);
                $failed++;
            }

            $i++;
            if ($i % 10 === 0) {
                $this->command->info("Processed {$i}/{$total} centres...");
            }
        }

        $this->command->info('✓ Partner Centre Seeder complete.');
        $this->command->info("  Created:  {$created} new partner centres");
        $this->command->info("  Skipped:  {$alreadyPartner} already partner");
        $this->command->info("  Failed:   {$failed} errors");
    }

    // ── Per-centre pipeline ────────────────────────────────────────────────────

    private function processCentre(
        CampingCentre $centre,
        string        $now,
        array         $serviceCats,
        int           $centreRoleId,
    ): void {
        $id      = $centre->id;
        $nom     = $centre->nom ?? 'Centre';
        $adresse = $centre->adresse ?? '';
        $type    = $centre->type ?? 'centre_camping';

        $category      = $this->resolveCategory($type, $adresse);
        $capacite      = $this->resolveCapacite($id, $type);
        $pricePerNight = $this->resolvePrice($id, $type, $category);
        $firstName     = $this->extractFirstName($nom);
        $ville         = $this->extractVille($adresse, $nom);
        $email         = Str::slug($nom) . '-' . $id . '@tunisiacamp-centre.tn';

        // Real coordinates already stored on camping_centres by CampingCentresSeeder
        $lat = $centre->lat !== null ? (float) $centre->lat : null;
        $lng = $centre->lng !== null ? (float) $centre->lng : null;

        // 1. User ─────────────────────────────────────────────────────────────
        $userId = DB::table('users')->insertGetId([
            'first_name'         => $firstName,
            'last_name'          => 'Camp',
            'email'              => $email,
            'email_verified_at'  => $now,
            'password'           => Hash::make('CentrePass2025!'),
            'role_id'            => $centreRoleId,
            'is_active'          => 1,
            'ville'              => $ville,
            'langue'             => 'fr',
            'first_login'        => 0,
            'nombre_signalement' => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        // 2. Profile ──────────────────────────────────────────────────────────
        $profileId = DB::table('profiles')->insertGetId([
            'user_id'    => $userId,
            'type'       => 'centre',
            'bio'        => null,
            'is_public'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. ProfileCentre ────────────────────────────────────────────────────
        $profileCentreId = DB::table('profile_centres')->insertGetId([
            'profile_id'       => $profileId,
            'name'             => $nom,
            'capacite'         => $capacite,
            'price_per_night'  => $pricePerNight,
            'category'         => $category,
            'disponibilite'    => 1,
            'latitude'         => $lat,
            'longitude'        => $lng,
            'contact_email'    => $email,
            'contact_phone'    => null,
            'manager_name'     => $firstName . ' Camp',
            'established_date' => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        // 4. Equipment ────────────────────────────────────────────────────────
        $this->createEquipment($profileCentreId, $id, $type, $category, $nom, $adresse, $now);

        // 5. Services ─────────────────────────────────────────────────────────
        $this->createServices($profileCentreId, $id, $type, $category, $now, $serviceCats);

        // 6. Link the camping_centre ──────────────────────────────────────────
        DB::table('camping_centres')->where('id', $id)->update([
            'user_id'           => $userId,
            'profile_centre_id' => $profileCentreId,
            'status'            => 1,
            'validation_status' => 'approved',
            'updated_at'        => $now,
        ]);
    }

    // ── Category resolution ────────────────────────────────────────────────────

    private function resolveCategory(string $type, string $adresse): string
    {
        return match ($type) {
            'glamping'                    => 'glamping',
            'campement_desert', 'bivouac' => 'desert',
            'gite_rural', 'ferme_agricole' => 'nature',
            'maison_hotes'                => $this->regionCategory($adresse) ?? 'lodge',
            default                       => $this->regionCategory($adresse) ?? 'camping',
        };
    }

    private function regionCategory(string $adresse): ?string
    {
        $lower = mb_strtolower($adresse);

        if (str_contains($lower, 'jendouba') || str_contains($lower, 'tabarka')
            || str_contains($lower, 'ain draham') || str_contains($lower, 'aïn draham')
            || str_contains($lower, 'béja') || str_contains($lower, 'beja')
            || str_contains($lower, 'nefza') || str_contains($lower, 'siliana')
            || str_contains($lower, 'mogods') || str_contains($lower, 'kroumirie')) {
            return 'nature';
        }
        if (str_contains($lower, 'bizerte') || str_contains($lower, 'ghar el melh')
            || str_contains($lower, 'sidi mechreg') || str_contains($lower, 'rafraf')
            || str_contains($lower, 'cap nord')) {
            return 'coastal';
        }
        if (str_contains($lower, 'nabeul') || str_contains($lower, 'hammamet')
            || str_contains($lower, 'kelibia') || str_contains($lower, 'kélibia')
            || str_contains($lower, 'sousse') || str_contains($lower, 'monastir')
            || str_contains($lower, 'mahdia') || str_contains($lower, 'sfax')
            || str_contains($lower, 'cap bon') || str_contains($lower, 'korbous')) {
            return 'beach';
        }
        if (str_contains($lower, 'tozeur') || str_contains($lower, 'nefta')
            || str_contains($lower, 'douz') || str_contains($lower, 'kébili')
            || str_contains($lower, 'kebili') || str_contains($lower, 'sabria')
            || str_contains($lower, 'faouar') || str_contains($lower, 'ghidma')
            || str_contains($lower, 'gafsa') || str_contains($lower, 'metlaoui')
            || str_contains($lower, 'tataouine') || str_contains($lower, 'ksar ghilane')) {
            return 'desert';
        }
        if (str_contains($lower, 'tunis') || str_contains($lower, 'sidi bou')
            || str_contains($lower, 'zaghouan') || str_contains($lower, 'kairouan')
            || str_contains($lower, 'manouba') || str_contains($lower, 'ariana')
            || str_contains($lower, 'mornag') || str_contains($lower, 'ben arous')) {
            return 'lodge';
        }
        return null;
    }

    // ── Capacity ──────────────────────────────────────────────────────────────

    private function resolveCapacite(int $id, string $type): int
    {
        [$min, $max] = match ($type) {
            'glamping'         => [8,  22],
            'campement_desert' => [12, 36],
            'bivouac'          => [8,  24],
            'maison_hotes'     => [6,  18],
            'gite_rural'       => [6,  14],
            'ferme_agricole'   => [8,  24],
            default            => [20, 70],
        };

        return $this->varyInt($id * 3, $min, $max);
    }

    // ── Price per night ────────────────────────────────────────────────────────

    private function resolvePrice(int $id, string $type, string $category): float
    {
        [$min, $max] = match ($type) {
            'glamping'         => [90.0,  185.0],
            'campement_desert' => [60.0,  130.0],
            'bivouac'          => [50.0,  100.0],
            'maison_hotes'     => [70.0,  200.0],
            'gite_rural'       => [50.0,  115.0],
            'ferme_agricole'   => [40.0,   85.0],
            default            => [20.0,   65.0],
        };

        if ($category === 'desert' && !in_array($type, ['glamping', 'maison_hotes'], true)) {
            $min += 10.0;
            $max += 25.0;
        }

        return round($this->varyFloat($id * 7, $min, $max) / 5) * 5.0;
    }

    // ── Equipment ─────────────────────────────────────────────────────────────

    private function createEquipment(
        int    $profileCentreId,
        int    $id,
        string $type,
        string $category,
        string $nom,
        string $adresse,
        string $now,
    ): void {
        $equipment = match ($type) {
            'glamping' => [
                'toilets'        => 1,
                'drinking_water' => 1,
                'parking'        => 1,
                'showers'        => 1,
                'electricity'    => 1,
                'wifi'           => 1,
                'kitchen'        => 1,
                'bbq_area'       => 1,
                'swimming_pool'  => $this->prob($id, 60) ? 1 : 0,
                'security'       => $this->prob($id * 3, 55) ? 1 : 0,
            ],
            'campement_desert', 'bivouac' => [
                'toilets'        => 1,
                'drinking_water' => 1,
                'parking'        => 1,
                'security'       => 1,
                'electricity'    => $this->prob($id, 25) ? 1 : 0,
                'wifi'           => $this->prob($id * 5, 28) ? 1 : 0,
                'showers'        => $this->prob($id * 7, 52) ? 1 : 0,
                'bbq_area'       => $this->prob($id * 11, 68) ? 1 : 0,
            ],
            'maison_hotes' => [
                'toilets'        => 1,
                'drinking_water' => 1,
                'parking'        => 1,
                'showers'        => 1,
                'electricity'    => 1,
                'wifi'           => 1,
                'kitchen'        => $this->prob($id, 62) ? 1 : 0,
                'swimming_pool'  => $this->prob($id * 3, 38) ? 1 : 0,
                'bbq_area'       => $this->prob($id * 5, 48) ? 1 : 0,
                'security'       => $this->prob($id * 7, 44) ? 1 : 0,
            ],
            'gite_rural' => [
                'toilets'        => 1,
                'drinking_water' => 1,
                'parking'        => 1,
                'showers'        => 1,
                'electricity'    => 1,
                'wifi'           => $this->prob($id, 68) ? 1 : 0,
                'bbq_area'       => $this->prob($id * 3, 63) ? 1 : 0,
                'kitchen'        => $this->prob($id * 7, 52) ? 1 : 0,
            ],
            'ferme_agricole' => [
                'toilets'        => 1,
                'drinking_water' => 1,
                'parking'        => 1,
                'electricity'    => 1,
                'bbq_area'       => 1,
                'showers'        => $this->prob($id, 72) ? 1 : 0,
                'wifi'           => $this->prob($id * 3, 58) ? 1 : 0,
                'kitchen'        => $this->prob($id * 5, 44) ? 1 : 0,
                'swimming_pool'  => $this->prob($id * 9, 22) ? 1 : 0,
            ],
            default => $this->campingEquipment($id, $category),
        };

        // Authoritative keyword overrides from the stored address / name
        $combined = mb_strtolower($nom . ' ' . $adresse);
        if (str_contains($combined, 'piscine') || str_contains($combined, 'pool')) {
            $equipment['swimming_pool'] = 1;
        }
        if (str_contains($combined, 'wifi') || str_contains($combined, 'internet')) {
            $equipment['wifi'] = 1;
        }
        if (str_contains($combined, 'restaurant') || str_contains($combined, 'cuisine')) {
            $equipment['kitchen'] = max($equipment['kitchen'] ?? 0, 1);
        }

        $rows = [];
        foreach ($equipment as $eqType => $isAvailable) {
            $rows[] = [
                'profile_center_id' => $profileCentreId,
                'type'              => $eqType,
                'is_available'      => $isAvailable,
                'notes'             => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        DB::table('profile_center_equipment')->insert($rows);
    }

    private function campingEquipment(int $id, string $category): array
    {
        $base = ['toilets' => 1, 'drinking_water' => 1, 'parking' => 1];

        switch ($category) {
            case 'coastal':
                $base['showers']     = 1;
                $base['security']    = 1;
                $base['bbq_area']    = $this->prob($id, 58) ? 1 : 0;
                $base['wifi']        = $this->prob($id * 3, 48) ? 1 : 0;
                $base['electricity'] = $this->prob($id * 5, 52) ? 1 : 0;
                break;

            case 'beach':
                $base['showers']     = 1;
                $base['security']    = $this->prob($id, 65) ? 1 : 0;
                $base['bbq_area']    = $this->prob($id * 3, 55) ? 1 : 0;
                $base['wifi']        = $this->prob($id * 5, 50) ? 1 : 0;
                $base['electricity'] = $this->prob($id * 7, 58) ? 1 : 0;
                break;

            case 'nature':
                $base['bbq_area']    = 1;
                $base['electricity'] = $this->prob($id, 38) ? 1 : 0;
                $base['wifi']        = $this->prob($id * 3, 42) ? 1 : 0;
                $base['showers']     = $this->prob($id * 7, 52) ? 1 : 0;
                $base['security']    = $this->prob($id * 11, 32) ? 1 : 0;
                break;

            case 'desert':
                $base['security']    = 1;
                $base['electricity'] = 0;
                $base['wifi']        = $this->prob($id, 18) ? 1 : 0;
                $base['showers']     = $this->prob($id * 3, 32) ? 1 : 0;
                $base['bbq_area']    = $this->prob($id * 5, 62) ? 1 : 0;
                break;

            case 'lodge':
                $base['showers']     = 1;
                $base['electricity'] = 1;
                $base['wifi']        = 1;
                $base['bbq_area']    = $this->prob($id, 52) ? 1 : 0;
                $base['kitchen']     = $this->prob($id * 3, 45) ? 1 : 0;
                $base['security']    = $this->prob($id * 5, 38) ? 1 : 0;
                break;

            default: // 'camping'
                $base['bbq_area']    = $this->prob($id, 62) ? 1 : 0;
                $base['electricity'] = $this->prob($id * 3, 48) ? 1 : 0;
                $base['wifi']        = $this->prob($id * 5, 38) ? 1 : 0;
                $base['showers']     = $this->prob($id * 7, 52) ? 1 : 0;
                break;
        }

        return $base;
    }

    // ── Services ──────────────────────────────────────────────────────────────

    /**
     * Build and insert service rows.
     *
     * IMPORTANT: PHP's spread operator (...) in array literals re-indexes integer
     * keys. All conditional services are added with explicit `if` blocks on the
     * `$services` array, never with inline spread expressions.
     *
     * Row layout: $services[catId] = [is_standard, is_available, float_price]
     */
    private function createServices(
        int    $profileCentreId,
        int    $id,
        string $type,
        string $category,
        string $now,
        array  $serviceCats,
    ): void {
        $services = [];

        switch ($type) {
            case 'glamping':
                $services[1]  = [1, 1, $this->vf($id,      25.0,  45.0)]; // Basic Camping std
                $services[2]  = [1, 1, $this->vf($id * 3,  90.0, 160.0)]; // Cabin Rental std
                $services[3]  = [1, 1, $this->vf($id * 5,  25.0,  45.0)]; // Breakfast std
                $services[5]  = [1, 1, $this->vf($id * 7,  45.0,  78.0)]; // Dinner std
                $services[4]  = [0, 1, $this->vf($id * 9,  38.0,  58.0)]; // Lunch
                $services[8]  = [0, 1, $this->vf($id * 11, 80.0, 135.0)]; // Guided Tour
                $services[9]  = [0, 1, $this->vf($id * 13, 25.0,  40.0)]; // BBQ
                if ($this->prob($id, 55)) {
                    $services[10] = [0, 1, $this->vf($id * 17, 40.0, 72.0)]; // Transport
                }
                break;

            case 'campement_desert':
            case 'bivouac':
                $services[1]  = [1, 1, $this->vf($id,      20.0,  40.0)]; // Basic Camping std
                $services[8]  = [1, 1, $this->vf($id * 3,  70.0, 145.0)]; // Guided Tour std
                $services[10] = [1, 1, $this->vf($id * 5,  40.0,  82.0)]; // Transport std
                $services[5]  = [0, 1, $this->vf($id * 7,  35.0,  62.0)]; // Dinner
                $services[9]  = [0, 1, $this->vf($id * 9,  20.0,  35.0)]; // BBQ
                if ($this->prob($id * 3, 62)) {
                    $services[3] = [0, 1, $this->vf($id * 11, 20.0, 35.0)]; // Breakfast
                }
                if ($this->prob($id * 5, 40)) {
                    $services[6] = [0, 1, $this->vf($id * 13, 45.0, 75.0)]; // Tent Rental
                }
                break;

            case 'maison_hotes':
                $services[1]  = [1, 1, $this->vf($id,      18.0, 35.0)]; // Basic Camping std
                $services[3]  = [1, 1, $this->vf($id * 3,  20.0, 40.0)]; // Breakfast std
                $services[11] = [0, 1, $this->vf($id * 11,  8.0, 15.0)]; // Chair
                if ($this->prob($id, 58)) {
                    $services[5] = [1, 1, $this->vf($id * 5,  38.0, 68.0)]; // Dinner std
                }
                if ($this->prob($id * 3, 45)) {
                    $services[8] = [0, 1, $this->vf($id * 7,  65.0, 115.0)]; // Guided Tour
                }
                if ($this->prob($id * 5, 42)) {
                    $services[4] = [0, 1, $this->vf($id * 9,  28.0, 50.0)]; // Lunch
                }
                break;

            case 'gite_rural':
                $services[1]  = [1, 1, $this->vf($id,      15.0, 28.0)]; // Basic Camping std
                $services[3]  = [1, 1, $this->vf($id * 3,  18.0, 30.0)]; // Breakfast std
                $services[6]  = [0, 1, $this->vf($id * 5,  45.0, 72.0)]; // Tent Rental
                $services[7]  = [0, 1, $this->vf($id * 7,  12.0, 22.0)]; // Sleeping Bag
                $services[9]  = [0, 1, $this->vf($id * 9,  20.0, 32.0)]; // BBQ
                if ($this->prob($id, 62)) {
                    $services[8] = [0, 1, $this->vf($id * 11, 60.0, 105.0)]; // Guided Tour
                }
                if ($this->prob($id * 3, 38)) {
                    $services[4] = [0, 1, $this->vf($id * 13, 25.0, 42.0)]; // Lunch
                }
                break;

            case 'ferme_agricole':
                $services[1]  = [1, 1, $this->vf($id,      12.0, 25.0)]; // Basic Camping std
                $services[3]  = [1, 1, $this->vf($id * 3,  18.0, 30.0)]; // Breakfast std
                $services[9]  = [0, 1, $this->vf($id * 5,  18.0, 30.0)]; // BBQ
                $services[11] = [0, 1, $this->vf($id * 7,   8.0, 13.0)]; // Chair
                if ($this->prob($id, 52)) {
                    $services[8] = [0, 1, $this->vf($id * 9,  50.0, 90.0)]; // Guided Tour
                }
                if ($this->prob($id * 3, 40)) {
                    $services[4] = [0, 1, $this->vf($id * 11, 25.0, 45.0)]; // Lunch
                }
                break;

            default: // centre_camping — region-driven
                $services[1]  = [1, 1, $this->vf($id,     12.0, 30.0)]; // Basic Camping std
                $services[9]  = [0, 1, $this->vf($id * 3, 20.0, 35.0)]; // BBQ
                $services[11] = [0, 1, $this->vf($id * 5,  8.0, 14.0)]; // Chair

                if ($category === 'nature') {
                    $services[8] = [0, 1, $this->vf($id * 7,  58.0, 100.0)]; // Guided Tour
                    $services[6] = [0, 1, $this->vf($id * 9,  45.0,  70.0)]; // Tent Rental
                    $services[7] = [0, 1, $this->vf($id * 11, 12.0,  22.0)]; // Sleeping Bag
                } elseif ($category === 'coastal' || $category === 'beach') {
                    $services[3]  = [0, 1, $this->vf($id * 7, 18.0, 32.0)]; // Breakfast
                    $services[10] = [0, 1, $this->vf($id * 9, 35.0, 65.0)]; // Transport
                } elseif ($category === 'desert') {
                    $services[8]  = [1, 1, $this->vf($id * 7,  65.0, 125.0)]; // Guided Tour std
                    $services[10] = [1, 1, $this->vf($id * 9,  40.0,  78.0)]; // Transport std
                    $services[5]  = [0, 1, $this->vf($id * 11, 35.0,  60.0)]; // Dinner
                } elseif ($category === 'lodge') {
                    $services[3] = [1, 1, $this->vf($id * 7, 22.0, 42.0)]; // Breakfast std
                    $services[5] = [1, 1, $this->vf($id * 9, 40.0, 68.0)]; // Dinner std
                    if ($this->prob($id * 3, 45)) {
                        $services[8] = [0, 1, $this->vf($id * 11, 60.0, 100.0)]; // Guided Tour
                    }
                }
                break;
        }

        $rows = [];
        foreach ($services as $catId => [$isStandard, $isAvailable, $rawPrice]) {
            $cat   = $serviceCats[$catId] ?? null;
            $unit  = $cat?->unit ?? self::SVC_DEFAULTS[$catId]['unit'];
            $price = round($rawPrice * 2) / 2; // round to nearest 0.5 TND

            $rows[] = [
                'profile_center_id'   => $profileCentreId,
                'service_category_id' => $catId,
                'name'                => null,
                'price'               => $price,
                'unit'                => $unit,
                'description'         => null,
                'is_available'        => $isAvailable,
                'is_standard'         => $isStandard,
                'nbr_place'           => 1,
                'min_quantity'        => 1,
                'max_quantity'        => null,
                'is_refundable'       => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        DB::table('profile_center_services')->insert($rows);
    }

    // ── Deterministic variation helpers ───────────────────────────────────────

    private function varyFloat(int $seed, float $min, float $max): float
    {
        $r = fmod(abs($seed) * 0.6180339887, 1.0);
        return $min + ($max - $min) * $r;
    }

    /** Shorthand alias used in switch/if blocks. */
    private function vf(int $seed, float $min, float $max): float
    {
        return $this->varyFloat($seed, $min, $max);
    }

    private function varyInt(int $seed, int $min, int $max): int
    {
        $r = fmod(abs($seed) * 0.6180339887, 1.0);
        return (int) round($min + ($max - $min) * $r);
    }

    private function prob(int $seed, int $pct): bool
    {
        return (fmod(abs($seed) * 0.6180339887, 1.0) * 100) < $pct;
    }

    // ── String helpers ────────────────────────────────────────────────────────

    private function extractFirstName(string $nom): string
    {
        $parts = preg_split('/\s+/', trim($nom), 2);
        return $parts[0] ?? 'Centre';
    }

    private function extractVille(string $adresse, string $nomFallback): string
    {
        $govMap = [
            'ain draham'   => 'Jendouba',   'aïn draham'   => 'Jendouba',
            'ksar ghilane' => 'Tataouine',  'ghar el melh' => 'Bizerte',
            'sidi mechreg' => 'Bizerte',    'sidi bou'     => 'Tunis',
            'ben arous'    => 'Ben Arous',  'el faouar'    => 'Kébili',
            'el battan'    => 'Manouba',    'tabarka'      => 'Jendouba',
            'jendouba'     => 'Jendouba',   'bizerte'      => 'Bizerte',
            'hammamet'     => 'Nabeul',     'kelibia'      => 'Nabeul',
            'kélibia'      => 'Nabeul',     'korbous'      => 'Nabeul',
            'nabeul'       => 'Nabeul',     'zaghouan'     => 'Zaghouan',
            'sousse'       => 'Sousse',     'monastir'     => 'Monastir',
            'mahdia'       => 'Mahdia',     'salakta'      => 'Mahdia',
            'sfax'         => 'Sfax',       'kairouan'     => 'Kairouan',
            'siliana'      => 'Siliana',    'béja'         => 'Béja',
            'beja'         => 'Béja',       'nefza'        => 'Béja',
            'testour'      => 'Béja',       'tozeur'       => 'Tozeur',
            'nefta'        => 'Tozeur',     'metlaoui'     => 'Gafsa',
            'gafsa'        => 'Gafsa',      'douz'         => 'Kébili',
            'sabria'       => 'Kébili',     'ghidma'       => 'Kébili',
            'kébili'       => 'Kébili',     'kebili'       => 'Kébili',
            'gabès'        => 'Gabès',      'gabes'        => 'Gabès',
            'matmata'      => 'Gabès',      'tamezret'     => 'Gabès',
            'médenine'     => 'Médenine',   'medenine'     => 'Médenine',
            'djerba'       => 'Médenine',   'jerba'        => 'Médenine',
            'tataouine'    => 'Tataouine',  'chenini'      => 'Tataouine',
            'manouba'      => 'Manouba',    'mornag'       => 'Ben Arous',
            'ariana'       => 'Ariana',     'rafraf'       => 'Bizerte',
            'kef'          => 'Kef',        'tunis'        => 'Tunis',
        ];

        $lower = mb_strtolower($adresse);
        foreach ($govMap as $keyword => $ville) {
            if (str_contains($lower, $keyword)) {
                return $ville;
            }
        }

        $parts = preg_split('/\s+/', trim($nomFallback), 2);
        return $parts[0] ?? 'Tunisie';
    }
}
