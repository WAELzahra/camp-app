<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\CentreClaim;
use App\Models\Events;
use App\Models\Feedback;
use App\Models\ProfileCentre;
use App\Models\Report;
use App\Models\Reservations_events;
use App\Models\User;

class AdminActivityController extends Controller
{
    /**
     * Single lightweight endpoint consumed by the admin sidebar badge system.
     * Returns counts of items needing attention + items new in the last 7 days.
     */
    public function counts()
    {
        try {
            $since7d = now()->subDays(7);

            return response()->json([
                'success' => true,
                'data'    => [
                    // "New" items (last 7 days) — shown to indicate platform growth
                    'users'        => User::where('created_at', '>=', $since7d)->count(),
                    'centres'      => ProfileCentre::where('created_at', '>=', $since7d)->count(),

                    // "Pending" items — items that need admin action
                    'events'       => Events::where('status', 'pending')->count(),
                    'feedbacks'    => Feedback::where('status', 'pending')->count(),
                    'posts'        => Annonce::where('status', 'pending')->count(),
                    'reports'      => Report::where('status', 'open')->count(),
                    'claims'       => CentreClaim::where('status', 'pending')->count(),
                    'reservations' => Reservations_events::where('status', 'en_attente_validation')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            // Never break the sidebar — return zeroes on any error
            return response()->json([
                'success' => true,
                'data'    => [
                    'users' => 0, 'centres' => 0, 'events' => 0, 'feedbacks' => 0,
                    'posts' => 0, 'reports' => 0, 'claims' => 0, 'reservations' => 0,
                ],
            ]);
        }
    }
}
