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
        Schema::create('favoris', function (Blueprint $table) {
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('target_id')->constrained('users')->onDelete('cascade'); // ou une autre table selon le type
        $table->enum('type', ['guide', 'centre', 'event', 'zone']); // <-- ajout de 'zone'
        $table->timestamps();
        $table->primary(['user_id', 'target_id']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favoris');
    }
};
