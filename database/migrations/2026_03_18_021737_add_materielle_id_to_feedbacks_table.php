<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->foreignId('materielle_id')
                  ->nullable()
                  ->after('zone_id')
                  ->constrained('materielles')
                  ->onDelete('cascade');

            $table->index('materielle_id');
        });
    }

    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropForeign(['materielle_id']);
            $table->dropIndex(['materielle_id']);
            $table->dropColumn('materielle_id');
        });
    }
};