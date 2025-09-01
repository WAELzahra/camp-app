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
        Schema::create('circuits', function (Blueprint $table) {
            $table->id();
            $table->string("adresse_debut_circuit");
            $table->string("adresse_fin_circuit");
            $table->string('description')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->float('distance_km')->check('distance_km > 0');
            $table->float('estimation_temps')->check('estimation_temps > 0');
            $table->enum('difficulte', ['up', 'down'])->default('down');
            $table->enum('danger_level', ['low', 'moderate', 'high', 'extreme'])->default('low');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('circuits');
    }
};
