<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Expense;
use App\Models\User;
use App\Models\Events;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        Expense::truncate();

        $users  = User::select('id')->get();
        $events = Events::select('id')->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found.');
            return;
        }

        $categories = [
            'transport', 'hébergement', 'nourriture', 'équipement',
            'marketing', 'maintenance', 'salaires', 'location',
            'formation', 'communication', 'assurance', 'autre',
        ];

        $templates = [
            ['titre' => 'Location minibus pour event',       'categorie' => 'transport',      'montant' => 350.00],
            ['titre' => 'Achat tentes et sacs de couchage',  'categorie' => 'équipement',     'montant' => 820.00],
            ['titre' => 'Nourriture groupe 15 personnes',    'categorie' => 'nourriture',     'montant' => 240.00],
            ['titre' => 'Réservation terrain de camping',    'categorie' => 'hébergement',    'montant' => 500.00],
            ['titre' => 'Carburant aller-retour Tozeur',     'categorie' => 'transport',      'montant' => 185.00],
            ['titre' => 'Publicité Facebook & Instagram',    'categorie' => 'marketing',      'montant' => 120.00],
            ['titre' => 'Entretien équipements de randonnée','categorie' => 'maintenance',    'montant' => 95.00],
            ['titre' => 'Guide local Djerba',                'categorie' => 'salaires',       'montant' => 300.00],
            ['titre' => 'Location salle de réunion',         'categorie' => 'location',       'montant' => 80.00],
            ['titre' => 'Formation premiers secours',        'categorie' => 'formation',      'montant' => 250.00],
            ['titre' => 'Abonnement plateforme communication','categorie' => 'communication', 'montant' => 45.00],
            ['titre' => 'Assurance groupe voyage',           'categorie' => 'assurance',      'montant' => 430.00],
            ['titre' => 'Corde et matériel escalade',        'categorie' => 'équipement',     'montant' => 615.00],
            ['titre' => 'Repas équipe organisatrice',        'categorie' => 'nourriture',     'montant' => 110.00],
            ['titre' => 'Impression flyers et affiches',     'categorie' => 'marketing',      'montant' => 75.00],
            ['titre' => 'Transport matériel lourds',         'categorie' => 'transport',      'montant' => 220.00],
            ['titre' => 'Location kayaks et équipements',    'categorie' => 'location',       'montant' => 160.00],
            ['titre' => 'Frais de certification guide',      'categorie' => 'formation',      'montant' => 380.00],
            ['titre' => 'Réparation tente déchirée',         'categorie' => 'maintenance',    'montant' => 40.00],
            ['titre' => 'Achat lampes frontales x20',        'categorie' => 'équipement',     'montant' => 290.00],
            ['titre' => 'Divers / frais imprévus',           'categorie' => 'autre',          'montant' => 65.00],
            ['titre' => 'Assurance responsabilité civile',   'categorie' => 'assurance',      'montant' => 195.00],
            ['titre' => 'Repas participants gala',           'categorie' => 'nourriture',     'montant' => 780.00],
            ['titre' => 'Location véhicule 4x4',             'categorie' => 'transport',      'montant' => 450.00],
            ['titre' => 'Hébergement hôtel équipe',          'categorie' => 'hébergement',    'montant' => 560.00],
        ];

        $statuses = ['confirmé', 'confirmé', 'confirmé', 'brouillon', 'remboursé'];

        foreach ($templates as $i => $t) {
            $userId  = $users[$i % count($users)]->id;
            $eventId = $events->isNotEmpty() ? $events->random()->id : null;
            $daysAgo = rand(1, 90);

            Expense::create([
                'user_id'      => $userId,
                'titre'        => $t['titre'],
                'montant'      => $t['montant'] + rand(-20, 50),
                'categorie'    => $t['categorie'],
                'status'       => $statuses[$i % count($statuses)],
                'date_depense' => now()->subDays($daysAgo)->toDateString(),
                'event_id'     => ($i % 3 === 0) ? $eventId : null,
                'reference'    => ($i % 4 === 0) ? 'FAC-' . strtoupper(substr(md5($i), 0, 8)) : null,
                'notes'        => ($i % 5 === 0) ? 'Approuvé par responsable équipe.' : null,
                'created_at'   => now()->subDays($daysAgo),
                'updated_at'   => now()->subDays($daysAgo),
            ]);
        }

        $this->command->info(count($templates) . ' dépenses créées.');
    }
}
