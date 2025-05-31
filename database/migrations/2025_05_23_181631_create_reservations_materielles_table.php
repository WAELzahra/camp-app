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
            $table->id(); // Auto-increment primary key

            $table->foreignId('materielle_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('fournisseur_id')->constrained('users')->onDelete('cascade');

            $table->date('date_debut');
            $table->date('date_fin');

            $table->integer('quantite')->check('quantite > 1');
            $table->float('montant_payer');

            $table->enum('status', ['pending', 'confirmed', 'rejected', 'canceled'])->default('pending');

            $table->foreignId('payments_id')->nullable()->constrained()->onDelete('cascade');

            // Unique constraint on materielle_id, user_id, fournisseur_id, and date_debut
            $table->unique(['materielle_id', 'user_id', 'fournisseur_id', 'date_debut'], 'unique_materielle_user_fournisseur_date');

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
