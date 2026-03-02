<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
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
            ]);
            $table->enum('channel', ['in_app', 'email', 'push', 'sms'])->default('in_app');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            
            $table->unique(['user_id', 'type', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};