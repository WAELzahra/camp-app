<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profile_groupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->onDelete('cascade');
            $table->string('nom_groupe')->nullable();
            $table->unsignedBigInteger('id_album_photo')->nullable();
            $table->unsignedBigInteger('id_annonce')->nullable();
            
            // Documents du groupe
            $table->string('patente_path')->nullable(); // Chemin vers la patente
            $table->string('patente_filename')->nullable(); // Nom original
            
            // CIN du responsable (déjà présent, mais on ajoute le chemin)
            $table->string('cin_responsable')->nullable(); // Numéro de CIN
            $table->string('cin_responsable_path')->nullable(); // Image de la CIN
            $table->string('cin_responsable_filename')->nullable(); // Nom original
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profile_groupes');
    }
};