<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reservation_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_reservation_id')->constrained('reservations_events')->onDelete('cascade');
            $table->foreignId('materielle_id')->constrained('materielles')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('quantite')->default(1);
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('montant_total', 10, 2);
            $table->decimal('platform_fee_amount', 10, 2)->default(0);
            $table->decimal('platform_fee_rate', 5, 2)->default(0);
            $table->decimal('supplier_net_revenue', 10, 2)->default(0);
            $table->boolean('supplier_credited')->default(false);
            $table->timestamps();

            $table->index('event_reservation_id');
            $table->index('supplier_id');
            $table->index('materielle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reservation_materials');
    }
};
