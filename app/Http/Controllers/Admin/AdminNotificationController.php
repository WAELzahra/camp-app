<?php
// app/Http/Controllers/Admin/AdminNotificationController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Notifications\CustomNotification;

class AdminNotificationController extends Controller
{
    /**
     * Send notification to users (admin only)
     */
    public function send(Request $request)
    {
        $admin = Auth::user();

        // Verify admin role (role_id = 6)
        if ($admin->role_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:system_alert,welcome_message,payment_confirmation,status_update,support_ticket,event_invitation,event_reminder,reservation_confirmed,reservation_cancelled,account_verified,password_changed,profile_updated,promotion,maintenance,security_alert',
            'priority' => 'required|in:low,medium,high,critical',
            'recipients' => 'required|in:all,users,groups,centers,suppliers,guides,admins,custom',
            'user_ids' => 'required_if:recipients,custom|array',
            'user_ids.*' => 'exists:users,id',
            'channels' => 'nullable|array',
            'channels.*' => 'in:in_app,email,push,sms',
            'scheduled_at' => 'nullable|date|after:now',
            'expires_at' => 'nullable|date|after:scheduled_at',
            'action_url' => 'nullable|url',
            'action_text' => 'nullable|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        DB::beginTransaction();
        try {
            // Determine recipients
            $users = $this->getRecipients($request->recipients, $request->user_ids ?? []);

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No recipients found'
                ], 400);
            }

            // Prepare notification data
            $data = [
                'title' => $request->title,
                'content' => $request->content,
                'type' => $request->type,
                'priority' => $request->priority,
                'action_url' => $request->action_url,
                'action_text' => $request->action_text,
                'sender_id' => $admin->id,
            ];

            // Handle image if uploaded
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('notification-images', 'public');
                $data['image'] = $path;
            }

            // Send to each user
            $sentCount = 0;
            $failedCount = 0;
            $channels = $request->channels ?? ['in_app', 'email'];

            foreach ($users as $user) {
                try {
                    // Check user preferences
                    $enabledChannels = [];
                    foreach ($channels as $channel) {
                        if (NotificationPreference::isEnabled($user->id, $request->type, $channel)) {
                            $enabledChannels[] = $channel;
                        }
                    }

                    if (empty($enabledChannels)) {
                        continue;
                    }

                    // Send notification through each enabled channel
                    $notification = new CustomNotification($data, $enabledChannels);
                    
                    if (in_array('in_app', $enabledChannels)) {
                        $user->notify($notification);
                    }

                    if (in_array('email', $enabledChannels) && $user->email) {
                        Mail::to($user->email)->send(new NotificationMail($data));
                    }

                    // Log for each channel
                    foreach ($enabledChannels as $channel) {
                        NotificationLog::create([
                            'user_id' => $user->id,
                            'channel' => $channel,
                            'status' => 'sent',
                        ]);
                    }

                    $sentCount++;

                } catch (\Exception $e) {
                    $failedCount++;
                    
                    NotificationLog::create([
                        'user_id' => $user->id,
                        'channel' => 'in_app',
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Notification sent to {$sentCount} users" . ($failedCount ? " ({$failedCount} failed)" : ""),
                'data' => [
                    'sent' => $sentCount,
                    'failed' => $failedCount,
                    'total_recipients' => $users->count(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification templates
     */
    public function getTemplates()
    {
        $templates = NotificationTemplate::all();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Create notification template
     */
    public function createTemplate(Request $request)
    {
        $admin = Auth::user();

        if ($admin->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'key' => 'required|string|unique:notification_templates,key',
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'variables' => 'nullable|array',
            'channels' => 'nullable|array',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        $template = NotificationTemplate::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Template created',
            'data' => $template
        ], 201);
    }

    /**
     * Update notification template
     */
    public function updateTemplate(Request $request, $id)
    {
        $admin = Auth::user();

        if ($admin->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $template = NotificationTemplate::findOrFail($id);

        $request->validate([
            'key' => 'sometimes|string|unique:notification_templates,key,' . $id,
            'name' => 'sometimes|string|max:255',
            'subject' => 'nullable|string|max:255',
            'content' => 'sometimes|string',
            'variables' => 'nullable|array',
            'channels' => 'nullable|array',
            'priority' => 'sometimes|in:low,medium,high,critical',
        ]);

        $template->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Template updated',
            'data' => $template
        ]);
    }

    /**
     * Delete notification template
     */
    public function deleteTemplate($id)
    {
        $admin = Auth::user();

        if ($admin->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $template = NotificationTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted'
        ]);
    }

    /**
     * Get notification logs
     */
    public function getLogs(Request $request)
    {
        $admin = Auth::user();

        if ($admin->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $logs = NotificationLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Get notification statistics (admin)
     */
    public function getAdminStats()
    {
        $admin = Auth::user();

        if ($admin->role_id !== 6) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_sent' => NotificationLog::count(),
            'sent_today' => NotificationLog::whereDate('created_at', today())->count(),
            'failed' => NotificationLog::where('status', 'failed')->count(),
            'opened' => NotificationLog::where('status', 'opened')->count(),
            'by_channel' => NotificationLog::select('channel', DB::raw('count(*) as count'))
                ->groupBy('channel')
                ->get()
                ->pluck('count', 'channel'),
            'by_type' => DB::table('notifications')
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Helper: Get recipients based on role
     */
    private function getRecipients($type, $customIds = [])
    {
        switch ($type) {
            case 'all':
                return User::all();
            case 'users':
                return User::where('role_id', 1)->get(); // Campers
            case 'groups':
                return User::where('role_id', 2)->get(); // Groups
            case 'centers':
                return User::where('role_id', 3)->get(); // Centers
            case 'suppliers':
                return User::where('role_id', 4)->get(); // Suppliers
            case 'guides':
                return User::where('role_id', 5)->get(); // Guides
            case 'admins':
                return User::where('role_id', 6)->get(); // Admins
            case 'custom':
                return User::whereIn('id', $customIds)->get();
            default:
                return collect([]);
        }
    }
}