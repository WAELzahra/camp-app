<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'pending_payment' to the status ENUM on all 3 reservation tables.
 *
 * 'pending_payment' = a manual (Flouci / bank-transfer) reservation that has been
 * booked but not yet paid+confirmed. It is hidden from the host's queue; the host
 * only sees the reservation (as 'pending' / 'en_attente_validation') once the admin
 * has confirmed the payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reservations_events
            MODIFY COLUMN status ENUM(
                'en_attente_paiement','confirmée','en_attente_validation','refusée',
                'annulée_par_utilisateur','annulée_par_organisateur',
                'remboursement_en_attente','remboursée_partielle','remboursée_totale',
                'paiement_soumis','paiement_invalide','confirmée_solde_en_attente',
                'solde_soumis','entièrement_payée','annulée_solde_impayé','modified','pending',
                'pending_payment'
            ) NOT NULL DEFAULT 'en_attente_paiement'");

        DB::statement("ALTER TABLE reservations_centres
            MODIFY COLUMN status ENUM(
                'pending','approved','rejected','canceled','modified',
                'paiement_soumis','paiement_invalide','confirmée_solde_en_attente',
                'solde_soumis','entièrement_payée','annulée_solde_impayé',
                'pending_payment'
            ) NOT NULL DEFAULT 'pending'");

        DB::statement("ALTER TABLE reservations_materielles
            MODIFY COLUMN status ENUM(
                'pending','confirmed','paid','retrieved','returned','rejected','canceled',
                'cancelled_by_camper','cancelled_by_fournisseur','disputed','modified',
                'paiement_soumis','paiement_invalide','confirmée_solde_en_attente',
                'solde_soumis','entièrement_payée','annulée_solde_impayé',
                'pending_payment'
            ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert any straggler rows so the value can be dropped from the enum.
        DB::statement("UPDATE reservations_events       SET status = 'pending'  WHERE status = 'pending_payment'");
        DB::statement("UPDATE reservations_centres      SET status = 'pending'  WHERE status = 'pending_payment'");
        DB::statement("UPDATE reservations_materielles  SET status = 'pending'  WHERE status = 'pending_payment'");

        DB::statement("ALTER TABLE reservations_events
            MODIFY COLUMN status ENUM(
                'en_attente_paiement','confirmée','en_attente_validation','refusée',
                'annulée_par_utilisateur','annulée_par_organisateur',
                'remboursement_en_attente','remboursée_partielle','remboursée_totale',
                'paiement_soumis','paiement_invalide','confirmée_solde_en_attente',
                'solde_soumis','entièrement_payée','annulée_solde_impayé','modified','pending'
            ) NOT NULL DEFAULT 'en_attente_paiement'");

        DB::statement("ALTER TABLE reservations_centres
            MODIFY COLUMN status ENUM(
                'pending','approved','rejected','canceled','modified',
                'paiement_soumis','paiement_invalide','confirmée_solde_en_attente',
                'solde_soumis','entièrement_payée','annulée_solde_impayé'
            ) NOT NULL DEFAULT 'pending'");

        DB::statement("ALTER TABLE reservations_materielles
            MODIFY COLUMN status ENUM(
                'pending','confirmed','paid','retrieved','returned','rejected','canceled',
                'cancelled_by_camper','cancelled_by_fournisseur','disputed','modified',
                'paiement_soumis','paiement_invalide','confirmée_solde_en_attente',
                'solde_soumis','entièrement_payée','annulée_solde_impayé'
            ) NOT NULL DEFAULT 'pending'");
    }
};
