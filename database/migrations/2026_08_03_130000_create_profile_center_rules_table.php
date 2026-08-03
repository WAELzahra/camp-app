<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_center_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_center_id')->constrained('profile_centres')->onDelete('cascade');
            $table->enum('type', [
                'pets',
                'alcohol',
                'smoking',
                'loud_music',
                'unmarried_couples',
                'campfires',
                'generators',
                'outside_visitors',
            ]);
            $table->boolean('is_allowed')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['profile_center_id', 'type']);
            $table->index(['type', 'is_allowed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_center_rules');
    }
};
