<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('wallet')->after('discount_amount');
            $table->decimal('platform_fee_amount', 10, 2)->nullable()->after('payment_method');
            $table->decimal('platform_fee_rate', 5, 2)->nullable()->after('platform_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'platform_fee_amount', 'platform_fee_rate']);
        });
    }
};
