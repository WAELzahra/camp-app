<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_popup_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('popup_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'popup_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_popup_states');
    }
};
