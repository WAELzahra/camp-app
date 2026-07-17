<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A departure now represents an availability window (start_date..end_date,
 * shared capacity), not a single fixed day — the camper picks any date
 * inside that window, and the booking stays 'pending' until the admin
 * confirms real availability for that specific date (same review step
 * that already exists for every Programme reservation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_reservations', function (Blueprint $table) {
            $table->date('requested_date')->nullable()->after('programme_departure_id');
        });
    }

    public function down(): void
    {
        Schema::table('programme_reservations', function (Blueprint $table) {
            $table->dropColumn('requested_date');
        });
    }
};
