<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programme bookings were wallet-only; this adds the same manual
 * (bank-transfer) payment columns/status vocabulary already used by
 * Events/Centres/Materielles, so a camper without enough wallet balance
 * has another way to book.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_reservations', function (Blueprint $table) {
            $table->string('payment_reference', 40)->nullable()->after('payment_option');
            $table->date('balance_due_at')->nullable()->after('amount_later');
            $table->timestamp('payment_submitted_at')->nullable()->after('balance_due_at');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_submitted_at');
            $table->foreignId('confirmed_by')->nullable()->after('payment_confirmed_at')->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE programme_reservations
            MODIFY COLUMN status ENUM(
                'pending_payment',
                'pending',
                'paiement_soumis',
                'paiement_invalide',
                'solde_soumis',
                'confirmed',
                'cancelled',
                'completed'
            ) NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        Schema::table('programme_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['payment_reference', 'balance_due_at', 'payment_submitted_at', 'payment_confirmed_at']);
        });

        DB::statement("ALTER TABLE programme_reservations
            MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending'"
        );
    }
};
