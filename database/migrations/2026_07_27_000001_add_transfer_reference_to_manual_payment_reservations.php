<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The camper's OWN bank transfer reference (as opposed to `payment_reference`,
 * the system-generated code we ask them to put in the transfer note) — mirrors
 * the `wallet_recharge_requests.transfer_reference` column/pattern, so the
 * reservation payment screen can require the same proof-of-transfer field.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['reservations_events', 'reservations_centres', 'reservations_materielles', 'programme_reservations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('transfer_reference', 120)->nullable()->after('payment_reference');
            });
        }
    }

    public function down(): void
    {
        foreach (['reservations_events', 'reservations_centres', 'reservations_materielles', 'programme_reservations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('transfer_reference');
            });
        }
    }
};
