<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Références
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');

            // Montant payé par l'utilisateur
            $table->decimal('montant', 10, 2);

            // Description libre
            $table->string('description')->nullable();

            // Statut du paiement
            $table->enum('status', [
                'pending',        // en attente
                'paid',           // payé avec succès
                'failed',         // échec
                'refunded_partial', // remboursement partiel
                'refunded_total'   // remboursement total
            ])->default('pending');

            // Données liées à Konnect
            $table->string('konnect_session_id')->nullable();
            $table->string('konnect_payment_id')->nullable(); // utile pour vérif & refund
            $table->string('konnect_payment_url')->nullable();

            // Commission de la plateforme et revenu net
            $table->decimal('commission', 10, 2)->default(0);
            $table->decimal('net_revenue', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
