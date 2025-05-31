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
            $table->id(); // auto-increment primary key

            $table->foreignId('reserver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('guide_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('circuit_id')->nullable()->constrained()->onDelete('cascade');

            $table->date('creation_date');
            $table->string('type');
            $table->string('discription');

            $table->unique(['reserver_id', 'guide_id', 'creation_date'], 'reservation_guide_unique');

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
