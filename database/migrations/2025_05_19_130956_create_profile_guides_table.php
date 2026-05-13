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
            $table->string('certificat_path')->nullable(); 
            $table->string('certificat_type')->nullable(); 
            $table->date('certificat_expiration')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profile_guides');
    }
};