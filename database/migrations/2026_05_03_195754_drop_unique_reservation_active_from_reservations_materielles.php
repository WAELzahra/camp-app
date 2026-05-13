<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations_materielles', function (Blueprint $table) {
            // MySQL uses the composite unique index as the backing index for the
            // materielle_id FK (composite indexes cover the FK if the first column matches).
            // Add a plain index first so the FK has a backing index after the unique is dropped.
            $table->index('materielle_id', 'idx_rm_materielle_id');
            $table->dropUnique('unique_reservation_active');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->unique(
                ['materielle_id', 'user_id', 'fournisseur_id', 'date_debut'],
                'unique_reservation_active'
            );
            $table->dropIndex('idx_rm_materielle_id');
        });
    }
};
