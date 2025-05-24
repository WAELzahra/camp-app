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
        Schema::create('reservations_centres', function (Blueprint $table) {
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('centre_id')->constrained('users')->onDelete('cascade');

        $table->date('date_debut');
        $table->date('date_fin');
        $table->integer('nbr_place')->check('nbr_place > 1');
        $table->string('note')->nullable();
        $table->string('type')->nullable();
        $table->enum('status', ['pending', 'approved', 'rejected', 'canceled'])->default('pending');
        $table->foreignId('payments_id')->constrained()->onDelete('cascade')->nullable();

        // Composite primary key using existing fields
        $table->primary(['user_id', 'centre_id', 'date_debut']);
 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations_centres');
    }
};
