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
            ChatGroupsSeeder::class,      
            ChatMessagesSeeder::class,  

        ]);
    }
}