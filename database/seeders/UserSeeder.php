<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [

            [
                'id' => 3,
                'first_name' => 'Sarah',
                'last_name' => 'Camper',
                'email' => 'sarah@example.com',
                'password' => Hash::make('password'),
                'role_id' => 4,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'first_name' => 'Mike',
                'last_name' => 'Smith',
                'email' => 'mike@example.com',
                'password' => Hash::make('password'),
                'role_id' => 4,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('users')->insert($users);
    }
}