<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profile;
use App\Models\ProfileCentre;
use Carbon\Carbon;

class ProfileCentreSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure user with id=3 exists
        $profile = Profile::create([
            'user_id' => 3,
            'bio' => 'Centre très expérimenté avec une grande capacité.',
            'cover_image' => 'cover_centre.jpg',
            'adresse' => '123 Rue de Camping, Tunis',
            'immatricule' => 'CAMP-2025-001',
            'type' => 'centre',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        ProfileCentre::create([
            'profile_id' => $profile->id,
            'capacite' => 100,
            'services_offerts' => 'Hébergement, Activités sportives, Restauration',
            'document_legal' => 'legal_doc.pdf',
            'disponibilite' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
