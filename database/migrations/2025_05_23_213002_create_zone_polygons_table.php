<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('zone_polygons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('zone_id')->constrained('camping_zones')->onDelete('cascade');
    $table->json('coordinates'); // stocke les coordonnées du polygone
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_polygons');
    }
};
