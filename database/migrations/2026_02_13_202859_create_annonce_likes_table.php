<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('annonce_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('annonce_id')->constrained('annonces')->onDelete('cascade');
            $table->timestamps();
            
            // Prevent duplicate likes
            $table->unique(['user_id', 'annonce_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonce_likes');
    }
};