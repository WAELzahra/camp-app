<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE notifications
            MODIFY COLUMN `type` ENUM(
                'system_alert',
                'welcome_message',
                'payment_confirmation',
                'payment',
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
            ) NOT NULL DEFAULT 'system_alert'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE notifications
            MODIFY COLUMN `type` ENUM(
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
            ) NOT NULL DEFAULT 'system_alert'
        ");
    }
};
