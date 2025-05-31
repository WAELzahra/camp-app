<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profile;
use App\Models\ProfileFournisseur;
use Illuminate\Support\Facades\DB;

class FounisseurProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $profile = Profile::create([
                'user_id' => 4, // Make sure user with ID 5 exists
                'bio' => 'Fournisseur spécialisé dans les équipements de camping de haute qualité.',
                'cover_image' => 'cover_fournisseur.jpg',
                'adresse' => '456 Rue du Fournisseur, Sousse',
                'immatricule' => 'FOUR-2025-001',
                'type' => 'fournisseur',

            ]);

            ProfileFournisseur::create([
                'profile_id' => $profile->id,
                'intervale_prix' => '50 - 300 TND',
                'product_category' => 'Équipements de camping',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
