<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerCentreSeeder extends Seeder
{
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

    private const PHONE_PREFIXES = [
        'Tunis' => '71', 'Nabeul' => '72', 'Bizerte' => '72',
        'Sousse' => '73', 'Monastir' => '73', 'Mahdia' => '73',
        'Sfax' => '74', 'Gabès' => '75', 'Médenine' => '75',
        'Tataouine' => '75', 'Gafsa' => '76', 'Tozeur' => '76',
        'Kébili' => '76', 'Kairouan' => '77', 'Jendouba' => '78',
        'Béja' => '78', 'Kef' => '78', 'Siliana' => '78',
        'Zaghouan' => '72', 'Kasserine' => '77', 'Manouba' => '71',
        'Ben Arous' => '71', 'Ariana' => '71',
    ];

    private const ESTABLISHED_YEARS = [
        1978, 1980, 1982, 1984, 1986, 1988, 1990, 1992,
        1994, 1996, 1998, 2000, 2002, 2004, 2006, 2008,
        2010, 2012, 2014, 2016,
    ];

    // ─────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $now     = now()->toDateTimeString();
        $centres = DB::table('camping_centres')->whereNull('profile_centre_id')->get();
        $total   = $centres->count();

        if ($total === 0) {
            $this->command->info('✓ All camping_centres already linked.');
            return;
        }

        $serviceCats = DB::table('service_categories')
            ->whereIn('id', array_keys(self::SVC_DEFAULTS))
            ->get()
            ->keyBy('id')
            ->all();

        $centreRoleId = DB::table('roles')->where('name', 'centre')->value('id');
        if (!$centreRoleId) {
            $this->command->error('❌ "centre" role not found!');
            return;
        }

        $created = 0;
        $failed  = 0;

        foreach ($centres as $i => $centre) {
            try {
                DB::transaction(function () use ($centre, $now, $serviceCats, $centreRoleId): void {
                    $this->processCentre($centre, $now, $serviceCats, $centreRoleId);
                });
                $created++;
            } catch (\Throwable $e) {
                $this->command->error("  ❌ #{$centre->id} '{$centre->nom}': {$e->getMessage()}");
                $failed++;
            }

            if (($i + 1) % 25 === 0) {
                $this->command->info("  Progress: " . ($i + 1) . "/{$total}");
            }
        }

        $this->command->info("✓ Done: {$created} created, {$failed} failed");
    }

    // ── Per-centre pipeline ──────────────────────────────────────────

    private function processCentre($centre, string $now, array $serviceCats, int $centreRoleId): void
    {
        $id      = $centre->id;
        $nom     = trim($centre->nom ?? 'Centre');
        $adresse = $centre->adresse ?? '';
        $desc    = $centre->description ?? '';
        $type    = $centre->type ?? 'centre_camping';
        $lat     = $centre->lat !== null ? (float) $centre->lat : null;
        $lng     = $centre->lng !== null ? (float) $centre->lng : null;

        $category      = $this->resolveCategory($type, $adresse);
        $capacite      = $this->resolveCapacite($id, $type);
        $pricePerNight = $this->resolvePrice($id, $type, $category);
        $ville         = $this->resolveVille($adresse, $nom);
        $firstName     = $this->extractFirstName($nom);
        $lastName      = $this->extractLastName($nom);
        $email         = Str::slug($nom) . '-' . $id . '@tunisiacamp.tn';
        $phone         = $this->generatePhone($ville, $id);
        $established   = $this->generateEstablishedDate($id);
        $bio           = $this->buildBio($nom, $type, $category, $ville, $desc);

        // 1. User ─────────────────────────────────────────────────────
        $userId = DB::table('users')->insertGetId([
            'uuid'              => (string) Str::uuid(),
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $email,
            'email_verified_at' => $now,
            'password'          => Hash::make('CentrePass2025!'),
            'phone_number'      => $phone,
            'role_id'           => $centreRoleId,
            'is_active'         => 1,
            'ville'             => $ville,
            'langue'            => 'fr',
            'first_login'       => 0,
            'nombre_signalement'=> 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // 2. Profile ──────────────────────────────────────────────────
        $profileId = DB::table('profiles')->insertGetId([
            'user_id'    => $userId,
            'type'       => 'centre',
            'bio'        => $bio,
            'city'       => $ville,
            'address'    => $adresse,
            'is_public'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. ProfileCentre ────────────────────────────────────────────
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
            'contact_phone'    => $phone,
            'manager_name'     => $firstName . ' ' . $lastName,
            'established_date' => $established,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        // 4. Equipment ────────────────────────────────────────────────
        $this->createEquipment($profileCentreId, $id, $type, $category, $nom, $adresse, $now);

        // 5. Services ─────────────────────────────────────────────────
        $this->createServices($profileCentreId, $id, $type, $category, $now, $serviceCats);

        // 6. Link camping_centre ──────────────────────────────────────
        DB::table('camping_centres')->where('id', $id)->update([
            'user_id'           => $userId,
            'profile_centre_id' => $profileCentreId,
            'status'            => 1,
            'validation_status' => 'approved',
            'is_partner'        => 1,
            'updated_at'        => $now,
        ]);
    }

    // ── Category ────────────────────────────────────────────────────

    private function resolveCategory(string $type, string $adresse): string
    {
        $lower = mb_strtolower($adresse);

        $typeMap = [
            'glamping'         => 'glamping',
            'campement_desert' => 'desert',
            'bivouac'          => 'desert',
            'maison_hotes'     => 'lodge',
            'gite_rural'       => 'nature',
            'ferme_agricole'   => 'nature',
            'chalet_foret'     => 'nature',
        ];

        if (isset($typeMap[$type])) {
            return $typeMap[$type];
        }

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

        return 'camping';
    }

    // ── Capacité ────────────────────────────────────────────────────

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

    // ── Price ───────────────────────────────────────────────────────

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

    // ── Equipment ───────────────────────────────────────────────────

    private function createEquipment(int $pcId, int $id, string $type, string $category, string $nom, string $adresse, string $now): void
    {
        $equipment = match ($type) {
            'glamping' => [
                'toilets' => 1, 'drinking_water' => 1, 'parking' => 1, 'showers' => 1,
                'electricity' => 1, 'wifi' => 1, 'kitchen' => 1, 'bbq_area' => 1,
                'swimming_pool' => $this->prob($id, 60) ? 1 : 0,
                'security' => $this->prob($id * 3, 55) ? 1 : 0,
            ],
            'campement_desert', 'bivouac' => [
                'toilets' => 1, 'drinking_water' => 1, 'parking' => 1, 'security' => 1,
                'electricity' => $this->prob($id, 25) ? 1 : 0,
                'wifi' => $this->prob($id * 5, 28) ? 1 : 0,
                'showers' => $this->prob($id * 7, 52) ? 1 : 0,
                'bbq_area' => $this->prob($id * 11, 68) ? 1 : 0,
            ],
            'maison_hotes' => [
                'toilets' => 1, 'drinking_water' => 1, 'parking' => 1, 'showers' => 1,
                'electricity' => 1, 'wifi' => 1,
                'kitchen' => $this->prob($id, 62) ? 1 : 0,
                'swimming_pool' => $this->prob($id * 3, 38) ? 1 : 0,
                'bbq_area' => $this->prob($id * 5, 48) ? 1 : 0,
                'security' => $this->prob($id * 7, 44) ? 1 : 0,
            ],
            'gite_rural' => [
                'toilets' => 1, 'drinking_water' => 1, 'parking' => 1, 'showers' => 1,
                'electricity' => 1,
                'wifi' => $this->prob($id, 68) ? 1 : 0,
                'bbq_area' => $this->prob($id * 3, 63) ? 1 : 0,
                'kitchen' => $this->prob($id * 7, 52) ? 1 : 0,
            ],
            'ferme_agricole' => [
                'toilets' => 1, 'drinking_water' => 1, 'parking' => 1, 'electricity' => 1,
                'bbq_area' => 1,
                'showers' => $this->prob($id, 72) ? 1 : 0,
                'wifi' => $this->prob($id * 3, 58) ? 1 : 0,
                'kitchen' => $this->prob($id * 5, 44) ? 1 : 0,
                'swimming_pool' => $this->prob($id * 9, 22) ? 1 : 0,
            ],
            default => $this->campingEquipment($id, $category),
        };

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
                'profile_center_id' => $pcId,
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
                $base['showers'] = 1;
                $base['security'] = 1;
                $base['bbq_area'] = $this->prob($id, 58) ? 1 : 0;
                $base['wifi'] = $this->prob($id * 3, 48) ? 1 : 0;
                $base['electricity'] = $this->prob($id * 5, 52) ? 1 : 0;
                break;
            case 'beach':
                $base['showers'] = 1;
                $base['security'] = $this->prob($id, 65) ? 1 : 0;
                $base['bbq_area'] = $this->prob($id * 3, 55) ? 1 : 0;
                $base['wifi'] = $this->prob($id * 5, 50) ? 1 : 0;
                $base['electricity'] = $this->prob($id * 7, 58) ? 1 : 0;
                break;
            case 'nature':
                $base['bbq_area'] = 1;
                $base['electricity'] = $this->prob($id, 38) ? 1 : 0;
                $base['wifi'] = $this->prob($id * 3, 42) ? 1 : 0;
                $base['showers'] = $this->prob($id * 7, 52) ? 1 : 0;
                $base['security'] = $this->prob($id * 11, 32) ? 1 : 0;
                break;
            case 'desert':
                $base['security'] = 1;
                $base['electricity'] = 0;
                $base['wifi'] = $this->prob($id, 18) ? 1 : 0;
                $base['showers'] = $this->prob($id * 3, 32) ? 1 : 0;
                $base['bbq_area'] = $this->prob($id * 5, 62) ? 1 : 0;
                break;
            case 'lodge':
                $base['showers'] = 1;
                $base['electricity'] = 1;
                $base['wifi'] = 1;
                $base['bbq_area'] = $this->prob($id, 52) ? 1 : 0;
                $base['kitchen'] = $this->prob($id * 3, 45) ? 1 : 0;
                $base['security'] = $this->prob($id * 5, 38) ? 1 : 0;
                break;
            default:
                $base['bbq_area'] = $this->prob($id, 62) ? 1 : 0;
                $base['electricity'] = $this->prob($id * 3, 48) ? 1 : 0;
                $base['wifi'] = $this->prob($id * 5, 38) ? 1 : 0;
                $base['showers'] = $this->prob($id * 7, 52) ? 1 : 0;
                break;
        }
        return $base;
    }

    // ── Services ─────────────────────────────────────────────────────

    private function createServices(int $pcId, int $id, string $type, string $category, string $now, array $serviceCats): void
    {
        $services = [];

        switch ($type) {
            case 'glamping':
                $services[1] = [1, 1, $this->vf($id, 25.0, 45.0)];
                $services[2] = [1, 1, $this->vf($id * 3, 90.0, 160.0)];
                $services[3] = [1, 1, $this->vf($id * 5, 25.0, 45.0)];
                $services[5] = [1, 1, $this->vf($id * 7, 45.0, 78.0)];
                $services[4] = [0, 1, $this->vf($id * 9, 38.0, 58.0)];
                $services[8] = [0, 1, $this->vf($id * 11, 80.0, 135.0)];
                $services[9] = [0, 1, $this->vf($id * 13, 25.0, 40.0)];
                if ($this->prob($id, 55)) $services[10] = [0, 1, $this->vf($id * 17, 40.0, 72.0)];
                break;

            case 'campement_desert':
            case 'bivouac':
                $services[1] = [1, 1, $this->vf($id, 20.0, 40.0)];
                $services[8] = [1, 1, $this->vf($id * 3, 70.0, 145.0)];
                $services[10] = [1, 1, $this->vf($id * 5, 40.0, 82.0)];
                $services[5] = [0, 1, $this->vf($id * 7, 35.0, 62.0)];
                $services[9] = [0, 1, $this->vf($id * 9, 20.0, 35.0)];
                if ($this->prob($id * 3, 62)) $services[3] = [0, 1, $this->vf($id * 11, 20.0, 35.0)];
                if ($this->prob($id * 5, 40)) $services[6] = [0, 1, $this->vf($id * 13, 45.0, 75.0)];
                break;

            case 'maison_hotes':
                $services[1] = [1, 1, $this->vf($id, 18.0, 35.0)];
                $services[3] = [1, 1, $this->vf($id * 3, 20.0, 40.0)];
                $services[11] = [0, 1, $this->vf($id * 11, 8.0, 15.0)];
                if ($this->prob($id, 58)) $services[5] = [1, 1, $this->vf($id * 5, 38.0, 68.0)];
                if ($this->prob($id * 3, 45)) $services[8] = [0, 1, $this->vf($id * 7, 65.0, 115.0)];
                if ($this->prob($id * 5, 42)) $services[4] = [0, 1, $this->vf($id * 9, 28.0, 50.0)];
                break;

            case 'gite_rural':
                $services[1] = [1, 1, $this->vf($id, 15.0, 28.0)];
                $services[3] = [1, 1, $this->vf($id * 3, 18.0, 30.0)];
                $services[6] = [0, 1, $this->vf($id * 5, 45.0, 72.0)];
                $services[7] = [0, 1, $this->vf($id * 7, 12.0, 22.0)];
                $services[9] = [0, 1, $this->vf($id * 9, 20.0, 32.0)];
                if ($this->prob($id, 62)) $services[8] = [0, 1, $this->vf($id * 11, 60.0, 105.0)];
                if ($this->prob($id * 3, 38)) $services[4] = [0, 1, $this->vf($id * 13, 25.0, 42.0)];
                break;

            case 'ferme_agricole':
                $services[1] = [1, 1, $this->vf($id, 12.0, 25.0)];
                $services[3] = [1, 1, $this->vf($id * 3, 18.0, 30.0)];
                $services[9] = [0, 1, $this->vf($id * 5, 18.0, 30.0)];
                $services[11] = [0, 1, $this->vf($id * 7, 8.0, 13.0)];
                if ($this->prob($id, 52)) $services[8] = [0, 1, $this->vf($id * 9, 50.0, 90.0)];
                if ($this->prob($id * 3, 40)) $services[4] = [0, 1, $this->vf($id * 11, 25.0, 45.0)];
                break;

            default:
                $services[1] = [1, 1, $this->vf($id, 12.0, 30.0)];
                $services[9] = [0, 1, $this->vf($id * 3, 20.0, 35.0)];
                $services[11] = [0, 1, $this->vf($id * 5, 8.0, 14.0)];

                if ($category === 'nature') {
                    $services[8] = [0, 1, $this->vf($id * 7, 58.0, 100.0)];
                    $services[6] = [0, 1, $this->vf($id * 9, 45.0, 70.0)];
                    $services[7] = [0, 1, $this->vf($id * 11, 12.0, 22.0)];
                } elseif ($category === 'coastal' || $category === 'beach') {
                    $services[3] = [0, 1, $this->vf($id * 7, 18.0, 32.0)];
                    $services[10] = [0, 1, $this->vf($id * 9, 35.0, 65.0)];
                } elseif ($category === 'desert') {
                    $services[8] = [1, 1, $this->vf($id * 7, 65.0, 125.0)];
                    $services[10] = [1, 1, $this->vf($id * 9, 40.0, 78.0)];
                    $services[5] = [0, 1, $this->vf($id * 11, 35.0, 60.0)];
                } elseif ($category === 'lodge') {
                    $services[3] = [1, 1, $this->vf($id * 7, 22.0, 42.0)];
                    $services[5] = [1, 1, $this->vf($id * 9, 40.0, 68.0)];
                    if ($this->prob($id * 3, 45)) $services[8] = [0, 1, $this->vf($id * 11, 60.0, 100.0)];
                }
                break;
        }

        $rows = [];
        foreach ($services as $catId => [$isStandard, $isAvailable, $rawPrice]) {
            $cat  = $serviceCats[$catId] ?? null;
            $unit = 'unit';
            if ($cat && isset($cat->unit)) {
                $unit = $cat->unit;
            } elseif (isset(self::SVC_DEFAULTS[$catId])) {
                $unit = self::SVC_DEFAULTS[$catId]['unit'];
            }
            $price = round($rawPrice * 2) / 2;

            $rows[] = [
                'profile_center_id'   => $pcId,
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

        if (!empty($rows)) {
            DB::table('profile_center_services')->insert($rows);
        }
    }

    // ── Deterministic helpers ─────────────────────────────────────────

    private function varyFloat(int $seed, float $min, float $max): float
    {
        return $min + ($max - $min) * fmod(abs($seed) * 0.6180339887, 1.0);
    }

    private function vf(int $seed, float $min, float $max): float
    {
        return $this->varyFloat($seed, $min, $max);
    }

    private function varyInt(int $seed, int $min, int $max): int
    {
        return (int) round($min + ($max - $min) * fmod(abs($seed) * 0.6180339887, 1.0));
    }

    private function prob(int $seed, int $pct): bool
    {
        return (fmod(abs($seed) * 0.6180339887, 1.0) * 100) < $pct;
    }

    // ── String / data helpers ────────────────────────────────────────

    private function extractFirstName(string $nom): string
    {
        $skip = ['camp', 'centre', 'dar', 'camping', 'le', 'la', 'les', 'el', 'gite', 'gîte', 'maison', 'villa', 'chalet'];
        $parts = preg_split('/\s+/', trim($nom));
        foreach ($parts as $p) {
            if (!in_array(mb_strtolower($p), $skip)) {
                return ucfirst(mb_strtolower($p));
            }
        }
        return ucfirst(mb_strtolower($parts[0] ?? 'Centre'));
    }

    private function extractLastName(string $nom): string
    {
        $skip = ['camp', 'centre', 'dar', 'camping', 'le', 'la', 'les', 'el', 'gite', 'gîte', 'maison', 'villa', 'chalet'];
        $parts = preg_split('/\s+/', trim($nom));
        $meaningful = [];
        foreach ($parts as $p) {
            if (!in_array(mb_strtolower($p), $skip)) {
                $meaningful[] = ucfirst(mb_strtolower($p));
            }
        }
        if (count($meaningful) > 1) {
            return implode(' ', array_slice($meaningful, 1));
        }
        return 'Camp';
    }

    private function resolveVille(string $adresse, string $nomFallback): string
    {
        $govMap = [
            'ain draham'   => 'Jendouba', 'aïn draham' => 'Jendouba', 'ksar ghilane' => 'Tataouine',
            'ghar el melh' => 'Bizerte', 'sidi mechreg' => 'Bizerte', 'sidi bou' => 'Tunis',
            'ben arous'    => 'Ben Arous', 'el faouar' => 'Kébili', 'el battan' => 'Manouba',
            'tabarka'      => 'Jendouba', 'jendouba' => 'Jendouba', 'bizerte' => 'Bizerte',
            'hammamet'     => 'Nabeul', 'kelibia' => 'Nabeul', 'kélibia' => 'Nabeul',
            'korbous'      => 'Nabeul', 'nabeul' => 'Nabeul', 'zaghouan' => 'Zaghouan',
            'sousse'       => 'Sousse', 'monastir' => 'Monastir', 'mahdia' => 'Mahdia',
            'salakta'      => 'Mahdia', 'sfax' => 'Sfax', 'kairouan' => 'Kairouan',
            'siliana'      => 'Siliana', 'béja' => 'Béja', 'beja' => 'Béja', 'nefza' => 'Béja',
            'testour'      => 'Béja', 'tozeur' => 'Tozeur', 'nefta' => 'Tozeur', 'metlaoui' => 'Gafsa',
            'gafsa'        => 'Gafsa', 'douz' => 'Kébili', 'sabria' => 'Kébili', 'ghidma' => 'Kébili',
            'kébili'       => 'Kébili', 'kebili' => 'Kébili', 'gabès' => 'Gabès', 'matmata' => 'Gabès',
            'tamezret'     => 'Gabès', 'médenine' => 'Médenine', 'djerba' => 'Médenine',
            'tataouine'    => 'Tataouine', 'chenini' => 'Tataouine', 'manouba' => 'Manouba',
            'mornag'       => 'Ben Arous', 'ariana' => 'Ariana', 'rafraf' => 'Bizerte',
            'kef'          => 'Kef', 'tunis' => 'Tunis', 'kasserine' => 'Kasserine',
            'fermana'      => 'Jendouba', 'halk el oued' => 'Béja', 'sbeitla' => 'Kasserine',
            'degache'      => 'Tozeur', 'chebika' => 'Tozeur', 'tamerza' => 'Tozeur',
            'sejnane'      => 'Bizerte', 'tinja' => 'Bizerte', 'mateur' => 'Bizerte',
            'enfidha'      => 'Sousse', 'bouficha' => 'Sousse', 'skhira' => 'Sfax',
            'jebeniana'    => 'Sfax', 'el jem' => 'Mahdia', 'zarzis' => 'Médenine',
        ];
        $lower = mb_strtolower($adresse);
        foreach ($govMap as $kw => $ville) {
            if (str_contains($lower, $kw)) return $ville;
        }
        $parts = preg_split('/\s+/', trim($nomFallback), 2);
        return $parts[0] ?? 'Tunisie';
    }

    private function generatePhone(string $ville, int $id): string
    {
        $prefix = self::PHONE_PREFIXES[$ville] ?? '71';
        $suffix = str_pad(($id * 7 + 12345) % 1000000, 6, '0', STR_PAD_LEFT);
        return "+216 {$prefix} {$suffix}";
    }

    private function generateEstablishedDate(int $id): string
    {
        $year  = self::ESTABLISHED_YEARS[$id % count(self::ESTABLISHED_YEARS)];
        $month = ($id % 12) + 1;
        $day   = min(($id % 28) + 1, 28);
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function buildBio(string $nom, string $type, string $category, string $ville, string $desc): string
    {
        if (!empty($desc)) {
            // Use mb_substr to safely truncate multibyte strings
            $short = mb_substr($desc, 0, 250);
            // Remove any trailing incomplete multibyte character
            return mb_convert_encoding($short, 'UTF-8', 'UTF-8');
        }

        $typeLabels = [
            'centre_camping'   => 'Centre de camping',
            'campement_desert' => 'Campement desertique',
            'bivouac'          => 'Bivouac',
            'glamping'         => 'Camping de luxe',
            'maison_hotes'     => "Maison d'hotes",
            'gite_rural'       => 'Gite rural',
            'ferme_agricole'   => 'Ferme agricole',
            'chalet_foret'     => 'Chalet forestier',
        ];

        $categoryLabels = [
            'desert'   => 'au coeur du desert tunisien',
            'beach'    => 'en bord de mer',
            'nature'   => 'en pleine nature',
            'coastal'  => 'sur la cote tunisienne',
            'lodge'    => 'dans un cadre authentique',
            'glamping' => 'alliant confort et nature',
            'camping'  => 'pour les amoureux du plein air',
        ];

        $label    = $typeLabels[$type] ?? 'Etablissement';
        $catLabel = $categoryLabels[$category] ?? 'en Tunisie';

        return "{$label} {$catLabel}. Situe a {$ville}, {$nom} vous accueille pour un sejour inoubliable.";
    }
}