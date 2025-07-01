<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = ['campeur', 'groupe', 'centre', 'fournisseur', 'guide', 'admin'];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(['name' => $roleName]);
        }
    }
}

