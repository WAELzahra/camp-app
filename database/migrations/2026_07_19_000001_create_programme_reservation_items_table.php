<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which programme_items a camper actually kept when booking (they
 * can deselect items they don't want, e.g. skip the equipment rental and
 * only keep the event + stay) — the ledger only splits payment across the
 * items present here, not every item on the programme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_reservation_id')->constrained('programme_reservations')->onDelete('cascade');
            $table->foreignId('programme_item_id')->constrained('programme_items')->onDelete('restrict');

            $table->unique(['programme_reservation_id', 'programme_item_id'], 'pri_reservation_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_reservation_items');
    }
};
