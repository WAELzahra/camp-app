<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Profile;
use App\Models\ProfileGroupe;
use App\Models\ProfileCentre;
use App\Models\ProfileFournisseur;
use App\Models\ProfileGuide;
use App\Models\ServiceCategory;
use App\Models\ProfileCenterEquipment;
use App\Models\ProfileCenterService;

class UserSeeder extends Seeder
{
    public function run()
    {
        $roles = Role::all()->keyBy('name');

        $usersData = [
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ],
            [
                'first_name' => 'Campeur',
                'last_name' => 'User',
                'email' => 'campeur@example.com',
                'password' => bcrypt('password'),
                'role' => 'campeur',
            ],
            [
                'first_name' => 'Groupe',
                'last_name' => 'User',
                'email' => 'groupe@example.com',
                'password' => bcrypt('password'),
                'role' => 'groupe',
            ],
            [
                'first_name' => 'Centre',
                'last_name' => 'User',
                'email' => 'centre@example.com',
                'password' => bcrypt('password'),
                'role' => 'centre',
            ],
            [
                'first_name' => 'Fournisseur',
                'last_name' => 'User',
                'email' => 'fournisseur@example.com',
                'password' => bcrypt('password'),
                'role' => 'fournisseur',
            ],
            [
                'first_name' => 'Guide',
                'last_name' => 'User',
                'email' => 'guide@example.com',
                'password' => bcrypt('password'),
                'role' => 'guide',
            ],
        ];

        foreach ($usersData as $data) {
            $role = $roles[$data['role']];
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role_id' => $role->id,
            ]);

            // If not admin, create profile
            if ($data['role'] !== 'admin') {
                $profile = Profile::create([
                    'user_id' => $user->id,
                    'bio' => 'Bio for ' . $data['first_name'] . ' ' . $data['last_name'],
                    'type' => $data['role'],
                ]);

                // Create specialized profiles based on role
                switch ($data['role']) {
                    case 'groupe':
                        ProfileGroupe::create([
                            'profile_id' => $profile->id,
                            'nom_groupe' => 'Groupe ' . $data['first_name'] . ' ' . $data['last_name'],
                            'cin_responsable' => '12345678',
                        ]);
                        break;

                    case 'centre':
                        // Create center with full service system
                        $profileCentre = ProfileCentre::create([
                            'profile_id' => $profile->id,
                            'name' => 'Camping ' . $data['first_name'] . ' ' . $data['last_name'],
                            'adresse' => '123 Rue de Camping, Tunis',
                            'capacite' => 50,
                            'price_per_night' => 25.00,
                            'category' => 'Standard',
                            'services_offerts' => 'Toilettes, Eau potable, Électricité, Parking',
                            'additional_services_description' => 'Additional services available upon request',
                            'latitude' => 36.8065,
                            'longitude' => 10.1815,
                            'contact_email' => $data['email'],
                            'contact_phone' => '+21612345678',
                            'manager_name' => $data['first_name'] . ' ' . $data['last_name'],
                            'established_date' => now()->subYears(5)->format('Y-m-d'),
                            'disponibilite' => true,
                        ]);
                        
                        // Add equipment for this center
                        $equipment = [
                            ['type' => 'toilets', 'is_available' => true],
                            ['type' => 'drinking_water', 'is_available' => true],
                            ['type' => 'electricity', 'is_available' => true],
                            ['type' => 'parking', 'is_available' => true],
                            ['type' => 'wifi', 'is_available' => false],
                            ['type' => 'showers', 'is_available' => true],
                            ['type' => 'security', 'is_available' => true],
                        ];
                        
                        foreach ($equipment as $eq) {
                            ProfileCenterEquipment::create([
                                'profile_center_id' => $profileCentre->id,
                                'type' => $eq['type'],
                                'is_available' => $eq['is_available'],
                            ]);
                        }
                        
                        // Add services for this center
                        $services = ServiceCategory::all();
                        foreach ($services as $service) {
                            // Set different prices for each center
                            $price = $service->suggested_price;
                            if ($service->is_standard) {
                                // Standard service (basic camping)
                                $price = 15.00; // Default price for basic camping
                            }
                            
                            ProfileCenterService::create([
                                'profile_center_id' => $profileCentre->id,
                                'service_category_id' => $service->id,
                                'price' => $price,
                                'unit' => $service->unit,
                                'description' => $service->description,
                                'is_available' => true,
                                'is_standard' => $service->is_standard,
                                'min_quantity' => 1,
                            ]);
                        }
                        break;

                    case 'fournisseur':
                        ProfileFournisseur::create([
                            'profile_id' => $profile->id,
                            'intervale_prix' => '100-500',
                            'product_category' => 'Equipement',
                        ]);
                        break;

                    case 'guide':
                        ProfileGuide::create([
                            'profile_id' => $profile->id,
                            'experience' => 5,
                            'tarif' => 100,
                            'zone_travail' => 'Tunisie',
                        ]);
                        break;
                }
            }
        }
    }
}