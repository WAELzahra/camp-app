<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payments;
use App\Models\Reservations_events;
use App\Models\User;
use App\Models\Events;

class ReservationEventSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer des utilisateurs campeurs existants
        $users = User::whereHas('role', function ($q) {
            $q->where('name', 'campeur');
        })->get();

        // Récupérer des groupes existants (utilisateurs avec rôle groupe)
        $groups = User::whereHas('role', function ($q) {
            $q->where('name', 'groupe');
        })->get();

        // Récupérer quelques événements
        $events = Events::inRandomOrder()->take(3)->get();

        if ($users->isEmpty() || $groups->isEmpty() || $events->isEmpty()) {
            $this->command->warn('⚠️  Aucun utilisateur, groupe ou événement trouvé. Seeder annulé.');
            return;
        }

        foreach ($users as $user) {
            $event = $events->random();
            $group = $groups->random();
            $nbr_place = rand(1, 5);

            // Créer le paiement
            $payment = Payments::create([
                'montant' => $nbr_place * 50, // Exemple: 50 DT par place
                'description' => 'Réservation pour événement: ' . ($event->title ?? 'Titre inconnu'),
               'status' => 'annulé',
                'user_id' => $user->id,
                'event_id' => $event->id,
            ]);

            // Créer la réservation associée
            Reservations_events::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'event_id' => $event->id,
                'nbr_place' => $nbr_place,
                'payment_id' => $payment->id,
                'status' => 'annulé',
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '9' . rand(1000000, 9999999), // numéro fictif
                'created_by' => $group->id,
            ]);
        }

        $this->command->info('✅ Réservations d\'événements et paiements générés avec succès.');
    }
}
