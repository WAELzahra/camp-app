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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            
            // User who wrote the comment
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            // The post/annonce being commented on
            $table->foreignId('annonce_id')
                  ->constrained('annonces')
                  ->onDelete('cascade');
            
            // For nested comments (replies)
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('comments')
                  ->onDelete('cascade');
            
            // Comment content
            $table->text('content');
            
            // Engagement stats
            $table->integer('likes_count')->default(0);
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_hidden')->default(false);
            
            $table->timestamps();
            $table->softDeletes(); // Allow soft deletes
            
            // Indexes for better performance
            $table->index(['annonce_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('parent_id');
        });

        // Create comment likes table for users who liked comments
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('comment_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Prevent duplicate likes
            $table->unique(['user_id', 'comment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_likes');
        Schema::dropIfExists('comments');
    }
};