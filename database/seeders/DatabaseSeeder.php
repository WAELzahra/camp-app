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
            // ── NEW: additional campaign events (run BEFORE ReservationsSeeder
            //         so that the existing seeder can also reserve them if needed)
            CampingEventsSeeder::class,
            ReservationsSeeder::class,
            FeedbacksAndFavoritesSeeder::class,
            AnnoncesAndSocialSeeder::class,
            // ── NEW: campaign-specific event reservations, feedbacks & notifications
            //         These target only the events created by CampingEventsSeeder
            //         and are idempotent — safe to re-run after migrate:fresh --seed.
            CampingEventReservationsSeeder::class,
            CampingEventFeedbacksSeeder::class,
            CampingEventNotificationsSeeder::class,
        ]);

        // One-time data fixes — run manually, NOT on every db:seed:
        //   php artisan db:seed --class=GearPriceFixSeeder
    }
}