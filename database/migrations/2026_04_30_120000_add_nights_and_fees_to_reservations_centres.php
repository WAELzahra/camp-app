<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->unsignedSmallInteger('nights')->default(1)->after('nbr_place');
            $table->decimal('platform_fee_rate', 5, 2)->nullable()->after('discount_amount');
            $table->decimal('platform_fee_amount', 10, 2)->nullable()->after('platform_fee_rate');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropColumn(['nights', 'platform_fee_rate', 'platform_fee_amount']);
        });
    }
};
