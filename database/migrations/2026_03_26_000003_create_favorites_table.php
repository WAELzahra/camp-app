<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the broken favorites table (wrong PK, wrong FK, missing types)
        Schema::dropIfExists('favoris');

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('favoritable_id');
            $table->enum('favoritable_type', ['profile', 'centre', 'zone', 'equipment']);
            $table->timestamps();

            // Prevent duplicate favorites
            $table->unique(['user_id', 'favoritable_id', 'favoritable_type']);
            // Fast queries by user + type
            $table->index(['user_id', 'favoritable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
