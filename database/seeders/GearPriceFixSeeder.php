<?php

namespace Database\Seeders;

use App\Models\Materielles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-time fix: assigns realistic Tunisian rental-market tarif_nuit values
 * to the 15 materielle items that shipped with tarif_nuit = NULL.
 *
 * Idempotent: running it again sets the same values (safe to re-run).
 * Uses a single INSERT … ON DUPLICATE KEY UPDATE statement via upsert().
 *
 * Run manually:
 *   php artisan db:seed --class=GearPriceFixSeeder
 */
class GearPriceFixSeeder extends Seeder
{
    /** id → tarif_nuit (TND/night) */
    private const PRICES = [
        19 =>  8.00,   // Kit survie désert 72h
        20 =>  3.00,   // Boussole Suunto A-10
        21 =>  4.00,   // Filtre à eau LifeStraw Personal
        23 =>  6.00,   // Veste imperméable Columbia
        25 =>  7.00,   // Chaussures randonnée Merrell Moab
        26 =>  5.00,   // Pantalon trekking Quechua MH500
        27 =>  3.00,   // Guêtres imperméables Outdoor Research
        42 =>  2.00,   // Sifflet de sécurité + boussole Fox 40
        48 => 12.00,   // Kit survie premium 72h Mil-Tec
        50 =>  4.00,   // Couvertures de survie × 5
        51 =>  5.00,   // Kit purification eau Katadyn BeFree
        52 =>  6.00,   // Fusées de détresse marines
        56 =>  3.00,   // Housse pluie imperméable sac
        57 =>  3.00,   // Sac de compression Sea to Summit
        58 =>  4.00,   // Bidon souple Platypus 2L + filtre
    ];

    public function run(): void
    {
        // Single UPDATE with a CASE expression — one round-trip, per-item prices preserved.
        // upsert() is avoided because it generates a full INSERT that requires all NOT NULL
        // columns (fournisseur_id etc.) even though every id already exists.
        $whenClauses = collect(self::PRICES)
            ->map(fn ($price, $id) => "WHEN {$id} THEN {$price}")
            ->implode(' ');

        DB::table('materielles')
            ->whereIn('id', array_keys(self::PRICES))
            ->update([
                'tarif_nuit' => DB::raw("CASE id {$whenClauses} END"),
            ]);

        $remaining = Materielles::whereNull('tarif_nuit')->count();

        $this->command->info('GearPriceFixSeeder complete.');
        $this->command->info('  Items still with tarif_nuit = NULL: ' . $remaining . ' (expect 0)');
        $this->command->info('  Spot-check id=20 → ' . (Materielles::find(20)?->tarif_nuit ?? 'NOT FOUND') . ' TND/nuit (expect 3.00)');
    }
}
