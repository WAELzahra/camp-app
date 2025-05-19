<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'campeur'], ['description' => 'Utilisateur campeur']);
        Role::firstOrCreate(['name' => 'guide'], ['description' => 'Guide professionnel']);
        Role::firstOrCreate(['name' => 'centre'], ['description' => 'Centre de camping']);
        Role::firstOrCreate(['name' => 'fournisseur'], ['description' => 'Fournisseur de matériel']);
        Role::firstOrCreate(['name' => 'groupe'], ['description' => 'Groupe de randonneurs']);
        Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrateur de la plateforme']);
    }
}
