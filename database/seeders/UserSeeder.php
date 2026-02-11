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
            // Center user with role_id = 3
            [
                'id' => 1,
                'first_name' => 'Center',
                'last_name' => 'Manager',
                'email' => 'njkhouja@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21612345678',
                'ville' => 'Tunis',
                'date_naissance' => '1985-01-15',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 3,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Camper user with role_id = 1
            [
                'id' => 2,
                'first_name' => 'Camper',
                'last_name' => 'User',
                'email' => 'deadxshot660@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21698765432',
                'ville' => 'Sousse',
                'date_naissance' => '1995-05-20',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 1,
                'is_active' => 1,
                'first_login' => 1,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'first_name' => 'Sarah',
                'last_name' => 'Camper',
                'email' => 'sarah@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21611111111',
                'ville' => 'Bizerte',
                'date_naissance' => '1998-03-10',
                'sexe' => 'female',
                'langue' => 'fr',
                'role_id' => 4,
                'is_active' => 1,
                'first_login' => 1,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'first_name' => 'Mike',
                'last_name' => 'Smith',
                'email' => 'mike@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21622222222',
                'ville' => 'Hammamet',
                'date_naissance' => '1990-07-25',
                'sexe' => 'male',
                'langue' => 'en',
                'role_id' => 4,
                'is_active' => 1,
                'first_login' => 1,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('users')->insert($users);
    }
}