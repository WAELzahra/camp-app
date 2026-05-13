<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', [
                'system_alert',
                'welcome_message',
                'payment_confirmation',
                'status_update',
                'support_ticket',
                'event_invitation',
                'event_reminder',
                'reservation_confirmed',
                'reservation_cancelled',
                'account_verified',
                'password_changed',
                'profile_updated',
                'promotion',
                'maintenance',
                'security_alert'
            ])->default('system_alert');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('channels')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
            $table->index(['priority', 'created_at']);
            $table->index(['scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};