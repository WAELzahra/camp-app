<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_center_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_center_id')->constrained('profile_centres')->onDelete('cascade');
            $table->enum('type', [
                'toilets', 
                'drinking_water', 
                'electricity', 
                'parking', 
                'wifi', 
                'showers', 
                'security',
                'kitchen',
                'bbq_area',
                'swimming_pool'
            ]);
            $table->boolean('is_available')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['profile_center_id', 'type']);
            $table->index(['type', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_center_equipment');
    }
};