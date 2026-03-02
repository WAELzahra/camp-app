<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReservationEventSeeder extends Seeder
{
    public function run()
    {
        $reservations = [
            // Event 1: Camping Saharien - Douz (group_id = 4)
            [
                'user_id' => 3, // deadxshot660@gmail.com (camper)
                'event_id' => 1,
                'group_id' => 4,
                'name' => 'DeadXShot Camper',
                'email' => 'deadxshot660@gmail.com',
                'phone' => '+21650123458',
                'nbr_place' => 2,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 4, // Created by group owner (Nejikh Group)
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'user_id' => 9, // Sarah Camper
                'event_id' => 1,
                'group_id' => 4,
                'name' => 'Sarah Camper',
                'email' => 'sarah@example.com',
                'phone' => '+21650123464',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 4,
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(4),
            ],
            [
                'user_id' => 10, // Mike Smith
                'event_id' => 1,
                'group_id' => 4,
                'name' => 'Mike Smith',
                'email' => 'mike@example.com',
                'phone' => '+21650123465',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'en_attente_paiement',
                'created_by' => 4,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],

            // Event 2: Randonnée à Ain Draham (group_id = 5 - Forest Rangers)
            [
                'user_id' => 3, // deadxshot660@gmail.com
                'event_id' => 2,
                'group_id' => 5,
                'name' => 'DeadXShot Camper',
                'email' => 'deadxshot660@gmail.com',
                'phone' => '+21650123458',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 5,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'user_id' => 9, // Sarah Camper
                'event_id' => 2,
                'group_id' => 5,
                'name' => 'Sarah Camper',
                'email' => 'sarah@example.com',
                'phone' => '+21650123464',
                'nbr_place' => 2,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 5,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'user_id' => 10, // Mike Smith
                'event_id' => 2,
                'group_id' => 5,
                'name' => 'Mike Smith',
                'email' => 'mike@example.com',
                'phone' => '+21650123465',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 5,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],

            // Event 3: Voyage aux Îles Kerkennah (group_id = 6 - Coastal Adventures)
            [
                'user_id' => 3, // deadxshot660@gmail.com
                'event_id' => 3,
                'group_id' => 6,
                'name' => 'DeadXShot Camper',
                'email' => 'deadxshot660@gmail.com',
                'phone' => '+21650123458',
                'nbr_place' => 2,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 6,
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ],
            [
                'user_id' => 8, // Ahmed Guide
                'event_id' => 3,
                'group_id' => 6,
                'name' => 'Ahmed Guide',
                'email' => 'ahmed.guide@example.com',
                'phone' => '+21650123463',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 6,
                'created_at' => now()->subDays(6),
                'updated_at' => now()->subDays(6),
            ],
            [
                'user_id' => 10, // Mike Smith
                'event_id' => 3,
                'group_id' => 6,
                'name' => 'Mike Smith',
                'email' => 'mike@example.com',
                'phone' => '+21650123465',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'en_attente_paiement',
                'created_by' => 6,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'user_id' => 9, // Sarah Camper
                'event_id' => 3,
                'group_id' => 6,
                'name' => 'Sarah Camper',
                'email' => 'sarah@example.com',
                'phone' => '+21650123464',
                'nbr_place' => 2,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 6,
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(4),
            ],

            // Event 4: Camping à Tabarka (Fully booked - group_id = 4)
            [
                'user_id' => 3, // deadxshot660@gmail.com
                'event_id' => 4,
                'group_id' => 4,
                'name' => 'DeadXShot Camper',
                'email' => 'deadxshot660@gmail.com',
                'phone' => '+21650123458',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 4,
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(15),
            ],
            [
                'user_id' => 9, // Sarah Camper
                'event_id' => 4,
                'group_id' => 4,
                'name' => 'Sarah Camper',
                'email' => 'sarah@example.com',
                'phone' => '+21650123464',
                'nbr_place' => 2,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 4,
                'created_at' => now()->subDays(14),
                'updated_at' => now()->subDays(14),
            ],
            [
                'user_id' => 10, // Mike Smith
                'event_id' => 4,
                'group_id' => 4,
                'name' => 'Mike Smith',
                'email' => 'mike@example.com',
                'phone' => '+21650123465',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 4,
                'created_at' => now()->subDays(13),
                'updated_at' => now()->subDays(13),
            ],
            [
                'user_id' => 8, // Ahmed Guide
                'event_id' => 4,
                'group_id' => 4,
                'name' => 'Ahmed Guide',
                'email' => 'ahmed.guide@example.com',
                'phone' => '+21650123463',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 4,
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(12),
            ],
            [
                'user_id' => 2, // Center Manager
                'event_id' => 4,
                'group_id' => 4,
                'name' => 'Center Manager',
                'email' => 'njkhouja@gmail.com',
                'phone' => '+21650123457',
                'nbr_place' => 2,
                'payment_id' => null,
                'status' => 'annulée_par_utilisateur',
                'created_by' => 4,
                'created_at' => now()->subDays(11),
                'updated_at' => now()->subDays(5),
            ],

            // Event 5: Randonnée à Zaghouan (group_id = 5)
            [
                'user_id' => 9, // Sarah Camper
                'event_id' => 5,
                'group_id' => 5,
                'name' => 'Sarah Camper',
                'email' => 'sarah@example.com',
                'phone' => '+21650123464',
                'nbr_place' => 1,
                'payment_id' => null,
                'status' => 'confirmée',
                'created_by' => 5,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'user_id' => 3, // deadxshot660@gmail.com
                'event_id' => 5,
                'group_id' => 5,
                'name' => 'DeadXShot Camper',
                'email' => 'deadxshot660@gmail.com',
                'phone' => '+21650123458',
                'nbr_place' => 2,
                'payment_id' => null,
                'status' => 'en_attente_paiement',
                'created_by' => 5,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
        ];

        DB::table('reservations_events')->insert($reservations);
    }
}