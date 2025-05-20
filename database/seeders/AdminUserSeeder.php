<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role; // si tu as une table roles

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        // Récupérer le rôle admin (ou créer si inexistant)
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Créer un utilisateur admin
        User::create([
            'name' => 'Admin Principal',
            'email' => 'admin@example.com',
            'password' => 'password', // si tu as 'password' casté en hashed dans User model
            'role_id' => $adminRole->id,
            'is_active' => true,
            'adresse' => 'Admin Address',
            'phone_number' => '123456789',
            'ville' => 'Admin City',
            'date_naissance' => '1990-01-01',
            'sexe' => 'M',
            'first_login' => false,
            'nombre_signalement' => 0,
        ]);
    }
}
