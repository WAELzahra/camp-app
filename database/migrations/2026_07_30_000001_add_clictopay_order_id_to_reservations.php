<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ClicToPay register.do returns an orderId (UUID) that must be stored to later
 * call getOrderStatusExtended.do (manual §4/§5 — "à stocker obligatoirement").
 * payment_method stays 'manual' (bank transfer and ClicToPay are both
 * sub-channels of it); no enum change needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['reservations_events', 'reservations_centres', 'reservations_materielles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('clictopay_order_id', 36)->nullable()->after('payment_reference')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['reservations_events', 'reservations_centres', 'reservations_materielles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('clictopay_order_id');
            });
        }
    }
};
