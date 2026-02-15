<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('title')->nullable();
            
            // Remove old columns and add new ones
            // $table->string('tag')->nullable(); - REMOVED
            // $table->string('gatergory')->nullable(); - REMOVED
            
            $table->text('description')->nullable();
            
            // Date fields
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            
            // Archive settings
            $table->boolean('auto_archive')->default(true);
            $table->boolean('is_archived')->default(false);
            
            // Post type
            $table->string('type')->default('Summer Camp');
            
            // Activities as JSON
            $table->json('activities')->nullable();
            
            // Location fields
            $table->string('latitude', 20)->nullable();
            $table->string('longitude', 20)->nullable();
            $table->text('address')->nullable();
            
            // Stats
            $table->integer('views_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            
            // Updated status enum with more options
            $table->enum('status', ['pending', 'approved', 'rejected', 'canceled', 'modified', 'archived'])
                  ->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annonces');
    }
};