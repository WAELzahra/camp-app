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
        Schema::create('reservation_guides', function (Blueprint $table) {
            $table->foreignId('reserver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('guide_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('circuit_id')->constrained()->onDelete('cascade')->nullable();
            $table->date('creation_date');
            $table->string('type');
            $table->string("discription");
            $table->primary(['reserver_id', 'guide_id', 'creation_date']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_guides');
    }
};
