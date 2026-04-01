<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Montant demandé
            $table->decimal('montant', 12, 2);

            // Statut de la demande
            $table->enum('status', [
                'en_attente',   // nouvelle demande
                'en_cours',     // admin l'a prise en charge
                'approuvé',     // approuvée + virement initié
                'complété',     // argent bien transféré
                'rejeté',       // refusée par admin
            ])->default('en_attente');

            // Méthode de retrait
            $table->enum('methode', [
                'virement_bancaire',
                'chèque',
                'espèces',
                'flouci',
            ])->default('virement_bancaire');

            // Détails bancaires / coordonnées (JSON)
            $table->json('details_paiement')->nullable();

            // Notes admin
            $table->text('admin_note')->nullable();

            // Traité par
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
