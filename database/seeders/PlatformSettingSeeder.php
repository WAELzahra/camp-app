<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformSetting;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Withdrawal settings
            [
                'key'         => 'withdrawal_allowed_days',
                'value'       => json_encode([1, 4]), // Monday=1, Thursday=4
                'label'       => 'Jours de retrait autorisés',
                'description' => 'Jours de la semaine où les demandes de retrait sont acceptées (1=Lundi, 2=Mardi, ..., 7=Dimanche)',
                'type'        => 'json',
                'group'       => 'withdrawal',
            ],
            [
                'key'         => 'withdrawal_min_amount',
                'value'       => '50',
                'label'       => 'Montant minimum de retrait (TND)',
                'description' => 'Montant minimal qu\'un utilisateur peut demander à retirer',
                'type'        => 'integer',
                'group'       => 'withdrawal',
            ],
            [
                'key'         => 'withdrawal_processing_days',
                'value'       => '3',
                'label'       => 'Délai de traitement (jours ouvrables)',
                'description' => 'Nombre de jours ouvrables pour traiter une demande de retrait',
                'type'        => 'integer',
                'group'       => 'withdrawal',
            ],
            [
                'key'         => 'withdrawal_enabled',
                'value'       => '1',
                'label'       => 'Retraits activés',
                'description' => 'Activer ou désactiver globalement les demandes de retrait',
                'type'        => 'boolean',
                'group'       => 'withdrawal',
            ],
            // Platform settings
            [
                'key'         => 'platform_commission_rate',
                'value'       => '5',
                'label'       => 'Commission plateforme (%)',
                'description' => 'Pourcentage prélevé par la plateforme sur chaque transaction',
                'type'        => 'integer',
                'group'       => 'finance',
            ],
            [
                'key'         => 'platform_name',
                'value'       => 'CampTN',
                'label'       => 'Nom de la plateforme',
                'description' => 'Nom affiché dans les communications',
                'type'        => 'string',
                'group'       => 'general',
            ],
            [
                'key'         => 'support_email',
                'value'       => 'support@camptn.tn',
                'label'       => 'Email de support',
                'description' => 'Adresse email pour les demandes de support',
                'type'        => 'string',
                'group'       => 'general',
            ],
        ];

        foreach ($settings as $s) {
            PlatformSetting::updateOrCreate(['key' => $s['key']], $s);
        }
    }
}
