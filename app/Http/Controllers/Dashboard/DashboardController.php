<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Reservation_guide;
use App\Models\Feedbacks;
use App\Models\Favorite;

class DashboardController extends Controller
{
    public function stats()
    {
        $user   = Auth::user();
        $roleId = $user->role_id;

        $stats = [];

        // ── Camper (role 1) ──────────────────────────────────────────────
        if ($roleId === 1) {
            $favCount = Favorite::where('user_id', $user->id)->count();
            $stats['favorites_count'] = $favCount;
        }

        // ── Group (role 3) ──────────────────────────────────────────────
        if ($roleId === 3) {
            $profile = $user->profile;
            $followersCount = 0;
            if ($profile && $profile->profileGroupe) {
                $followersCount = $profile->profileGroupe->followers()->count();
            }
            $stats['followers_count'] = $followersCount;
        }

        // ── Guide (role 5) ──────────────────────────────────────────────
        if ($roleId === 5) {
            $bookingsCount = Reservation_guide::where('guide_id', $user->id)->count();
            $avgRating     = Feedbacks::where('type', 'guide')
                ->where('target_id', $user->id)
                ->where('status', 'approved')
                ->avg('note');
            $reviewsCount  = Feedbacks::where('type', 'guide')
                ->where('target_id', $user->id)
                ->where('status', 'approved')
                ->count();

            $stats['bookings_count'] = $bookingsCount;
            $stats['reviews_count']  = $reviewsCount;
            $stats['avg_rating']     = $avgRating ? round($avgRating, 1) : 0;
        }

        return response()->json(['success' => true, 'stats' => $stats]);
    }
}
