<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Events;

class ReservationsEnvetSeeder extends Seeder
{
    public function run()
    {
        // Vérifier qu'on a au moins 1 user et 1 event
        $user = User::first();
        $event = Events::first();

        if (!$user || !$event) {
            $this->command->error('⚠️ Aucun utilisateur ou événement trouvé. Créez-les d\'abord.');
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
        DB::table('reservations_events')->insert([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'group_id' => $event->group_id ?? 1, // ou ajuster
                'name' => fake()->name,
                'email' => fake()->safeEmail,
                'phone' => fake()->phoneNumber,
                'nbr_place' => rand(1, 5),
             'status' => fake()->randomElement([
    'en_attente_paiement',
    'confirmée',
    'en_attente_validation',
    'refusée',
    'annulée_par_utilisateur',
    'annulée_par_organisateur',
    'remboursement_en_attente',
    'remboursée_partielle',
    'remboursée_totale'
]),
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ 10 fausses réservations créées dans reservations_events.');
    }
}
