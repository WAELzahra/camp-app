<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            // Make existing event FK nullable so centre refunds don't need it
            $table->foreignId('reservation_event_id')->nullable()->change();
            // New FK for centre reservations
            $table->foreignId('reservation_centre_id')
                  ->nullable()
                  ->after('reservation_event_id')
                  ->constrained('reservations_centres')
                  ->onDelete('cascade');
            // Track payment channel for the refund
            $table->enum('payment_channel', ['konnect', 'wallet'])->default('konnect')->after('montant_rembourse');
            // Optional note / reason
            $table->string('reason')->nullable()->after('payment_channel');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_centre_id');
            $table->dropColumn(['payment_channel', 'reason']);
            $table->foreignId('reservation_event_id')->nullable(false)->change();
        });
    }
};
