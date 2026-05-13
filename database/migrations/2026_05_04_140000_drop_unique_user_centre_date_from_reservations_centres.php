<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            // Add a plain index on user_id first so the FK constraint still has an index to use
            $table->index('user_id', 'reservations_centres_user_id_idx');
        });
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropUnique('unique_user_centre_date');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->unique(['user_id', 'centre_id', 'date_debut'], 'unique_user_centre_date');
            $table->dropIndex('reservations_centres_user_id_idx');
        });
    }
};
