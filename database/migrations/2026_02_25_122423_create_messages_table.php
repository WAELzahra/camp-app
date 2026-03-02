<?php
// database/migrations/2024_01_01_000001_create_messages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unified conversations table (both DMs and groups)
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'group'])->index();
            $table->string('name')->nullable(); // For groups only
            $table->string('avatar')->nullable(); // For groups only
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('group_id')->nullable()->constrained('users')->onDelete('set null'); // Links to group users
            $table->timestamp('last_message_at')->nullable();
            $table->json('metadata')->nullable(); // Flexible for future needs
            $table->timestamps();
        });

        // Conversation participants (for both DMs and groups)
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable(); // For leaving groups
            $table->boolean('is_muted')->default(false);
            $table->timestamps();
            
            $table->unique(['conversation_id', 'user_id']);
        });

        // Unified messages table
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('content')->nullable();
            $table->enum('type', ['text', 'image', 'file', 'system', 'event_invitation'])->default('text');
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes(); // For message recall
            $table->timestamps();
            
            $table->index(['conversation_id', 'created_at']);
        });

        // Message status (read/delivered per participant)
        Schema::create('message_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->unique(['message_id', 'user_id']);
        });

        // Unified attachments
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size');
            $table->string('mime_type');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Unified reactions
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reaction', 50);
            $table->timestamps();
            
            $table->unique(['message_id', 'user_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_statuses');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};