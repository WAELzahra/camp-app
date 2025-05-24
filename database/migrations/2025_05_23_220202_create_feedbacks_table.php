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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('target_id')->constrained('users')->onDelete('cascade')->nullable();
            $table->foreignId('event_id')->constrained()->onDelete('cascade')->nullable();
            $table->foreignId('zone_id')->constrained('camping_zones')->onDelete('cascade')->nullable();
            $table->string('contenu')->nullable();
            $table->string('response')->nullable();
            $table->float('note')->check('note >= 0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
