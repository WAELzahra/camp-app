<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_group_messages', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('chat_group_id')->constrained('chat_groups')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            
            // Reply to message
            $table->foreignId('reply_to_id')->nullable()->constrained('chat_group_messages')->onDelete('set null');
            
            // Message content
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->json('mentions')->nullable();
            
            // Message type
            $table->enum('type', ['text', 'image', 'file', 'system', 'announcement'])->default('text');
            
            // Message status
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_system_message')->default(false);
            
            // Delivery status
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['chat_group_id', 'created_at']);
            $table->index('sender_id');
            $table->index('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_group_messages');
    }
};