<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('category', [
                'platform_fee',           // Service fee charged to camper at booking
                'commission',             // Commission taken from supplier/centre/group
                'platform_cancellation_fee', // Fee charged when actor cancels
                'refund_funding',         // Platform funds refund gap (negative — expense)
            ]);
            $table->decimal('amount', 10, 2);  // positive = income, negative = expense
            $table->string('reference_type')->nullable(); // centre_reservation | event_reservation | materiel_reservation
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('related_user_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_transactions');
    }
};
