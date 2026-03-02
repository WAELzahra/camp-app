<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Events;
use App\Models\User;
use App\Models\Circuit;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $groupeUsers = User::whereHas('role', function ($q) {
            $q->where('name', 'groupe');
        })->get();

        $circuits = Circuit::all();

        if ($groupeUsers->isEmpty() || $circuits->isEmpty()) {
            $this->command->warn('Aucun groupe ou circuit disponible pour créer des événements.');
            return;
        }

        $statuses = ['scheduled', 'finished', 'canceled', 'postponed', 'full'];

        foreach ($groupeUsers as $user) {
            for ($i = 0; $i < 3; $i++) {
                $dateSortie = now()->addDays(rand(5, 30));
                $dateRetoure = (clone $dateSortie)->addDays(rand(1, 3));
                $circuit = $circuits->random();

                Events::create([
                    'title' => 'Événement ' . Str::random(5),
                    'group_id' => $user->id,
                    'description' => 'Un événement exceptionnel à vivre avec le groupe.',
                    'category' => 'Aventure',
                    'date_sortie' => $dateSortie->toDateString(),
                    'date_retoure' => $dateRetoure->toDateString(),
                    'ville_passente' => [$circuit->adresse_debut_circuit, $circuit->adresse_fin_circuit],
                    'tags' => 'désert,nature,exploration',
                    'nbr_place_total' => $total = rand(15, 50),
                    'nbr_place_restante' => rand(0, $total),
                    'prix_place' => rand(50, 200) + rand(0, 99) / 100,
                    'circuit_id' => $circuit->id,
                    'is_active' => true,
                    'status' => $statuses[array_rand($statuses)],
                ]);
            }
        }
    }
}
