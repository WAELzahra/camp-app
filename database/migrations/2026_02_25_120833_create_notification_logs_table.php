<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('channel');
            $table->enum('status', ['sent', 'delivered', 'failed', 'opened'])->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['notification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};