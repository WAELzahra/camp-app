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
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Polymorphic structure
            $table->unsignedBigInteger('target_id');
            $table->enum('type', ['guide', 'centre', 'event', 'zone', 'annonce']);

            $table->timestamps();

            // Prevent duplicates: same user can’t favorite same target twice
            $table->unique(['user_id', 'target_id', 'type']);
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
