<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('type', ['campeur', 'guide', 'centre', 'fournisseur', 'groupe']);
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            
            // Documents communs
            $table->string('cin_path')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profiles');
    }
};