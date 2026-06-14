<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'annulée_solde_impayé' to the status ENUM on all 3 reservation tables.
 * This status is set by the payments:cancel-overdue-balances scheduler command
 * when balance_due_at has passed and the reservation is still confirmée_solde_en_attente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reservations_events
            MODIFY COLUMN status ENUM(
                'en_attente_paiement',
                'confirmée',
                'en_attente_validation',
                'refusée',
                'annulée_par_utilisateur',
                'annulée_par_organisateur',
                'remboursement_en_attente',
                'remboursée_partielle',
                'remboursée_totale',
                'paiement_soumis',
                'paiement_invalide',
                'confirmée_solde_en_attente',
                'solde_soumis',
                'entièrement_payée',
                'annulée_solde_impayé',
                'modified',
                'pending'
            ) NOT NULL DEFAULT 'en_attente_paiement'"
        );

        DB::statement("ALTER TABLE reservations_centres
            MODIFY COLUMN status ENUM(
                'pending',
                'approved',
                'rejected',
                'canceled',
                'modified',
                'paiement_soumis',
                'paiement_invalide',
                'confirmée_solde_en_attente',
                'solde_soumis',
                'entièrement_payée',
                'annulée_solde_impayé'
            ) NOT NULL DEFAULT 'pending'"
        );

        DB::statement("ALTER TABLE reservations_materielles
            MODIFY COLUMN status ENUM(
                'pending',
                'confirmed',
                'paid',
                'retrieved',
                'returned',
                'rejected',
                'canceled',
                'cancelled_by_camper',
                'cancelled_by_fournisseur',
                'disputed',
                'modified',
                'paiement_soumis',
                'paiement_invalide',
                'confirmée_solde_en_attente',
                'solde_soumis',
                'entièrement_payée',
                'annulée_solde_impayé'
            ) NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        // Remove the new value — requires rebuilding the enum without it
        DB::statement("ALTER TABLE reservations_events
            MODIFY COLUMN status ENUM(
                'en_attente_paiement',
                'confirmée',
                'en_attente_validation',
                'refusée',
                'annulée_par_utilisateur',
                'annulée_par_organisateur',
                'remboursement_en_attente',
                'remboursée_partielle',
                'remboursée_totale',
                'paiement_soumis',
                'paiement_invalide',
                'confirmée_solde_en_attente',
                'solde_soumis',
                'entièrement_payée',
                'modified',
                'pending'
            ) NOT NULL DEFAULT 'en_attente_paiement'"
        );

        DB::statement("ALTER TABLE reservations_centres
            MODIFY COLUMN status ENUM(
                'pending',
                'approved',
                'rejected',
                'canceled',
                'modified',
                'paiement_soumis',
                'paiement_invalide',
                'confirmée_solde_en_attente',
                'solde_soumis',
                'entièrement_payée'
            ) NOT NULL DEFAULT 'pending'"
        );

        DB::statement("ALTER TABLE reservations_materielles
            MODIFY COLUMN status ENUM(
                'pending',
                'confirmed',
                'paid',
                'retrieved',
                'returned',
                'rejected',
                'canceled',
                'cancelled_by_camper',
                'cancelled_by_fournisseur',
                'disputed',
                'modified',
                'paiement_soumis',
                'paiement_invalide',
                'confirmée_solde_en_attente',
                'solde_soumis',
                'entièrement_payée'
            ) NOT NULL DEFAULT 'pending'"
        );
    }
};
