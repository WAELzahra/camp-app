<?php
// database/seeders/ReservationsCentreSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationsCentreSeeder extends Seeder
{
    public function run()
    {
        $reservations = [
            // Past reservation (completed) - User ID 2 (Camper User)
            [
                'user_id' => 2, // Camper user ID (deadxshot660@gmail.com)
                'centre_id' => 1, // Center ID
                'date_debut' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'date_fin' => Carbon::now()->subDays(7)->format('Y-m-d'),
                'nbr_place' => 3,
                'note' => 'Family camping trip for 3 people',
                'type' => 'Basic Camping, Breakfast',
                'status' => 'approved',
                'total_price' => 165.00,
                'service_count' => 2,
                'created_at' => Carbon::now()->subDays(12),
                'updated_at' => Carbon::now()->subDays(12),
            ],
            // Current reservation (pending approval) - User ID 2
            [
                'user_id' => 2,
                'centre_id' => 1,
                'date_debut' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'date_fin' => Carbon::now()->addDays(8)->format('Y-m-d'),
                'nbr_place' => 2,
                'note' => 'Weekend getaway for 2',
                'type' => 'Cabin Rental',
                'status' => 'pending',
                'total_price' => 240.00,
                'service_count' => 1,
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            // Future reservation (approved) - User ID 2
            [
                'user_id' => 2,
                'centre_id' => 1,
                'date_debut' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'date_fin' => Carbon::now()->addDays(18)->format('Y-m-d'),
                'nbr_place' => 4,
                'note' => 'Family reunion camping',
                'type' => 'Basic Camping, Breakfast, Cabin Rental',
                'status' => 'approved',
                'total_price' => 340.00,
                'service_count' => 3,
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            // Rejected reservation - User ID 2
            [
                'user_id' => 2,
                'centre_id' => 1,
                'date_debut' => Carbon::now()->addDays(25)->format('Y-m-d'),
                'date_fin' => Carbon::now()->addDays(28)->format('Y-m-d'),
                'nbr_place' => 5,
                'note' => 'Large group camping',
                'type' => 'Basic Camping',
                'status' => 'rejected',
                'total_price' => 225.00,
                'service_count' => 1,
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            // Canceled reservation - User ID 2
            [
                'user_id' => 2,
                'centre_id' => 1,
                'date_debut' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'date_fin' => Carbon::now()->addDays(33)->format('Y-m-d'),
                'nbr_place' => 2,
                'note' => 'Canceled due to weather concerns',
                'type' => 'Cabin Rental',
                'status' => 'canceled',
                'total_price' => 240.00,
                'service_count' => 1,
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(4),
            ],
            // Another user's reservation to test center view - User ID 3 (Sarah Camper)
            [
                'user_id' => 3, // Sarah Camper (sarah@example.com)
                'centre_id' => 1,
                'date_debut' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'date_fin' => Carbon::now()->addDays(6)->format('Y-m-d'),
                'nbr_place' => 2,
                'note' => 'Anniversary camping trip',
                'type' => 'Cabin Rental, Breakfast',
                'status' => 'pending',
                'total_price' => 200.00,
                'service_count' => 2,
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            // Approved reservation for another user - User ID 4 (Mike Smith)
            [
                'user_id' => 4, // Mike Smith (mike@example.com)
                'centre_id' => 1,
                'date_debut' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'date_fin' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'nbr_place' => 1,
                'note' => 'Solo camping experience',
                'type' => 'Basic Camping',
                'status' => 'approved',
                'total_price' => 30.00,
                'service_count' => 1,
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3),
            ],
        ];

        DB::table('reservations_centres')->insert($reservations);
    }
}