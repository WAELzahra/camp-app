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
        Schema::create('followers_groupes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Le campeur
    $table->foreignId('groupe_id')->constrained('profile_groupes')->onDelete('cascade'); // Le groupe suivi
    $table->timestamps();

    $table->unique(['user_id', 'groupe_id']); 
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('followers_groupes');
    }
};
