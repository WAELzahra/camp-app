<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Circuit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
<<<<<<< HEAD
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            UserSeeder::class,
            FounisseurProfileSeeder::class,
            ProfileCentreSeeder::class
        ]);
    }
    
=======
   public function run()
{
    $this->call([
        RoleSeeder::class,
        UserSeeder::class,
        CircuitSeeder::class,
        EventSeeder::class,
        ReservationEventSeeder::class,
   
    ]);
}



>>>>>>> origin/sprint-3
}
