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
        Schema::create('reservations_envets', function (Blueprint $table) {
            $table->id(); // auto-increment primary key
            
            $table->foreignId('group_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->integer('nbr_place')->check('nbr_place > 0');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('cascade');

            $table->unique(['user_id', 'event_id', 'group_id'], 'reservation_envets_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations_envets');
    }
};
