<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    public function run()
    {
        // First, let's get all users to send notifications to
        $users = DB::table('users')->get();
        
        // Create notification templates - with duplicate handling
        $this->createTemplates();
        
        // Create notification preferences for users
        $this->createPreferences($users);
        
        // Array of notification types
        $notificationTypes = [
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
        ];

        // Sample notification data for different scenarios
        $notifications = [];

        // 1. Welcome notifications for all users
        foreach ($users as $user) {
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'welcome_message',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => 'Welcome to Tunisia Camp!',
                    'message' => "Welcome {$user->first_name}! We're excited to have you on board.",
                    'action_url' => '/profile',
                    'action_text' => 'Complete Your Profile',
                    'icon' => '🎉'
                ]),
                'read_at' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 30)) : null,
                'archived_at' => null,
                'priority' => 'low',
                'channels' => json_encode(['in_app', 'email']),
                'sender_id' => 1, // Admin user
                'created_at' => Carbon::now()->subDays(rand(1, 60)),
                'updated_at' => Carbon::now()->subDays(rand(1, 60)),
            ];
        }

        // 2. Event-related notifications
        if (DB::getSchemaBuilder()->hasTable('events')) {
            $events = DB::table('events')->take(5)->get();
            foreach ($events as $event) {
                // Notify followers about new events
                if (DB::getSchemaBuilder()->hasTable('follows')) {
                    $followerIds = DB::table('follows')
                        ->where('groupe_id', $event->group_id)
                        ->pluck('user_id')
                        ->toArray();

                    foreach (array_slice($followerIds, 0, 3) as $userId) {
                        $notifications[] = [
                            'id' => Str::uuid()->toString(),
                            'type' => 'event_invitation',
                            'notifiable_type' => 'App\Models\User',
                            'notifiable_id' => $userId,
                            'data' => json_encode([
                                'title' => 'New Event Alert!',
                                'message' => "Check out the new event: {$event->title}",
                                'event_id' => $event->id,
                                'event_title' => $event->title,
                                'start_date' => $event->start_date,
                                'action_url' => "/events/{$event->id}",
                                'action_text' => 'View Event',
                                'icon' => '📅'
                            ]),
                            'read_at' => null,
                            'archived_at' => null,
                            'priority' => 'medium',
                            'channels' => json_encode(['in_app', 'email']),
                            'sender_id' => $event->group_id,
                            'created_at' => Carbon::now()->subHours(rand(1, 48)),
                            'updated_at' => Carbon::now()->subHours(rand(1, 48)),
                        ];
                    }
                }
            }
        }

        // 3. Reservation confirmations
        if (DB::getSchemaBuilder()->hasTable('reservations_events')) {
            $reservations = DB::table('reservations_events')->take(5)->get();
            foreach ($reservations as $reservation) {
                // Notify user about reservation status
                $type = $reservation->status === 'confirmée' ? 'reservation_confirmed' : 'reservation_cancelled';
                
                $notifications[] = [
                    'id' => Str::uuid()->toString(),
                    'type' => $type,
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $reservation->user_id,
                    'data' => json_encode([
                        'title' => 'Reservation ' . ($reservation->status === 'confirmée' ? 'Confirmed' : 'Updated'),
                        'message' => "Your reservation has been {$reservation->status}.",
                        'reservation_id' => $reservation->id,
                        'status' => $reservation->status,
                        'nbr_place' => $reservation->nbr_place,
                        'action_url' => "/reservations/{$reservation->id}",
                        'action_text' => 'View Details',
                        'icon' => $reservation->status === 'confirmée' ? '✅' : '❌'
                    ]),
                    'read_at' => $reservation->status === 'confirmée' ? Carbon::now()->subDays(rand(1, 5)) : null,
                    'archived_at' => null,
                    'priority' => $reservation->status === 'confirmée' ? 'medium' : 'high',
                    'channels' => json_encode(['in_app', 'email']),
                    'sender_id' => $reservation->group_id,
                    'created_at' => Carbon::now()->subDays(rand(1, 10)),
                    'updated_at' => Carbon::now()->subDays(rand(1, 10)),
                ];
            }
        }

        // 4. System notifications for admins
        $admins = DB::table('users')->where('role_id', 6)->get();
        foreach ($admins as $admin) {
            // New user registration alert
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'system_alert',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $admin->id,
                'data' => json_encode([
                    'title' => 'New User Registration',
                    'message' => 'A new user has registered on the platform.',
                    'user_count' => DB::table('users')->count(),
                    'action_url' => '/admin/users',
                    'action_text' => 'View Users',
                    'icon' => '👤'
                ]),
                'read_at' => Carbon::now()->subHours(rand(1, 12)),
                'archived_at' => null,
                'priority' => 'low',
                'channels' => json_encode(['in_app']),
                'sender_id' => 1,
                'created_at' => Carbon::now()->subHours(rand(1, 24)),
                'updated_at' => Carbon::now()->subHours(rand(1, 24)),
            ];
        }

        // 5. Profile update reminders
        foreach ($users->take(15) as $user) {
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'profile_updated',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => 'Complete Your Profile',
                    'message' => 'Take a moment to complete your profile information.',
                    'completion_percentage' => rand(30, 80),
                    'action_url' => '/profile/edit',
                    'action_text' => 'Update Profile',
                    'icon' => '📝'
                ]),
                'read_at' => null,
                'archived_at' => null,
                'priority' => 'low',
                'channels' => json_encode(['in_app', 'email']),
                'sender_id' => 1,
                'created_at' => Carbon::now()->subDays(rand(1, 15)),
                'updated_at' => Carbon::now()->subDays(rand(1, 15)),
            ];
        }

        // 6. Promotional notifications
        foreach ($users->take(10) as $user) {
            $notifications[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'promotion',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => 'Special Offer!',
                    'message' => 'Get 20% off on your next camping booking!',
                    'discount' => '20%',
                    'code' => 'CAMP20',
                    'expiry' => Carbon::now()->addDays(7)->format('Y-m-d'),
                    'action_url' => '/events',
                    'action_text' => 'Book Now',
                    'icon' => '🏕️'
                ]),
                'read_at' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 2)) : null,
                'archived_at' => null,
                'priority' => 'medium',
                'channels' => json_encode(['in_app', 'email', 'push']),
                'sender_id' => 1,
                'created_at' => Carbon::now()->subDays(rand(1, 5)),
                'updated_at' => Carbon::now()->subDays(rand(1, 5)),
            ];
        }

        // Insert notifications in chunks
        if (!empty($notifications)) {
            foreach (array_chunk($notifications, 100) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
        }

        // Create notification logs
        $this->createNotificationLogs($users);
    }

    private function createTemplates()
    {
        // Check if templates table exists
        if (!DB::getSchemaBuilder()->hasTable('notification_templates')) {
            return;
        }

        // First, clear existing templates or use updateOrInsert
        $templates = [
            [
                'key' => 'welcome',
                'name' => 'Welcome Notification',
                'subject' => 'Welcome to {{app_name}}!',
                'content' => 'Hello {{first_name}}, welcome to {{app_name}}! We\'re excited to have you.',
                'variables' => json_encode(['first_name', 'app_name']),
                'channels' => json_encode(['in_app', 'email']),
                'priority' => 'low',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'new_message',
                'name' => 'New Message',
                'subject' => 'New message from {{sender_name}}',
                'content' => 'You have a new message from {{sender_name}}.',
                'variables' => json_encode(['sender_name']),
                'channels' => json_encode(['in_app', 'push']),
                'priority' => 'high',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'event_reminder',
                'name' => 'Event Reminder',
                'subject' => 'Reminder: {{event_title}} starts soon!',
                'content' => 'Your event "{{event_title}}" starts on {{start_date}}. Don\'t forget!',
                'variables' => json_encode(['event_title', 'start_date']),
                'channels' => json_encode(['in_app', 'email', 'push']),
                'priority' => 'high',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment_confirmation',
                'name' => 'Payment Confirmation',
                'subject' => 'Payment Confirmed: {{amount}} TND',
                'content' => 'Your payment of {{amount}} TND has been confirmed. Thank you for your booking!',
                'variables' => json_encode(['amount', 'booking_reference']),
                'channels' => json_encode(['in_app', 'email']),
                'priority' => 'medium',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($templates as $template) {
            // Use updateOrInsert to avoid duplicate key errors
            DB::table('notification_templates')->updateOrInsert(
                ['key' => $template['key']],
                $template
            );
        }
    }

    private function createPreferences($users)
    {
        // Check if preferences table exists
        if (!DB::getSchemaBuilder()->hasTable('notification_preferences')) {
            return;
        }

        $types = [
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
        ];
        
        // Allowed channels from your ENUM
        $channels = ['in_app', 'email', 'push', 'sms'];

        $preferences = [];

        foreach ($users as $user) {
            foreach ($types as $type) {
                foreach ($channels as $channel) {
                    // Check if preference already exists
                    $exists = DB::table('notification_preferences')
                        ->where('user_id', $user->id)
                        ->where('type', $type)
                        ->where('channel', $channel)
                        ->exists();

                    if (!$exists) {
                        // 80% chance of being enabled
                        $enabled = rand(0, 100) < 80;

                        $preferences[] = [
                            'user_id' => $user->id,
                            'type' => $type,
                            'channel' => $channel,
                            'enabled' => $enabled,
                            'created_at' => now()->subDays(rand(1, 30)),
                            'updated_at' => now()->subDays(rand(1, 30)),
                        ];
                    }
                }
            }
        }

        // Insert in chunks to avoid memory issues
        if (!empty($preferences)) {
            foreach (array_chunk($preferences, 100) as $chunk) {
                DB::table('notification_preferences')->insert($chunk);
            }
        }
    }

    private function createNotificationLogs($users)
    {
        // Check if logs table exists
        if (!DB::getSchemaBuilder()->hasTable('notification_logs')) {
            return;
        }

        $channels = ['in_app', 'email', 'push', 'sms'];
        $statuses = ['sent', 'delivered', 'failed', 'opened'];

        $logs = [];

        // Get all notification IDs
        $notificationIds = DB::table('notifications')->pluck('id')->toArray();

        foreach (array_slice($notificationIds, 0, 100) as $notificationId) {
            $user = $users->random();
            $channel = $channels[array_rand($channels)];
            $status = $statuses[array_rand($statuses)];
            
            // Check if log already exists for this notification and user
            $exists = DB::table('notification_logs')
                ->where('notification_id', $notificationId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$exists) {
                $log = [
                    'notification_id' => $notificationId,
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'status' => $status,
                    'error_message' => $status === 'failed' ? 'Failed to send notification' : null,
                    'opened_at' => $status === 'opened' ? now()->subHours(rand(1, 24)) : null,
                    'created_at' => now()->subDays(rand(1, 10)),
                    'updated_at' => now()->subDays(rand(1, 10)),
                ];

                $logs[] = $log;
            }
        }

        if (!empty($logs)) {
            foreach (array_chunk($logs, 100) as $chunk) {
                DB::table('notification_logs')->insert($chunk);
            }
        }
    }
}