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
        Schema::create('materielles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fournisseur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('users')->onDelete('cascade');
            $table->string('nom');
            $table->string('description');
            $table->float('tarif_nuit')->check('tarif_nuit > 0');
            $table->integer('quantite_dispo');
            $table->integer('quantite_total')->check('quantite_total > 0');

            $table->string('type');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materielles');
    }
};
