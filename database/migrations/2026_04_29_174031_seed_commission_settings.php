<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $settings = [
        // ── Commissions (prélevées sur le receveur) ───────────────────────────
        [
            'key'         => 'commission_camper',
            'value'       => '2',
            'label'       => 'Commission Camper',
            'description' => 'Prélevée sur les montants reçus par un camper',
            'type'        => 'integer',
            'group'       => 'commissions',
        ],
        [
            'key'         => 'commission_center',
            'value'       => '8',
            'label'       => 'Commission Centre de camping',
            'description' => 'Prélevée sur les réservations camping',
            'type'        => 'integer',
            'group'       => 'commissions',
        ],
        [
            'key'         => 'commission_group',
            'value'       => '5',
            'label'       => 'Commission Groupe / Organisateur',
            'description' => 'Prélevée sur les événements de groupe',
            'type'        => 'integer',
            'group'       => 'commissions',
        ],
        [
            'key'         => 'commission_supplier',
            'value'       => '10',
            'label'       => 'Commission Fournisseur',
            'description' => 'Prélevée sur les ventes de matériel',
            'type'        => 'integer',
            'group'       => 'commissions',
        ],
        [
            'key'         => 'commission_guide',
            'value'       => '7',
            'label'       => 'Commission Guide',
            'description' => 'Prélevée sur les services de guidage',
            'type'        => 'integer',
            'group'       => 'commissions',
        ],
        // ── Frais de service (ajoutés au prix payé par le client) ────────────
        [
            'key'         => 'service_fee_camper',
            'value'       => '3',
            'label'       => 'Frais de service Camper',
            'description' => 'Facturés à chaque réservation effectuée par un camper',
            'type'        => 'integer',
            'group'       => 'commissions',
        ],
        // ── Paramètres de retrait ─────────────────────────────────────────────
        [
            'key'         => 'withdrawal_fee_percentage',
            'value'       => '2',
            'label'       => 'Frais de retrait (%)',
            'description' => 'Déduits de chaque demande de retrait',
            'type'        => 'integer',
            'group'       => 'withdrawal',
        ],
        [
            'key'         => 'withdrawal_min_amount',
            'value'       => '20',
            'label'       => 'Montant minimum de retrait (TND)',
            'description' => 'Seuil en dessous duquel le retrait est refusé',
            'type'        => 'integer',
            'group'       => 'withdrawal',
        ],
        [
            'key'         => 'withdrawal_processing_days',
            'value'       => '3',
            'label'       => 'Délai de traitement (jours)',
            'description' => 'Jours ouvrables avant exécution du paiement',
            'type'        => 'integer',
            'group'       => 'withdrawal',
        ],
        [
            'key'         => 'withdrawal_allowed_days',
            'value'       => '[1,4]',
            'label'       => 'Jours de traitement',
            'description' => 'Jours de la semaine où les retraits sont traités (1=Lun … 7=Dim)',
            'type'        => 'json',
            'group'       => 'withdrawal',
        ],
        [
            'key'         => 'withdrawal_enabled',
            'value'       => '1',
            'label'       => 'Retraits activés',
            'description' => 'Désactiver bloque toutes les nouvelles demandes',
            'type'        => 'boolean',
            'group'       => 'withdrawal',
        ],
        // ── Passerelles de paiement ───────────────────────────────────────────
        [
            'key'         => 'gateway_konnect_enabled',
            'value'       => '1',
            'label'       => 'Konnect activé',
            'description' => 'Passerelle de paiement principale (carte bancaire)',
            'type'        => 'boolean',
            'group'       => 'gateway',
        ],
        [
            'key'         => 'gateway_flouci_enabled',
            'value'       => '0',
            'label'       => 'Flouci activé',
            'description' => 'Paiement mobile tunisien',
            'type'        => 'boolean',
            'group'       => 'gateway',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->settings as $setting) {
            // Insert only if the key doesn't already exist
            $exists = DB::table('platform_settings')
                ->where('key', $setting['key'])
                ->exists();

            if (!$exists) {
                DB::table('platform_settings')->insert(array_merge($setting, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        $keys = array_column($this->settings, 'key');
        DB::table('platform_settings')->whereIn('key', $keys)->delete();
    }
};
