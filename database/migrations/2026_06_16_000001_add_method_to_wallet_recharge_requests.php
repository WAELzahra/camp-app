<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds a second wallet-recharge method: direct bank transfer.
 *
 *  - method             — 'flouci' (pay via the Flouci link) | 'bank_transfer'
 *                         (camper transfers directly and posts a claim)
 *  - transfer_reference — the camper's OWN bank transfer reference (bank_transfer only)
 *  - credited_amount    — the amount the admin actually credited on confirmation,
 *                         which may differ from the camper's claimed `amount`
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_recharge_requests', function (Blueprint $table) {
            $table->enum('method', ['flouci', 'bank_transfer'])->default('flouci')->after('amount');
            $table->string('transfer_reference', 120)->nullable()->after('payment_reference');
            $table->decimal('credited_amount', 10, 2)->nullable()->after('transfer_reference');
        });

        // Existing rows predate the feature — they were all Flouci.
        DB::table('wallet_recharge_requests')->update(['method' => 'flouci']);
    }

    public function down(): void
    {
        Schema::table('wallet_recharge_requests', function (Blueprint $table) {
            $table->dropColumn(['method', 'transfer_reference', 'credited_amount']);
        });
    }
};
