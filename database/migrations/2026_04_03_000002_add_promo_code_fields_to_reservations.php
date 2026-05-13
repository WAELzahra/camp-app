<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete()->after('total_price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promo_code_id');
        });

        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete()->after('montant_total');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promo_code_id');
        });

        Schema::table('reservations_events', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete()->after('nbr_place');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'discount_amount']);
        });

        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'discount_amount']);
        });

        Schema::table('reservations_events', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'discount_amount']);
        });
    }
};
