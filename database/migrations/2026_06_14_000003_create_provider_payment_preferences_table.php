<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.5 — per-provider deposit preferences.
 *
 * Centres, suppliers, and event organizers can opt in to accepting deposit
 * payments and set a custom deposit percentage within the admin-defined min/max.
 * One row per provider (user_id). If no row exists, deposits are not accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_payment_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('accepts_deposits')->default(false);
            // Percentage the provider charges as deposit (must be within admin min/max)
            $table->unsignedTinyInteger('deposit_percentage')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payment_preferences');
    }
};
