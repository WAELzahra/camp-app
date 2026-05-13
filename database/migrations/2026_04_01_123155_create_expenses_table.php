<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Propriétaire de la dépense
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Titre / description courte
            $table->string('titre');

            // Montant en TND
            $table->decimal('montant', 12, 2);

            // Catégorie
            $table->enum('categorie', [
                'transport',
                'hébergement',
                'nourriture',
                'équipement',
                'marketing',
                'maintenance',
                'salaires',
                'location',
                'formation',
                'communication',
                'assurance',
                'autre',
            ])->default('autre');

            // Statut
            $table->enum('status', [
                'brouillon',    // non finalisé
                'confirmé',     // dépense confirmée
                'remboursé',    // remboursé par la plateforme
            ])->default('confirmé');

            // Date de la dépense (pas forcément created_at)
            $table->date('date_depense');

            // Lien optionnel à un événement
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');

            // Référence externe (facture, ticket, etc.)
            $table->string('reference')->nullable();

            // Notes libres
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
