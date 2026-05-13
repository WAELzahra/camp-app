<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materielles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fournisseur_id')->constrained('users')->onDelete('cascade');

            // Fixed: was incorrectly constrained to 'users' — should reference materielles_categories
            $table->foreignId('category_id')->constrained('materielles_categories')->onDelete('cascade');

            $table->string('nom');
            $table->string('description');

            // --- Listing type ---
            // Whether this item can be rented, sold, or both
            $table->boolean('is_rentable')->default(true);
            $table->boolean('is_sellable')->default(false);

            // --- Pricing ---
            // Rental price per night (null if not rentable)
            $table->float('tarif_nuit')->nullable();
            // Sale price (null if not sellable)
            $table->float('prix_vente')->nullable();

            // --- Stock ---
            $table->unsignedInteger('quantite_total');
            $table->unsignedInteger('quantite_dispo'); // decremented on confirmed reservation

            // --- Delivery ---
            // Whether the supplier offers delivery as an option
            $table->boolean('livraison_disponible')->default(false);
            // Delivery fee set by supplier (null = negotiated / free)
            $table->float('frais_livraison')->nullable();

            // --- Visibility ---
            $table->enum('status', ['up', 'down'])->default('up');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materielles');
    }
};