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

class ProfileCentreSeeder extends Seeder
{
    public function run(): void
    {
        // Get users by email
        $users = User::whereIn('email', [
            'centre@example.com',
            'campeur@example.com',
            'groupe@example.com'
        ])->get()->keyBy('email');

        // Create sample centers with full service system
        $centers = [
            [
                'user_email' => 'centre@example.com',
                'name' => 'Mountain Camp Paradise',
                'bio' => 'A beautiful camping center in the mountains with amazing views and modern facilities.',
                'address' => 'Mountain Road, Ain Draham',
                'capacity' => 80,
                'price_per_night' => 30.00,
                'category' => 'Mountain',
                'latitude' => 36.7739,
                'longitude' => 8.6944,
            ],
            [
                'user_email' => 'campeur@example.com', // Using campeur user for second center
                'name' => 'Beach Camp Oasis',
                'bio' => 'Camping by the beach with direct access to the sea and beach activities.',
                'address' => 'Beach Road, Hammamet',
                'capacity' => 120,
                'price_per_night' => 35.00,
                'category' => 'Beachfront',
                'latitude' => 36.4000,
                'longitude' => 10.6167,
            ],
            [
                'user_email' => 'groupe@example.com', // Using groupe user for third center
                'name' => 'Desert Camp Adventure',
                'bio' => 'Experience the authentic desert camping with traditional tents and activities.',
                'address' => 'Desert Road, Douz',
                'capacity' => 60,
                'price_per_night' => 40.00,
                'category' => 'Desert',
                'latitude' => 33.4667,
                'longitude' => 9.0167,
            ],
        ];

        $serviceCategories = ServiceCategory::all();

        foreach ($centers as $centerData) {
            $user = $users[$centerData['user_email']] ?? null;
            
            if (!$user) {
                $this->command->error("User with email {$centerData['user_email']} not found!");
                continue;
            }

            // Create user profile WITHOUT adresse (it goes in profile_centres)
            $profile = Profile::create([
                'user_id' => $user->id,
                'bio' => $centerData['bio'],
                'cover_image' => null,
                'type' => 'centre',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Create center WITH adresse in profile_centres table
            $profileCentre = ProfileCentre::create([
                'profile_id' => $profile->id,
                'name' => $centerData['name'],
                'adresse' => $centerData['address'], // This goes here, not in profiles
                'capacite' => $centerData['capacity'],
                'price_per_night' => $centerData['price_per_night'],
                'category' => $centerData['category'],
                'services_offerts' => 'Basic facilities included',
                'additional_services_description' => 'Contact us for special requests and group rates',
                'latitude' => $centerData['latitude'],
                'longitude' => $centerData['longitude'],
                'contact_email' => $centerData['user_email'],
                'contact_phone' => '+216' . rand(20, 29) . rand(100000, 999999),
                'manager_name' => $user->first_name . ' ' . $user->last_name,
                'established_date' => now()->subYears(rand(1, 10))->format('Y-m-d'),
                'disponibilite' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Add equipment (different for each center type)
            $equipmentMap = [
                'Mountain' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => true],
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => false],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'kitchen', 'is_available' => true],
                ],
                'Beachfront' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => true],
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => true],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'bbq_area', 'is_available' => true],
                ],
                'Desert' => [
                    ['type' => 'toilets', 'is_available' => true],
                    ['type' => 'drinking_water', 'is_available' => true],
                    ['type' => 'electricity', 'is_available' => false], // Limited in desert
                    ['type' => 'parking', 'is_available' => true],
                    ['type' => 'wifi', 'is_available' => false],
                    ['type' => 'showers', 'is_available' => true],
                    ['type' => 'security', 'is_available' => true],
                ],
            ];

            $equipment = $equipmentMap[$centerData['category']] ?? $equipmentMap['Mountain'];
            foreach ($equipment as $eq) {
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
            
            $this->command->info("Created center: {$centerData['name']} with services and equipment.");
        }
    }
}