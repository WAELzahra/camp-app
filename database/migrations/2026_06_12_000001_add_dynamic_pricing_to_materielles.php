<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materielles', function (Blueprint $table) {
            // Rental unit: each item is rented per night OR per hour.
            $table->string('rental_unit', 10)->default('night')->after('is_sellable');
            // Hourly rate — used when rental_unit = 'hour'.
            $table->float('tarif_heure')->nullable()->after('tarif_nuit');
        });

        // Recurring yearly seasonal rates (day+month ranges) with separate
        // weekday / weekend (Sat+Sun) pricing. Overrides the base rate for
        // nights falling inside the range.
        Schema::create('materielle_seasonal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materielle_id')->constrained('materielles')->onDelete('cascade');
            $table->string('name');                       // e.g. "Juillet & Août"
            $table->unsignedTinyInteger('start_month');   // 1-12
            $table->unsignedTinyInteger('start_day');     // 1-31
            $table->unsignedTinyInteger('end_month');     // 1-12
            $table->unsignedTinyInteger('end_day');       // 1-31
            $table->float('price_weekday');               // per unit (night/hour)
            $table->float('price_weekend')->nullable();   // falls back to weekday when null
            $table->timestamps();

            $table->index('materielle_id');
        });

        Schema::table('reservations_materielles', function (Blueprint $table) {
            // Number of hours for hourly rentals (rental_unit = 'hour').
            $table->unsignedInteger('hours')->nullable()->after('date_fin');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
        Schema::dropIfExists('materielle_seasonal_rates');
        Schema::table('materielles', function (Blueprint $table) {
            $table->dropColumn(['rental_unit', 'tarif_heure']);
        });
    }
};
