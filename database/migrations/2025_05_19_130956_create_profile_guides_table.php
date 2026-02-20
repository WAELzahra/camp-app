<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profile_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->onDelete('cascade');
            $table->integer('experience')->nullable();
            $table->decimal('tarif', 8, 2)->nullable();
            $table->string('zone_travail')->nullable();
            
            // Certificats du guide
            $table->string('certificat_path')->nullable(); // Chemin vers le certificat
            $table->string('certificat_filename')->nullable(); // Nom original
            $table->string('certificat_type')->nullable(); // Type de certificat (guide, secourisme, etc.)
            $table->date('certificat_expiration')->nullable(); // Date d'expiration si applicable
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profile_guides');
    }
};