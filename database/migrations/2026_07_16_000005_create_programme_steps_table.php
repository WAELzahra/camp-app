<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('day_offset')->default(0);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location_label', 255)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index('programme_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_steps');
    }
};
