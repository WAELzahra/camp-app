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
    
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('annonce_id')->nullable()->constrained('camping_zones')->onDelete('cascade');
            $table->foreignId('materielle_id')->nullable()->constrained()->onDelete('cascade');
    
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
