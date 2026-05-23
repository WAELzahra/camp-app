<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make optional event columns nullable so custom/partial events can be saved.
     *
     * - start_date / end_date  : optional for 'custom' event type
     * - capacity               : optional (unlimited events)
     * - remaining_spots        : follows capacity — also optional
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->integer('capacity')->nullable()->default(null)->change();
            $table->integer('remaining_spots')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Restore originals — fill nulls with 0 first to avoid constraint violation
            \DB::statement("UPDATE events SET start_date = NOW() WHERE start_date IS NULL");
            \DB::statement("UPDATE events SET end_date   = NOW() WHERE end_date IS NULL");
            \DB::statement("UPDATE events SET capacity        = 0 WHERE capacity IS NULL");
            \DB::statement("UPDATE events SET remaining_spots = 0 WHERE remaining_spots IS NULL");

            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
            $table->integer('capacity')->nullable(false)->default(0)->change();
            $table->integer('remaining_spots')->nullable(false)->default(0)->change();
        });
    }
};
