<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

// =============================================================================
// Run order (all handled by ShopSeeder at the bottom):
//   1. MateriellesCategoriesSeeder
//   2. BoutiqueSeeder
//   3. MateriellesSeeder
//   4. ReservationsMateriellesSeeder
//
// Add to DatabaseSeeder AFTER UserSeeder:
//   $this->call([ UserSeeder::class, ShopSeeder::class ]);
// =============================================================================

// -----------------------------------------------------------------------------
// 1. CATEGORIES
// -----------------------------------------------------------------------------

class MateriellesCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['id' => 1, 'nom' => 'Tents',               'description' => 'All-season camping tents — 1 to 8 persons.'],
            ['id' => 2, 'nom' => 'Sleeping Bags',       'description' => 'Sleeping bags for temperatures from -10°C to +20°C.'],
            ['id' => 3, 'nom' => 'Stoves & Cooking',    'description' => 'Stoves, cookware, utensils and outdoor cooking accessories.'],
            ['id' => 4, 'nom' => 'Hiking',              'description' => 'Backpacks, trekking poles, compasses and trekking equipment.'],
            ['id' => 5, 'nom' => 'Lighting',            'description' => 'Headlamps, lanterns and solar lighting.'],
            ['id' => 6, 'nom' => 'Camp Furniture',      'description' => 'Folding chairs, tables, hammocks and ground mats.'],
        ];

        foreach ($categories as $cat) {
            DB::table('materielles_categories')->insert(array_merge($cat, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('✅  materielles_categories: ' . count($categories) . ' rows inserted.');
    }
}

// -----------------------------------------------------------------------------
// 2. BOUTIQUE  (fournisseur = user id 7 — equipment@example.com, role_id=4)
// -----------------------------------------------------------------------------

class BoutiqueSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('boutiques')->insert([
            'id'             => 1,
            'fournisseur_id' => 7,
            'nom_boutique'   => 'Camp Équipement Pro',
            'description'    => 'Votre spécialiste en matériel de camping haut de gamme à Sfax. Location et vente.',
            'status'         => true,
            'path_to_img'    => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->command->info('✅  boutiques: 1 row inserted.');
    }
}

// -----------------------------------------------------------------------------
// 3. MATERIELLES  (all owned by fournisseur_id = 7)
// -----------------------------------------------------------------------------

class MateriellesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // --- Rentable ONLY ---
            [
                'id' => 1, 'fournisseur_id' => 7, 'category_id' => 1,
                'nom' => 'Tente Quechua 2 Secondes 3P',
                'description' => 'Tente 3 places ultra-rapide à monter, idéale pour week-ends.',
                'is_rentable' => true,  'is_sellable' => false,
                'tarif_nuit' => 25.00,  'prix_vente' => null,
                'quantite_total' => 5,  'quantite_dispo' => 5,
                'livraison_disponible' => true, 'frais_livraison' => 10.00,
                'status' => 'up',
            ],
            [
                'id' => 2, 'fournisseur_id' => 7, 'category_id' => 2,
                'nom' => 'Sac de Couchage Forclaz -5°C',
                'description' => "Sac de couchage confort jusqu'à -5°C, rembourrage synthétique.",
                'is_rentable' => true,  'is_sellable' => false,
                'tarif_nuit' => 12.00,  'prix_vente' => null,
                'quantite_total' => 10, 'quantite_dispo' => 10,
                'livraison_disponible' => true, 'frais_livraison' => 5.00,
                'status' => 'up',
            ],
            [
                'id' => 3, 'fournisseur_id' => 7, 'category_id' => 6,
                'nom' => 'Chaise Pliante Camping',
                'description' => 'Chaise légère et robuste, charge max 120 kg.',
                'is_rentable' => true,  'is_sellable' => false,
                'tarif_nuit' => 5.00,   'prix_vente' => null,
                'quantite_total' => 20, 'quantite_dispo' => 20,
                'livraison_disponible' => false, 'frais_livraison' => null,
                'status' => 'up',
            ],
            // --- Sellable ONLY ---
            [
                'id' => 4, 'fournisseur_id' => 7, 'category_id' => 5,
                'nom' => 'Lampe Frontale LED 350 Lm',
                'description' => 'Lampe frontale rechargeable USB, autonomie 8h, étanche IPX4.',
                'is_rentable' => false, 'is_sellable' => true,
                'tarif_nuit' => null,   'prix_vente' => 35.00,
                'quantite_total' => 30, 'quantite_dispo' => 30,
                'livraison_disponible' => true, 'frais_livraison' => 7.00,
                'status' => 'up',
            ],
            [
                'id' => 5, 'fournisseur_id' => 7, 'category_id' => 3,
                'nom' => 'Kit Gamelle Inox 4 pièces',
                'description' => 'Set de cuisine inox compact, compatible gaz et induction.',
                'is_rentable' => false, 'is_sellable' => true,
                'tarif_nuit' => null,   'prix_vente' => 55.00,
                'quantite_total' => 15, 'quantite_dispo' => 15,
                'livraison_disponible' => true, 'frais_livraison' => 7.00,
                'status' => 'up',
            ],
            // --- Both rentable AND sellable ---
            [
                'id' => 6, 'fournisseur_id' => 7, 'category_id' => 4,
                'nom' => 'Sac à Dos Trek 50L Forclaz',
                'description' => 'Sac à dos 50L avec armature dorsale réglable, housse pluie incluse.',
                'is_rentable' => true,  'is_sellable' => true,
                'tarif_nuit' => 15.00,  'prix_vente' => 185.00,
                'quantite_total' => 8,  'quantite_dispo' => 8,
                'livraison_disponible' => true, 'frais_livraison' => 10.00,
                'status' => 'up',
            ],
            [
                'id' => 7, 'fournisseur_id' => 7, 'category_id' => 1,
                'nom' => 'Tente Dôme 4 Saisons 4P',
                'description' => 'Tente professionnelle 4 personnes, résistante aux vents forts.',
                'is_rentable' => true,  'is_sellable' => true,
                'tarif_nuit' => 40.00,  'prix_vente' => 350.00,
                'quantite_total' => 3,  'quantite_dispo' => 3,
                'livraison_disponible' => true, 'frais_livraison' => 15.00,
                'status' => 'up',
            ],
            // --- Inactive listing (tests status=down filter) ---
            [
                'id' => 8, 'fournisseur_id' => 7, 'category_id' => 5,
                'nom' => 'Lanterne Solaire (Hors stock)',
                'description' => 'Lanterne solaire pliable — temporairement indisponible.',
                'is_rentable' => true,  'is_sellable' => false,
                'tarif_nuit' => 8.00,   'prix_vente' => null,
                'quantite_total' => 6,  'quantite_dispo' => 0,
                'livraison_disponible' => false, 'frais_livraison' => null,
                'status' => 'down',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('materielles')->insert(array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('✅  materielles: ' . count($rows) . ' rows inserted.');
    }
}

// -----------------------------------------------------------------------------
// 4. RESERVATIONS
//
// Camper id=3  deadxshot660@gmail.com  → has CIN
// Camper id=9  sarah@example.com       → has CIN
// Camper id=10 mike@example.com        → NO CIN (tests the rental gate)
//
// Seeded raw PINs for testing verifyPin endpoint:
//   Reservation #2  (confirmed, rental)  → 123456
//   Reservation #3  (paid, rental)       → 654321
//   Reservation #10 (paid, sale)         → 246810
// -----------------------------------------------------------------------------

class ReservationsMateriellesSeeder extends Seeder
{
    /** Build a complete reservation row with safe defaults for every nullable column. */
    private function row(array $data): array
    {
        return array_merge([
            // required
            'materielle_id'     => null,
            'user_id'           => null,
            'fournisseur_id'    => 7,
            'type_reservation'  => 'location',
            'quantite'          => 1,
            'montant_total'     => 0,
            'mode_livraison'    => 'pickup',
            'status'            => 'pending',
            // nullable
            'date_debut'        => null,
            'date_fin'          => null,
            'adresse_livraison' => null,
            'frais_livraison'   => 0,
            'cin_camper'        => null,
            'pin_code'          => null,
            'pin_used_at'       => null,
            'confirmed_at'      => null,
            'retrieved_at'      => null,
            'returned_at'       => null,
            'payment_id'        => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $data);
    }

    public function run(): void
    {
        $n = Carbon::now();

        // =====================================================================
        // RENTALS
        // =====================================================================

        // [1] pending — camper 3 wants to rent tente, pickup
        DB::table('reservations_materielles')->insert($this->row([
            'id'               => 1,
            'materielle_id'    => 1,
            'user_id'          => 3,
            'type_reservation' => 'location',
            'date_debut'       => $n->copy()->addDays(7)->toDateString(),
            'date_fin'         => $n->copy()->addDays(9)->toDateString(),
            'quantite'         => 1,
            'montant_total'    => 50.00,
            'mode_livraison'   => 'pickup',
            'cin_camper'       => 'uploads/cin/user3_cin.jpg',
            'status'           => 'pending',
            'created_at'       => $n->copy()->subHours(2),
            'updated_at'       => $n->copy()->subHours(2),
        ]));

        // [2] confirmed — camper 9 renting sleeping bags, delivery, PIN=123456
        DB::table('reservations_materielles')->insert($this->row([
            'id'                => 2,
            'materielle_id'     => 2,
            'user_id'           => 9,
            'type_reservation'  => 'location',
            'date_debut'        => $n->copy()->addDays(3)->toDateString(),
            'date_fin'          => $n->copy()->addDays(6)->toDateString(),
            'quantite'          => 2,
            'montant_total'     => 77.00,
            'mode_livraison'    => 'delivery',
            'adresse_livraison' => '12 Rue de la Mer, Bizerte 7000',
            'frais_livraison'   => 5.00,
            'cin_camper'        => 'uploads/cin/user9_cin.jpg',
            'status'            => 'confirmed',
            'pin_code'          => Hash::make('123456'),
            'confirmed_at'      => $n->copy()->subHour(),
            'created_at'        => $n->copy()->subHours(5),
            'updated_at'        => $n->copy()->subHour(),
        ]));

        // [3] paid — camper 3 renting chairs, awaiting PIN verification, PIN=654321
        DB::table('reservations_materielles')->insert($this->row([
            'id'               => 3,
            'materielle_id'    => 3,
            'user_id'          => 3,
            'type_reservation' => 'location',
            'date_debut'       => $n->copy()->addDay()->toDateString(),
            'date_fin'         => $n->copy()->addDays(2)->toDateString(),
            'quantite'         => 4,
            'montant_total'    => 20.00,
            'mode_livraison'   => 'pickup',
            'cin_camper'       => 'uploads/cin/user3_cin.jpg',
            'status'           => 'paid',
            'pin_code'         => Hash::make('654321'),
            'confirmed_at'     => $n->copy()->subDay(),
            'created_at'       => $n->copy()->subDays(2),
            'updated_at'       => $n->copy()->subDay(),
        ]));

        // [4] retrieved — camper 9 has trek bag, awaiting return
        DB::table('reservations_materielles')->insert($this->row([
            'id'               => 4,
            'materielle_id'    => 6,
            'user_id'          => 9,
            'type_reservation' => 'location',
            'date_debut'       => $n->copy()->subDays(3)->toDateString(),
            'date_fin'         => $n->copy()->addDays(4)->toDateString(),
            'quantite'         => 1,
            'montant_total'    => 105.00,
            'mode_livraison'   => 'pickup',
            'cin_camper'       => 'uploads/cin/user9_cin.jpg',
            'status'           => 'retrieved',
            'pin_code'         => Hash::make('789012'),
            'pin_used_at'      => $n->copy()->subDays(3),
            'confirmed_at'     => $n->copy()->subDays(4),
            'retrieved_at'     => $n->copy()->subDays(3),
            'created_at'       => $n->copy()->subDays(5),
            'updated_at'       => $n->copy()->subDays(3),
        ]));

        // [5] returned — completed rental, payout eligible
        DB::table('reservations_materielles')->insert($this->row([
            'id'               => 5,
            'materielle_id'    => 2,
            'user_id'          => 3,
            'type_reservation' => 'location',
            'date_debut'       => $n->copy()->subDays(10)->toDateString(),
            'date_fin'         => $n->copy()->subDays(7)->toDateString(),
            'quantite'         => 1,
            'montant_total'    => 36.00,
            'mode_livraison'   => 'pickup',
            'cin_camper'       => 'uploads/cin/user3_cin.jpg',
            'status'           => 'returned',
            'pin_code'         => Hash::make('111222'),
            'pin_used_at'      => $n->copy()->subDays(10),
            'confirmed_at'     => $n->copy()->subDays(11),
            'retrieved_at'     => $n->copy()->subDays(10),
            'returned_at'      => $n->copy()->subDays(7),
            'created_at'       => $n->copy()->subDays(12),
            'updated_at'       => $n->copy()->subDays(7),
        ]));

        // [6] rejected — supplier rejected camper 3's request
        DB::table('reservations_materielles')->insert($this->row([
            'id'                => 6,
            'materielle_id'     => 7,
            'user_id'           => 3,
            'type_reservation'  => 'location',
            'date_debut'        => $n->copy()->addDays(14)->toDateString(),
            'date_fin'          => $n->copy()->addDays(17)->toDateString(),
            'quantite'          => 1,
            'montant_total'     => 120.00,
            'mode_livraison'    => 'delivery',
            'adresse_livraison' => '5 Av. Habib Bourguiba, Sousse',
            'frais_livraison'   => 15.00,
            'cin_camper'        => 'uploads/cin/user3_cin.jpg',
            'status'            => 'rejected',
            'created_at'        => $n->copy()->subDay(),
            'updated_at'        => $n->copy()->subDay(),
        ]));

        // [7] canceled — camper 9 canceled before confirmation
        DB::table('reservations_materielles')->insert($this->row([
            'id'               => 7,
            'materielle_id'    => 1,
            'user_id'          => 9,
            'type_reservation' => 'location',
            'date_debut'       => $n->copy()->addDays(20)->toDateString(),
            'date_fin'         => $n->copy()->addDays(22)->toDateString(),
            'quantite'         => 1,
            'montant_total'    => 50.00,
            'mode_livraison'   => 'pickup',
            'cin_camper'       => 'uploads/cin/user9_cin.jpg',
            'status'           => 'pending',
            'created_at'       => $n->copy()->subDays(3),
            'updated_at'       => $n->copy()->subDays(3),
        ]));

        // [8] disputed — overdue, item never returned, CIN available for legal action
        DB::table('reservations_materielles')->insert($this->row([
            'id'               => 8,
            'materielle_id'    => 6,
            'user_id'          => 3,
            'type_reservation' => 'location',
            'date_debut'       => $n->copy()->subDays(20)->toDateString(),
            'date_fin'         => $n->copy()->subDays(14)->toDateString(), // past due
            'quantite'         => 1,
            'montant_total'    => 105.00,
            'mode_livraison'   => 'pickup',
            'cin_camper'       => 'uploads/cin/user3_cin.jpg', // available for legal use
            'status'           => 'disputed',
            'pin_code'         => Hash::make('999888'),
            'pin_used_at'      => $n->copy()->subDays(20),
            'confirmed_at'     => $n->copy()->subDays(21),
            'retrieved_at'     => $n->copy()->subDays(20),
            'created_at'       => $n->copy()->subDays(22),
            'updated_at'       => $n->copy()->subDays(14),
        ]));

        // =====================================================================
        // SALES (no dates, no CIN)
        // =====================================================================

        // [9] pending sale — camper 3 buying 2 headlamps, pickup
        DB::table('reservations_materielles')->insert($this->row([
            'id'               => 9,
            'materielle_id'    => 4,
            'user_id'          => 3,
            'type_reservation' => 'achat',
            'quantite'         => 2,
            'montant_total'    => 70.00,
            'mode_livraison'   => 'pickup',
            'status'           => 'pending',
            'created_at'       => $n->copy()->subHour(),
            'updated_at'       => $n->copy()->subHour(),
        ]));

        // [10] paid sale — camper 9 buying gamelle set, delivery, PIN=246810
        DB::table('reservations_materielles')->insert($this->row([
            'id'                => 10,
            'materielle_id'     => 5,
            'user_id'           => 9,
            'type_reservation'  => 'achat',
            'quantite'          => 1,
            'montant_total'     => 62.00,
            'mode_livraison'    => 'delivery',
            'adresse_livraison' => '12 Rue de la Mer, Bizerte 7000',
            'frais_livraison'   => 7.00,
            'status'            => 'paid',
            'pin_code'          => Hash::make('246810'),
            'confirmed_at'      => $n->copy()->subHours(3),
            'created_at'        => $n->copy()->subHours(6),
            'updated_at'        => $n->copy()->subHours(3),
        ]));

        // [11] retrieved sale — camper 3, payout ready immediately
        DB::table('reservations_materielles')->insert($this->row([
            'id'                => 11,
            'materielle_id'     => 6,
            'user_id'           => 3,
            'type_reservation'  => 'achat',
            'quantite'          => 1,
            'montant_total'     => 195.00,
            'mode_livraison'    => 'delivery',
            'adresse_livraison' => '3 Rue Ibn Khaldoun, Sousse',
            'frais_livraison'   => 10.00,
            'status'            => 'retrieved',
            'pin_code'          => Hash::make('135791'),
            'pin_used_at'       => $n->copy()->subDays(2),
            'confirmed_at'      => $n->copy()->subDays(3),
            'retrieved_at'      => $n->copy()->subDays(2),
            'created_at'        => $n->copy()->subDays(4),
            'updated_at'        => $n->copy()->subDays(2),
        ]));

        $this->command->info('✅  reservations_materielles: 11 rows inserted.');
        $this->command->newLine();
        $this->command->info('📋 Test credentials:');
        $this->command->info('   Fournisseur  equipment@example.com / password  (id=7)');
        $this->command->info('   Camper       deadxshot660@gmail.com / password  (id=3, has CIN)');
        $this->command->info('   Camper       sarah@example.com / password       (id=9, has CIN)');
        $this->command->info('   Camper       mike@example.com / password        (id=10, NO CIN → tests gate)');
        $this->command->newLine();
        $this->command->info('🔑 Raw PINs for verifyPin tests:');
        $this->command->info('   Reservation #2  (confirmed, rental)  → 123456');
        $this->command->info('   Reservation #3  (paid, rental)       → 654321');
        $this->command->info('   Reservation #10 (paid, sale)         → 246810');
    }
}

// =============================================================================
// ORCHESTRATOR — add this to DatabaseSeeder after UserSeeder
// =============================================================================

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MateriellesCategoriesSeeder::class,
            // BoutiqueSeeder::class,
            // MateriellesSeeder::class,
            // ReservationsMateriellesSeeder::class,
        ]);
    }
}