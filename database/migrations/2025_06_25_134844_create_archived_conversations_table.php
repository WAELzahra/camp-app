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
        Schema::create('archived_conversations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id'); // celui qui archive
    $table->unsignedBigInteger('receiver_id'); // la cible
    $table->unsignedBigInteger('event_id');
    $table->timestamps();

    $table->unique(['user_id', 'receiver_id', 'event_id']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_conversations');
    }
};
