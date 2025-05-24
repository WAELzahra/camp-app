<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations_materielles', function (Blueprint $table) {
            $table->foreignId('materielle_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('fournisseur_id')->constrained('users')->onDelete('cascade');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('quantite')->check('quantite > 1');
            $table->float('montant_payer');

            $table->primary(['user_id', 'materielle_id', 'date_debut']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations_materielles');
    }
};
