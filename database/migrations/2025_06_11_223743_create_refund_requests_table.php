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
        Schema::create('refund_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reservation_event_id')->constrained('reservations_events')->onDelete('cascade');
  $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
    $table->decimal('montant_rembourse', 8, 2);
    $table->enum('status', ['en_attente', 'accepté', 'refusé'])->default('en_attente');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
