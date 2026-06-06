<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ServiceCategorySeeder::class,
            FixCampingZonesSeeder::class,
            UsersAndProfilesSeeder::class,
            ProfileCampeursSeeder::class,
            CampingCentresSeeder::class,
            CentreProfilesSeeder::class,
            PartnerCentresSeeder::class,
            PartnerCentreSeeder::class,
            BoutiquesAndMateriellesSeeder::class,
            GroupesAndEventsSeeder::class,
            ReservationsSeeder::class,
            FeedbacksAndFavoritesSeeder::class,
            AnnoncesAndSocialSeeder::class,
        ]);

        // One-time data fixes — run manually, NOT on every db:seed:
        //   php artisan db:seed --class=GearPriceFixSeeder
    }
}