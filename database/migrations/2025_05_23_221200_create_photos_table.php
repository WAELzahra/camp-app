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
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->string('path_to_img');
            
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('annonce_id')->nullable();
            $table->unsignedBigInteger('materielle_id')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('annonce_id')->references('id')->on('camping_zones')->onDelete('cascade');
            $table->foreign('materielle_id')->references('id')->on('materielles')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
