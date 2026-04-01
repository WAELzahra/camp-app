<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');

            // Solde disponible (net après commission, prêt à retirer)
            $table->decimal('solde_disponible', 12, 2)->default(0);

            // Solde en attente (paiements confirmés mais retrait pas encore demandé)
            $table->decimal('solde_en_attente', 12, 2)->default(0);

            // Totaux historiques
            $table->decimal('total_encaisse', 12, 2)->default(0);   // cumul des net_revenue reçus
            $table->decimal('total_retire', 12, 2)->default(0);     // cumul des retraits approuvés
            $table->decimal('total_rembourse', 12, 2)->default(0);  // cumul des remboursements déduits

            $table->timestamp('dernier_mouvement_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
