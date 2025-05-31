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
            $table->id(); // Auto-incrementing primary key

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('centre_id')->constrained('users')->onDelete('cascade');

            $table->date('date_debut');
            $table->date('date_fin');

            $table->integer('nbr_place')->check('nbr_place > 1');
            $table->string('note')->nullable();
            $table->string('type')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'canceled'])->default('pending');

            $table->foreignId('payments_id')->nullable()->constrained()->onDelete('cascade');

            // Unique constraint on user_id, centre_id, and date_debut
            $table->unique(['user_id', 'centre_id', 'date_debut'], 'unique_user_centre_date');

            $table->timestamps();
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
