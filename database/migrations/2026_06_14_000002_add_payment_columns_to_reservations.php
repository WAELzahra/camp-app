<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — manual payment workflow columns on all 3 reservation tables.
 *
 * Adds:
 *   payment_reference       — human-readable TC-YYYY-000042 / WALLET-... reference
 *   payment_option          — 'full' | 'deposit' (which scenario the camper chose)
 *   amount_now              — amount due immediately
 *   amount_later            — balance still owed (0 for 'full' payments)
 *   balance_due_at          — deadline for the balance (only for deposits)
 *   payment_submitted_at    — camper declared "I paid" timestamp
 *   payment_confirmed_at    — admin confirmed the bank transfer
 *   confirmed_by            — FK to users (admin who confirmed)
 *
 * Also extends the status ENUMs with the new manual-payment lifecycle values:
 *   paiement_soumis              — camper submitted proof of payment
 *   paiement_invalide            — admin rejected the proof
 *   confirmée_solde_en_attente   — confirmed; balance still due
 *   solde_soumis                 — camper submitted balance proof
 *   entièrement_payée            — admin confirmed full payment
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── reservations_events ───────────────────────────────────────────────
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
                'entièrement_payée'
            ) NOT NULL DEFAULT 'en_attente_paiement'"
        );

        Schema::table('reservations_events', function (Blueprint $table) {
            $table->string('payment_reference', 40)->nullable()->after('payment_id');
            $table->enum('payment_option', ['full', 'deposit'])->nullable()->after('payment_reference');
            $table->decimal('amount_now', 10, 2)->nullable()->after('payment_option');
            $table->decimal('amount_later', 10, 2)->nullable()->after('amount_now');
            $table->date('balance_due_at')->nullable()->after('amount_later');
            $table->timestamp('payment_submitted_at')->nullable()->after('balance_due_at');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_submitted_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete()->after('payment_confirmed_at');
        });

        // ── reservations_centres ──────────────────────────────────────────────
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

        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->string('payment_reference', 40)->nullable()->after('payments_id');
            $table->enum('payment_option', ['full', 'deposit'])->nullable()->after('payment_reference');
            $table->decimal('amount_now', 10, 2)->nullable()->after('payment_option');
            $table->decimal('amount_later', 10, 2)->nullable()->after('amount_now');
            $table->date('balance_due_at')->nullable()->after('amount_later');
            $table->timestamp('payment_submitted_at')->nullable()->after('balance_due_at');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_submitted_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete()->after('payment_confirmed_at');
        });

        // ── reservations_materielles ──────────────────────────────────────────
        DB::statement("ALTER TABLE reservations_materielles
            MODIFY COLUMN status ENUM(
                'pending',
                'confirmed',
                'paid',
                'retrieved',
                'returned',
                'rejected',
                'cancelled_by_camper',
                'cancelled_by_fournisseur',
                'disputed',
                'paiement_soumis',
                'paiement_invalide',
                'confirmée_solde_en_attente',
                'solde_soumis',
                'entièrement_payée'
            ) NOT NULL DEFAULT 'pending'"
        );

        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->string('payment_reference', 40)->nullable()->after('payment_id');
            $table->enum('payment_option', ['full', 'deposit'])->nullable()->after('payment_reference');
            $table->decimal('amount_now', 10, 2)->nullable()->after('payment_option');
            $table->decimal('amount_later', 10, 2)->nullable()->after('amount_now');
            $table->date('balance_due_at')->nullable()->after('amount_later');
            $table->timestamp('payment_submitted_at')->nullable()->after('balance_due_at');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_submitted_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete()->after('payment_confirmed_at');
        });
    }

    public function down(): void
    {
        // Revert columns
        foreach (['reservations_events', 'reservations_centres', 'reservations_materielles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['confirmed_by']);
                $t->dropColumn([
                    'payment_reference', 'payment_option', 'amount_now', 'amount_later',
                    'balance_due_at', 'payment_submitted_at', 'payment_confirmed_at', 'confirmed_by',
                ]);
            });
        }

        // Revert status ENUMs
        DB::statement("ALTER TABLE reservations_events
            MODIFY COLUMN status ENUM(
                'en_attente_paiement','confirmée','en_attente_validation','refusée',
                'annulée_par_utilisateur','annulée_par_organisateur',
                'remboursement_en_attente','remboursée_partielle','remboursée_totale'
            ) NOT NULL DEFAULT 'en_attente_paiement'"
        );
        DB::statement("ALTER TABLE reservations_centres
            MODIFY COLUMN status ENUM('pending','approved','rejected','canceled','modified')
            NOT NULL DEFAULT 'pending'"
        );
        DB::statement("ALTER TABLE reservations_materielles
            MODIFY COLUMN status ENUM(
                'pending','confirmed','paid','retrieved','returned','rejected',
                'cancelled_by_camper','cancelled_by_fournisseur','disputed'
            ) NOT NULL DEFAULT 'pending'"
        );
    }
};
