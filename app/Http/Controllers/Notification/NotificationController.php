<?php
// app/Http/Controllers/Notification/NotificationController.php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMail;

class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->notifications();

        // Filters
        if ($request->has('type')) {
            $query->where('type', 'LIKE', "%{$request->type}%");
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('unread')) {
            $query->whereNull('read_at');
        }

        if ($request->has('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'meta' => [
                'unread_count' => $user->unreadNotifications->count(),
            ]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        // Log
        NotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'channel' => 'in_app',
            'status' => 'opened',
            'opened_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        $count = $user->unreadNotifications->count();

        $user->unreadNotifications->each(function($notification) use ($user) {
            $notification->markAsRead();
            
            NotificationLog::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'channel' => 'in_app',
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marked as read"
        ]);
    }

    /**
     * Archive notification
     */
    public function archive($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsArchived();

        return response()->json([
            'success' => true,
            'message' => 'Notification archived'
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Get notification preferences
     */
    public function getPreferences()
    {
        $user = Auth::user();

        $preferences = NotificationPreference::where('user_id', $user->id)
            ->get()
            ->groupBy('type');

        // Default preferences if not set
        $types = [
            'system_alert', 'welcome_message', 'payment_confirmation', 'status_update',
            'support_ticket', 'event_invitation', 'event_reminder', 'reservation_confirmed',
            'reservation_cancelled', 'account_verified', 'password_changed', 'profile_updated',
            'promotion', 'maintenance', 'security_alert'
        ];

        $channels = ['in_app', 'email', 'push', 'sms'];

        $result = [];
        foreach ($types as $type) {
            foreach ($channels as $channel) {
                $result[$type][$channel] = isset($preferences[$type]) 
                    ? $preferences[$type]->where('channel', $channel)->first()?->enabled ?? true
                    : true;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'preferences' => 'required|array',
            'preferences.*.*' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->preferences as $type => $channels) {
                foreach ($channels as $channel => $enabled) {
                    NotificationPreference::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'type' => $type,
                            'channel' => $channel,
                        ],
                        ['enabled' => $enabled]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStats()
    {
        $user = Auth::user();

        $stats = [
            'total' => $user->notifications()->count(),
            'unread' => $user->unreadNotifications->count(),
            'archived' => $user->notifications()->whereNotNull('archived_at')->count(),
            'by_type' => $user->notifications()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type'),
            'by_priority' => $user->notifications()
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->get()
                ->pluck('count', 'priority'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    /**
     * Get unread count for user
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        
        return response()->json([
            'success' => true,
            'count' => $user->unreadNotifications->count()
        ]);
    }
}