<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_reservation_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_reservation_id')->constrained('programme_reservations')->onDelete('cascade');
            $table->foreignId('programme_item_id')->constrained('programme_items')->onDelete('restrict');
            // Resolved at share-creation time from the referenced item's real owner
            // (events.group_id / camping_centres.user_id / materielles.fournisseur_id).
            $table->foreignId('owner_user_id')->constrained('users')->onDelete('restrict');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->boolean('credited')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index('programme_reservation_id');
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_reservation_shares');
    }
};
