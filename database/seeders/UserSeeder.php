<?php
<<<<<<< HEAD
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
=======

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Profile;
use App\Models\ProfileGroupe;
use App\Models\ProfileCentre;
use App\Models\ProfileFournisseur;
use App\Models\ProfileGuide;

class UserSeeder extends Seeder
{
    public function run()
    {
        $roles = Role::all()->keyBy('name');

        $usersData = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Campeur User',
                'email' => 'campeur@example.com',
                'password' => bcrypt('password'),
                'role' => 'campeur',
            ],
            [
                'name' => 'Groupe User',
                'email' => 'groupe@example.com',
                'password' => bcrypt('password'),
                'role' => 'groupe',
            ],
            [
                'name' => 'Centre User',
                'email' => 'centre@example.com',
                'password' => bcrypt('password'),
                'role' => 'centre',
            ],
            [
                'name' => 'Fournisseur User',
                'email' => 'fournisseur@example.com',
                'password' => bcrypt('password'),
                'role' => 'fournisseur',
            ],
            [
                'name' => 'Guide User',
                'email' => 'guide@example.com',
                'password' => bcrypt('password'),
                'role' => 'guide',
            ],
        ];

        foreach ($usersData as $data) {
            $role = $roles[$data['role']];
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role_id' => $role->id,
            ]);

            // Si ce n'est pas un admin, créer un profil
            if ($data['role'] !== 'admin') {
                $profile = Profile::create([
                    'user_id' => $user->id,
                    'bio' => 'Bio for ' . $user->name,
                    'type' => $data['role'],
                ]);

                // Créer les profils spécialisés selon le rôle
                switch ($data['role']) {
                    case 'groupe':
                        ProfileGroupe::create([
                            'profile_id' => $profile->id,
                            'nom_groupe' => 'Groupe ' . $user->name,
                            'cin_responsable' => '12345678',
                        ]);
                        break;

                    case 'centre':
                        ProfileCentre::create([
                            'profile_id' => $profile->id,
                            'capacite' => 50,
                            'services_offerts' => 'Service 1, Service 2',
                        ]);
                        break;

                    case 'fournisseur':
                        ProfileFournisseur::create([
                            'profile_id' => $profile->id,
                            'intervale_prix' => '100-500',
                            'product_category' => 'Equipement',
                        ]);
                        break;

                    case 'guide':
                        ProfileGuide::create([
                            'profile_id' => $profile->id,
                            'experience' => 5,
                            'tarif' => 100,
                            'zone_travail' => 'Tunisie',
                        ]);
                        break;
                }
            }
>>>>>>> origin/sprint-3
        }
    }
}
