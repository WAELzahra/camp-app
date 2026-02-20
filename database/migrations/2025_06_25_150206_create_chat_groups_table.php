<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            
            // The Group user who created this chat (role_id = 2)
            $table->foreignId('group_user_id')->constrained('users')->onDelete('cascade');
            
            // Chat group details
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('avatar')->nullable(); // Group avatar/icon
            
            // Invitation system
            $table->string('invitation_token')->unique()->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            
            // Group settings
            $table->boolean('is_private')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Group type
            $table->enum('type', ['event', 'camping', 'interest', 'private', 'announcement'])->default('private');
            
            // Maximum members (null = unlimited)
            $table->integer('max_members')->nullable();
            
            // Statistics
            $table->integer('members_count')->default(0);
            $table->integer('messages_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('group_user_id');
            $table->index('invitation_token');
            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_groups');
    }
};