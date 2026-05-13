<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('camping_centre_id')
                  ->nullable()
                  ->after('camping_zone_id')
                  ->constrained('camping_centres')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['camping_centre_id']);
            $table->dropColumn('camping_centre_id');
        });
    }
};
