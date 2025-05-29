<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Str;


class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Alice Campeur',
                'email' => 'alice@campeur.com',
                'role' => 'campeur',
            ],
            [
                'name' => 'Bob Guide',
                'email' => 'bob@guide.com',
                'role' => 'guide',
            ],
            [
                'name' => 'Charlie Centre',
                'email' => 'charlie@centre.com',
                'role' => 'centre',
            ],
            [
                'name' => 'Diane Fournisseur',
                'email' => 'diane@fournisseur.com',
                'role' => 'fournisseur',
            ],
            [
                'name' => 'Eve Groupe',
                'email' => 'eve@groupe.com',
                'role' => 'groupe',
            ],
            [
                'name' => 'Admin Master',
                'email' => 'admin@camp.com',
                'role' => 'admin',
            ],
        ];

        foreach ($users as $user) {
            $role = Role::where('name', $user['role'])->first();

            DB::table('users')->insert([
                'name' => $user['name'],
                'email' => $user['email'],
                'adresse' => '123 Rue de Camping',
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make('password'), // default password
                'phone_number' => '123456789',
                'ville' => 'Tunis',
                'date_naissance' => '1990-01-01',
                'sexe' => 'Femme',
                'langue' => 'fr',
                'role_id' => $role->id,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => Carbon::now(),
                'avatar' => null,
                'remember_token' => Str::random(10),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
