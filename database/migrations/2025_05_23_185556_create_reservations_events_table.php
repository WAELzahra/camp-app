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
        Schema::create('reservations_events', function (Blueprint $table) {
            $table->id();

            // Utilisateur ayant réservé (campeur)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');

            // Événement concerné
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');

            // Groupe organisateur de l’événement
            $table->foreignId('group_id')->constrained('users')->onDelete('cascade');

            // Infos personnelles si pas d’utilisateur
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Nombre de places réservées
            $table->integer('nbr_place');

            // Paiement lié (peut être null tant que non payé)
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');

            // Statut enrichi (nouveaux cas)
            $table->enum('status', [
                'en_attente_paiement',
                'confirmée',
                'en_attente_validation',
                'refusée',
                'annulée_par_utilisateur',
                'annulée_par_organisateur',
                'remboursement_en_attente',
                'remboursée_partielle',
                'remboursée_totale'
            ])->default('en_attente_paiement');

            // Qui a créé cette réservation (admin ou organisateur)
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations_events');
    }
};
