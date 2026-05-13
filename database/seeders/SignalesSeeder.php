<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Signales;
use App\Models\User;
use App\Models\Camping_zones;

class SignalesSeeder extends Seeder
{
    public function run(): void
    {
        Signales::truncate();

        $users = User::pluck('id')->toArray();
        $zones = Camping_zones::pluck('id')->toArray();
        $adminId = User::whereHas('role', fn($q) => $q->where('name', 'admin'))->value('id')
            ?? User::first()->id;

        if (empty($users) || empty($zones)) {
            $this->command->warn('Aucun utilisateur ou zone trouve.');
            return;
        }

        $contenuExemples = [
            'Zone dangereuse, presence d animaux sauvages non signalee.',
            'Terrain glissant apres les pluies, risque de chute.',
            'Dechets abandonnes en grande quantite dans cette zone.',
            'Panneau de securite manquant a l entree de la zone.',
            'Acces bloque par des travaux non balises.',
            'Presence de feux de camp non autorises.',
            'Comportement suspect signale par plusieurs campeurs.',
            'Infrastructure endommagee : pont en mauvais etat.',
            'Source d eau contaminee signalee par les riverains.',
            'Route d acces impraticable suite aux intemperies.',
            'Camping sauvage non autorise dans zone protegee.',
            'Depot illegal de dechets chimiques observe.',
            'Vandalisme des equipements de la zone.',
            'Zone inondee, danger pour les campeurs.',
            'Fils electriques a decouvert pres des emplacements.',
        ];

        $types = ['zone', 'securite', 'environnement', 'infrastructure', 'comportement'];

        $signalements = [
            ['status' => 'pending',   'admin_id' => null,     'validated_at' => null,               'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'pending',   'admin_id' => null,     'validated_at' => null,               'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'pending',   'admin_id' => null,     'validated_at' => null,               'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'pending',   'admin_id' => null,     'validated_at' => null,               'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'pending',   'admin_id' => null,     'validated_at' => null,               'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'validated', 'admin_id' => $adminId, 'validated_at' => now()->subDays(2),  'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'validated', 'admin_id' => $adminId, 'validated_at' => now()->subDays(5),  'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'validated', 'admin_id' => $adminId, 'validated_at' => now()->subDays(1),  'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'rejected',  'admin_id' => $adminId, 'validated_at' => null,               'rejected_at' => now()->subDays(3),  'rejection_reason' => 'Signalement non fonde apres verification sur place.'],
            ['status' => 'rejected',  'admin_id' => $adminId, 'validated_at' => null,               'rejected_at' => now()->subDays(7),  'rejection_reason' => 'Information incorrecte, zone en bon etat.'],
            ['status' => 'rejected',  'admin_id' => $adminId, 'validated_at' => null,               'rejected_at' => now()->subDays(4),  'rejection_reason' => 'Doublon d un signalement deja traite.'],
            ['status' => 'pending',   'admin_id' => null,     'validated_at' => null,               'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'pending',   'admin_id' => null,     'validated_at' => null,               'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'validated', 'admin_id' => $adminId, 'validated_at' => now()->subDays(10), 'rejected_at' => null,               'rejection_reason' => null],
            ['status' => 'rejected',  'admin_id' => $adminId, 'validated_at' => null,               'rejected_at' => now()->subDays(6),  'rejection_reason' => 'Zone deja securisee par les autorites competentes.'],
        ];

        foreach ($signalements as $index => $extra) {
            Signales::create(array_merge([
                'user_id'    => $users[array_rand($users)],
                'zone_id'    => $zones[array_rand($zones)],
                'target_id'  => null,
                'type'       => $types[array_rand($types)],
                'contenu'    => $contenuExemples[$index % count($contenuExemples)],
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now(),
            ], $extra));
        }

        $this->command->info(count($signalements) . ' signalements crees avec succes.');
    }
}
