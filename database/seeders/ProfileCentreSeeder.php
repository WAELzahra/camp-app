<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profile;
use App\Models\User;
use App\Models\ProfileCentre;
use App\Models\ServiceCategory;
use App\Models\ProfileCenterEquipment;
use App\Models\ProfileCenterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfileCentreSeeder extends Seeder
{
    public function run(): void
    {
        // Get users by email from your existing UserSeeder
        $users = User::whereIn('email', [
            'njkhouja@gmail.com',      // Center Manager (role_id = 3)
            'deadxshot660@gmail.com',  // Camper (role_id = 1)
            'sarah@example.com',       // Fournisseur (role_id = 4)
            'mike@example.com'         // Fournisseur (role_id = 4)
        ])->get()->keyBy('email');

        // Check if we have the center manager user
        if (!$users->has('njkhouja@gmail.com')) {
            $this->command->error("Center manager (njkhouja@gmail.com) not found! Creating centers may fail.");
            return;
        }

        // Create sample centers with full service system
        // All centers should be owned by the center manager (njkhouja@gmail.com)
        $centers = [
            [
                'user_email' => 'njkhouja@gmail.com', // Center Manager
                'name' => 'Tunisia Camp Center',
                'bio' => 'A beautiful camping center in the heart of Tunisia with amazing views and modern facilities.',
                'address' => '123 Camping Street, Tunis 1000',
                'capacity' => 50,
                'price_per_night' => 25.00,
                'category' => 'Premium',
                'latitude' => 36.8065,
                'longitude' => 10.1815,
            ],
            [
                'user_email' => 'njkhouja@gmail.com', // Same center manager
                'name' => 'Sousse Beach Camp',
                'bio' => 'Camping by the beach with direct access to the Mediterranean Sea and beach activities.',
                'address' => '456 Beach Road, Sousse 4000',
                'capacity' => 30,
                'price_per_night' => 20.00,
                'category' => 'Beachfront',
                'latitude' => 35.8254,
                'longitude' => 10.6360,
            ],
            [
                'user_email' => 'njkhouja@gmail.com', // Same center manager
                'name' => 'Atlas Mountain Retreat',
                'bio' => 'Experience mountain camping with hiking trails and breathtaking views.',
                'address' => '789 Mountain Path, Bizerte 7000',
                'capacity' => 20,
                'price_per_night' => 30.00,
                'category' => 'Mountain',
                'latitude' => 37.2749,
                'longitude' => 9.8739,
            ],
            [
                'user_email' => 'njkhouja@gmail.com', // Same center manager
                'name' => 'Sahara Desert Oasis',
                'bio' => 'Authentic desert camping experience with traditional tents and star gazing.',
                'address' => 'Desert Camp, Douz 4260',
                'capacity' => 25,
                'price_per_night' => 35.00,
                'category' => 'Desert',
                'latitude' => 33.4667,
                'longitude' => 9.0167,
            ],
            [
                'user_email' => 'njkhouja@gmail.com', // Same center manager
                'name' => 'Forest Adventure Camp',
                'bio' => 'Eco-friendly camping in the forest with wildlife watching and nature trails.',
                'address' => 'Forest Road, Ain Draham 8100',
                'capacity' => 40,
                'price_per_night' => 28.00,
                'category' => 'Eco-Friendly',
                'latitude' => 36.7739,
                'longitude' => 8.6944,
            ],
        ];

        $serviceCategories = ServiceCategory::all();

        // Check if the profiles table has a record for the center manager
        $centerManagerUser = $users['njkhouja@gmail.com'];
        $centerManagerProfile = Profile::where('user_id', $centerManagerUser->id)->first();

        if (!$centerManagerProfile) {
            // Create profile for center manager if it doesn't exist
            $centerManagerProfile = Profile::create([
                'user_id' => $centerManagerUser->id,
                'bio' => 'Experienced camping center manager with 10 years in the tourism industry.',
                'cover_image' => null,
                'type' => 'centre',
                'activities' => json_encode([]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $centerIndex = 1; // Track center index for profile_center_id

        foreach ($centers as $centerData) {
            $user = $users[$centerData['user_email']] ?? null;
            
            if (!$user) {
                $this->command->error("User with email {$centerData['user_email']} not found!");
                continue;
            }

            // Check if profile_centre already exists for this name
            $existingCentre = ProfileCentre::where('name', $centerData['name'])->first();
            if ($existingCentre) {
                $this->command->info("Center '{$centerData['name']}' already exists. Skipping creation.");
                continue;
            }

            // Create center in profile_centres table
            $profileCentre = ProfileCentre::create([
                'profile_id' => $centerManagerProfile->id,
                'name' => $centerData['name'],
                'adresse' => $centerData['address'],
                'capacite' => $centerData['capacity'],
                'price_per_night' => $centerData['price_per_night'],
                'category' => $centerData['category'],
                'services_offerts' => 'Basic facilities included: toilets, drinking water, parking',
                'additional_services_description' => 'Contact us for special requests and group rates',
                'latitude' => $centerData['latitude'],
                'longitude' => $centerData['longitude'],
                'contact_email' => $centerData['user_email'],
                'contact_phone' => '+216' . rand(20, 29) . rand(100000, 999999),
                'manager_name' => $user->first_name . ' ' . $user->last_name,
                'established_date' => Carbon::now()->subYears(rand(1, 10))->format('Y-m-d'),
                'disponibilite' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Also create a record in the centres table (if it exists) for consistency
            if (Schema::hasTable('centres')) {
                DB::table('centres')->updateOrInsert(
                    ['id' => $centerIndex],
                    [
                        'name' => $centerData['name'],
                        'description' => $centerData['bio'],
                        'ville' => explode(', ', $centerData['address'])[1] ?? 'Tunis',
                        'adresse' => $centerData['address'],
                        'latitude' => $centerData['latitude'],
                        'longitude' => $centerData['longitude'],
                        'capacite' => $centerData['capacity'],
                        'price_per_night' => $centerData['price_per_night'],
                        'disponibilite' => true,
                        'category' => $centerData['category'],
                        'contact_email' => $centerData['user_email'],
                        'contact_phone' => '+216' . rand(20, 29) . rand(100000, 999999),
                        'manager_name' => $user->first_name . ' ' . $user->last_name,
                        'user_id' => $user->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]
                );
                $centerIndex++;
            }

            // Add equipment using only allowed ENUM values
            $equipmentMap = [
                'Premium' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => true],
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => true],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'kitchen', 'is_available' => true],
                    ['type' => 'bbq_area', 'is_available' => true],
                    ['type' => 'security', 'is_available' => true],
                    ['type' => 'swimming_pool', 'is_available' => true],
                ],
                'Beachfront' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => true],
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => true],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'bbq_area', 'is_available' => true],
                    ['type' => 'security', 'is_available' => true],
                    ['type' => 'swimming_pool', 'is_available' => false], // Beachfront has sea instead
                    ['type' => 'kitchen', 'is_available' => true],
                ],
                'Mountain' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => true],
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => false],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'kitchen', 'is_available' => true],
                    ['type' => 'bbq_area', 'is_available' => true],
                    ['type' => 'security', 'is_available' => true],
                    ['type' => 'swimming_pool', 'is_available' => false],
                ],
                'Desert' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => false], // Limited in desert
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => false],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'security', 'is_available' => true],
                    ['type' => 'kitchen', 'is_available' => true],
                    ['type' => 'bbq_area', 'is_available' => true],
                    ['type' => 'swimming_pool', 'is_available' => false],
                ],
                'Eco-Friendly' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => false], // Solar only
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => false],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'kitchen', 'is_available' => true],
                    ['type' => 'bbq_area', 'is_available' => false], // Eco-friendly camps may restrict BBQs
                    ['type' => 'security', 'is_available' => true],
                    ['type' => 'swimming_pool', 'is_available' => false],
                ],
            ];

            $equipment = $equipmentMap[$centerData['category']] ?? $equipmentMap['Premium'];
            foreach ($equipment as $eq) {
                // Double-check that the type is in the allowed ENUM values
                $allowedTypes = ['toilets', 'drinking_water', 'electricity', 'parking', 'wifi', 'showers', 'security', 'kitchen', 'bbq_area', 'swimming_pool'];
                if (!in_array($eq['type'], $allowedTypes)) {
                    $this->command->error("Skipping invalid equipment type: {$eq['type']} for center: {$centerData['name']}");
                    continue;
                }
                
                ProfileCenterEquipment::create([
                    'profile_center_id' => $profileCentre->id,
                    'type' => $eq['type'],
                    'is_available' => $eq['is_available'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            // Add services with center-specific pricing
            foreach ($serviceCategories as $service) {
                // Customize prices based on center category
                $basePrice = $service->suggested_price;
                $multiplier = 1.0;
                
                switch ($centerData['category']) {
                    case 'Beachfront':
                        $multiplier = 1.2; // Beachfront is 20% more expensive
                        break;
                    case 'Desert':
                        $multiplier = 1.3; // Desert is 30% more expensive (remote location)
                        break;
                    case 'Mountain':
                        $multiplier = 1.1; // Mountain is 10% more expensive
                        break;
                    case 'Premium':
                        $multiplier = 1.25; // Premium is 25% more expensive
                        break;
                    case 'Eco-Friendly':
                        $multiplier = 1.15; // Eco-friendly is 15% more expensive
                        break;
                }
                
                $price = round($basePrice * $multiplier, 2);
                
                // For standard service, use the center's base price per night
                if ($service->is_standard) {
                    $price = $centerData['price_per_night'];
                }
                
                // Make some services unavailable for certain centers
                $isAvailable = true;
                if ($centerData['category'] === 'Desert' && in_array($service->name, ['Tent Rental', 'Sleeping Bag Rental'])) {
                    $isAvailable = false; // Desert camp provides own tents
                }
                if ($centerData['category'] === 'Eco-Friendly' && in_array($service->name, ['BBQ Equipment'])) {
                    $isAvailable = false; // Eco-friendly camps may restrict BBQs
                }
                
                // Check if service already exists for this center
                $existingService = ProfileCenterService::where('profile_center_id', $profileCentre->id)
                    ->where('service_category_id', $service->id)
                    ->first();
                    
                if (!$existingService) {
                    ProfileCenterService::create([
                        'profile_center_id' => $profileCentre->id,
                        'service_category_id' => $service->id,
                        'price' => $price,
                        'unit' => $service->unit,
                        'description' => $centerData['name'] . ' - ' . $service->description,
                        'is_available' => $isAvailable,
                        'is_standard' => $service->is_standard,
                        'min_quantity' => 1,
                        'max_quantity' => $service->name === 'Tent Rental' ? 20 : null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
            
            $this->command->info("✓ Created center: {$centerData['name']} with services and equipment.");
        }

        $this->command->info('ProfileCentreSeeder completed successfully!');
    }
}