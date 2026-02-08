<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ServiceCategory::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $services = [
            [
                'name' => 'Basic Camping',
                'description' => 'Sleep with your own tent - access to basic facilities (toilets, drinking water, parking)',
                'is_standard' => true,
                'suggested_price' => 15.00,
                'min_price' => 5.00,
                'unit' => 'person/night',
                'icon' => 'tent',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Cabin Rental',
                'description' => 'Cozy cabin accommodation with basic furniture, electricity, and shared facilities',
                'is_standard' => false,
                'suggested_price' => 80.00,
                'min_price' => 50.00,
                'unit' => 'night',
                'icon' => 'home',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Breakfast',
                'description' => 'Continental breakfast with coffee, bread, jam, and fruits',
                'is_standard' => false,
                'suggested_price' => 20.00,
                'min_price' => 10.00,
                'unit' => 'person',
                'icon' => 'coffee',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Lunch',
                'description' => 'Traditional Tunisian lunch with salad, main course, and dessert',
                'is_standard' => false,
                'suggested_price' => 30.00,
                'min_price' => 15.00,
                'unit' => 'person',
                'icon' => 'utensils',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Dinner',
                'description' => 'Evening meal with traditional dishes and barbecue options',
                'is_standard' => false,
                'suggested_price' => 35.00,
                'min_price' => 20.00,
                'unit' => 'person',
                'icon' => 'moon',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Tent Rental',
                'description' => 'Standard camping tent for 2-4 people',
                'is_standard' => false,
                'suggested_price' => 50.00,
                'min_price' => 25.00,
                'unit' => 'night',
                'icon' => 'tent',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Sleeping Bag Rental',
                'description' => 'Warm sleeping bag suitable for all seasons',
                'is_standard' => false,
                'suggested_price' => 15.00,
                'min_price' => 8.00,
                'unit' => 'night',
                'icon' => 'bed',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Guided Tour',
                'description' => 'Guided hiking or nature tour with experienced guide',
                'is_standard' => false,
                'suggested_price' => 75.00,
                'min_price' => 40.00,
                'unit' => 'person',
                'icon' => 'map',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'BBQ Equipment',
                'description' => 'Barbecue grill and tools rental',
                'is_standard' => false,
                'suggested_price' => 25.00,
                'min_price' => 12.00,
                'unit' => 'day',
                'icon' => 'flame',
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'name' => 'Transport Service',
                'description' => 'Transport from nearest city to camping site',
                'is_standard' => false,
                'suggested_price' => 40.00,
                'min_price' => 20.00,
                'unit' => 'person',
                'icon' => 'car',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Camping Chair Rental',
                'description' => 'Comfortable camping chair',
                'is_standard' => false,
                'suggested_price' => 10.00,
                'min_price' => 5.00,
                'unit' => 'day',
                'icon' => 'chair',
                'sort_order' => 11,
                'is_active' => true,
            ],
        ];

        foreach ($services as $service) {
            ServiceCategory::create($service);
        }

        $this->command->info('Service categories seeded successfully.');
    }
}