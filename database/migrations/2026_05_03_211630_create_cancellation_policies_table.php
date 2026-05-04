<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['centre', 'materiel', 'event']);
            $table->string('name', 100);
            // null = global platform default; set = custom policy for a specific centre
            $table->unsignedBigInteger('centre_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('centre_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['type', 'is_active']);
            $table->index(['centre_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_policies');
    }
};
