<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legal/compliance: refunds default to platform credit (unchanged —
 * AdminPaymentController::approveRefund already only credits Balance). This adds
 * a distinct, explicit path for the narrow legally-mandated cash-refund exception
 * (see AdminPaymentController::approveCashRefund) — tracked separately from
 * payment_channel, which describes the ORIGINAL payment's channel, not how a
 * refund is being issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->enum('refund_method', ['credit', 'cash_direct'])->default('credit')->after('status');
            $table->string('cash_refund_reason', 500)->nullable()->after('refund_method');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropColumn(['refund_method', 'cash_refund_reason']);
        });
    }
};
