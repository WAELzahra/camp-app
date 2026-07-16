<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_departure_id')->constrained('programme_departures')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->unsignedInteger('participants_count')->default(1);
            $table->decimal('total_price', 10, 2);
            $table->enum('payment_method', ['wallet', 'manual']);
            $table->enum('payment_option', ['deposit', 'full'])->default('full');
            $table->decimal('amount_now', 10, 2);
            $table->decimal('amount_later', 10, 2)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->onDelete('set null');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['programme_departure_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_reservations');
    }
};
