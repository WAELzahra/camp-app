<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_center_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_center_id')->constrained('profile_centres')->onDelete('cascade');
            $table->foreignId('service_category_id')->constrained('service_categories')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->string('unit')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_standard')->default(false);
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->timestamps();
            
            // Specify a shorter unique constraint name
            $table->unique(['profile_center_id', 'service_category_id'], 'pcs_pc_id_sc_id_unique');
            
            $table->index(['is_available', 'is_standard'], 'pcs_available_standard_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_center_services');
    }
};