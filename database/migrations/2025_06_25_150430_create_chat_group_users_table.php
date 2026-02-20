<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_group_users', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('chat_group_id')->constrained('chat_groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // User role in group
            $table->enum('role', ['admin', 'moderator', 'member'])->default('member');
            
            // Membership status
            $table->enum('status', ['active', 'muted', 'banned', 'left'])->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            
            // Mute settings
            $table->timestamp('muted_until')->nullable();
            
            // Notification preferences
            $table->boolean('notifications_enabled')->default(true);
            $table->enum('notification_mode', ['all', 'mentions', 'none'])->default('all');
            
            // Last read message
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            
            $table->timestamps();
            
            $table->unique(['chat_group_id', 'user_id']);
            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_group_users');
    }
};