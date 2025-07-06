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
   public function run()
{
    $this->call([
        RoleSeeder::class,
        UserSeeder::class,
        CircuitSeeder::class,
        EventSeeder::class,
        ReservationsEnvetSeeder::class
    ]);
}



}