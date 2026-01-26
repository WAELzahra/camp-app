<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_standard')->default(false); // Basic camping service
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->decimal('min_price', 10, 2)->default(5.00);
            $table->string('unit')->default('person/night');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['name']);
            $table->index(['is_standard', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};