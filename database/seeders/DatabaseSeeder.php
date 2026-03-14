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
            UserSeeder::class,           
            ProfileCentreSeeder::class,   
            ReservationsCentreSeeder::class, 
            ReservationServiceItemSeeder::class, 
            ConversationSeeder::class,
            ConversationParticipantSeeder::class,
            MessageSeeder::class,
            MessageStatusSeeder::class,
            MessageReactionSeeder::class,

            EventSeeder::class,
            ReservationEventSeeder::class,
            ShopSeeder::class,
        ]);
    }
}