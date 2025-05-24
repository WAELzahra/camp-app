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
        Schema::create('camping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('type_activitee');
            $table->boolean('is_public')->default(true);
            $table->string('description')->nullable();
            $table->string('adresse');
            $table->enum('danger_level', ['low', 'moderate', 'high', 'extreme'])->default('low');
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camping_zones');
    }
};
