<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reservation_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_reservation_id')->constrained('reservations_events')->onDelete('cascade');
            $table->foreignId('event_service_id')->constrained('event_services')->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->decimal('price_snapshot', 10, 2);
            $table->string('pricing_unit_snapshot');
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reservation_services');
    }
};
