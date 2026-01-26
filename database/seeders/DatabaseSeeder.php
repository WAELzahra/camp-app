<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,           // Must be first (creates roles)
            ServiceCategorySeeder::class, // Must be before UserSeeder (for services)
            UserSeeder::class,           // Creates users and profiles
            ProfileCentreSeeder::class,  // Creates additional centers with services
            // Add other seeders as needed
        ]);
    }
}