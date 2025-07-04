<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 

     
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('users')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->string('category')->nullable();
            $table->date('date_sortie');
            $table->date('date_retoure');
            $table->json('ville_passente')->nullable();
            $table->integer('nbr_place_total');
            $table->integer('nbr_place_restante');
            $table->enum("status",["scheduled","finished","canceled","postponed","full"]);
            $table->float("prix_place");
            
            $table->foreignId('circuit_id')->constrained('circuits')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
