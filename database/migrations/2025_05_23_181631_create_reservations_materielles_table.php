<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations_materielles', function (Blueprint $table) {
            $table->id();

            // --- Parties ---
            $table->foreignId('materielle_id')->constrained('materielles')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('fournisseur_id')->constrained('users')->onDelete('cascade');

            // --- Reservation type ---
            // 'location' = rental, 'achat' = sale
            $table->enum('type_reservation', ['location', 'achat']);

            // --- Dates (only required for rentals) ---
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();

            // --- Quantity & Amount ---
            $table->unsignedInteger('quantite'); // must be >= 1
            $table->float('montant_total'); // total charged to camper

            // --- Delivery ---
            $table->enum('mode_livraison', ['pickup', 'delivery']);
            // Delivery address (required when mode_livraison = 'delivery')
            $table->string('adresse_livraison')->nullable();
            // Agreed delivery fee (negotiated case by case, can be 0)
            $table->float('frais_livraison')->nullable()->default(0);

            // --- CIN (snapshot at time of reservation, required for rentals) ---
            // We store a copy here so it's legally tied to this specific reservation
            // even if the user later changes their profile CIN
            $table->string('cin_camper')->nullable(); // null only for 'achat' type

            // --- Status flow ---
            // pending   → supplier reviews
            // confirmed → supplier accepted, PIN generated, awaiting payment
            // paid      → payment received, camper has PIN, awaiting retrieval
            // retrieved → supplier entered PIN, handoff confirmed, money can be released
            // returned  → (rentals only) materiel returned to supplier
            // rejected  → supplier rejected the request
            // canceled  → camper or admin canceled
            $table->enum('status', [
                'pending',
                'confirmed',
                'paid',
                'retrieved',
                'returned',
                'rejected',
                'cancelled_by_camper',
                'cancelled_by_fournisseur',
                'disputed',    
            ])->default('pending');

            // --- PIN (generated on confirmation, hashed like a password) ---
            // Only revealed to camper once; supplier submits raw PIN to confirm handoff
            $table->string('pin_code')->nullable(); // stored as bcrypt hash
            $table->timestamp('pin_used_at')->nullable(); // set when supplier enters correct PIN

            // --- Payment ---
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');

            // --- Timestamps ---
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamp('returned_at')->nullable(); // rentals only

            $table->timestamps();

            // Prevent duplicate active reservations for the same item/user/start date
            $table->unique(
                ['materielle_id', 'user_id', 'fournisseur_id', 'date_debut'],
                'unique_reservation_active'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations_materielles');
    }
};