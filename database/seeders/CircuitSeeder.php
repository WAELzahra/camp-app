<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Circuit;

class CircuitSeeder extends Seeder
{
    public function run(): void
    {
        $circuits = [
            [
                'adresse_debut_circuit' => 'Douz',
                'adresse_fin_circuit' => 'Ksar Ghilane',
                'description' => 'Traversée du désert tunisien en 4x4 et randonnée.',
                'difficulty' => 'moyenne',
                'distance_km' => 120,
                'estimation_temps' => 6.0,
                'danger_level' => 'moderate',
            ],
            [
                'adresse_debut_circuit' => 'Tozeur',
                'adresse_fin_circuit' => 'Nefta',
                'description' => 'Exploration des oasis du sud tunisien.',
                'difficulty' => 'facile',
                'distance_km' => 45,
                'estimation_temps' => 3.0,
                'danger_level' => 'low',
            ],
            [
                'adresse_debut_circuit' => 'Ain Draham',
                'adresse_fin_circuit' => 'Tabarka',
                'description' => 'Randonnée en forêt dans le nord-ouest.',
                'difficulty' => 'difficile',
                'distance_km' => 30,
                'estimation_temps' => 4.0,
                'danger_level' => 'high',
            ],
        ];

        foreach ($circuits as $circuit) {
            Circuit::create($circuit);
        }
    }
}
