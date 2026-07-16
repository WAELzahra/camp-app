<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces programme_steps + programme_step_partners with a single flat
 * table. Each row is one already-published listing bundled into the
 * Programme (an Event created by an organisateur, a CampingCentre stay, or
 * a Materielle rental) — item_type + item_id is a polymorphic reference
 * into that listing's own table, so ownership/payout always resolves to
 * the real actor (events.group_id / camping_centres.user_id /
 * materielles.fournisseur_id), never a re-entered stand-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('day_offset')->default(0);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('item_type', ['event', 'centre', 'materiel']);
            $table->unsignedBigInteger('item_id'); // events.id | camping_centres.id | materielles.id
            // Bundle price for this item within the programme (curated, not
            // re-derived live from the listing's own variable pricing engine).
            $table->decimal('price', 10, 2)->default(0);
            // null = falls back to the platform's default commission rate for this item's actor type
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->index('programme_id');
            $table->index(['item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_items');
    }
};
