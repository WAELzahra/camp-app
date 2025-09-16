<?php

namespace App\Http\Controllers\Badge;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Badge;
use App\Models\Reservations_centre;
use App\Models\Reservation_guides;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;


class BadgeController extends Controller
{
    // Listing badges for a user
    public function index($userId)
    {
        try {
            // Check if the user exists
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found.'
                ], 404);
            }
            $badges = Badge::where('user_id', $userId)->get();
            return response()->json([
                'status' => 'success',
                'count' => $badges->count(),
                'badges' => $badges
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch badges.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Showing details of a single badge
    public function show($badgeId)
    {
        try {
            // check if the badge exists
            $badge = Badge::find($badgeId);
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'badge not found.'
                ], 404);
            }
            $badges = Badge::find($badgeId)->get();
            return response()->json([
                'status' => 'success',
                'badges' => $badges
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch badges.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    // Awarding a badge to a user
    public function award(Request $request)
    {
        // 1. Validate input
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'provider_id' => 'nullable|exists:users,id', // if badge comes from a provider
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:certification,reputation,badge',
            'icon' => 'nullable|string|max:255',
        ]);

        try {
            // 2. Create a new badge record
            $badge = Badge::create([
                'user_id' => $validated['user_id'],
                'provider_id' => $validated['provider_id'] ?? null,
                'titre' => $validated['titre'],
                'decription' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'icon' => $validated['icon'] ?? null,
                'creation_date' => now(),
            ]);

            // 3. Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Badge awarded successfully!',
                'data' => $badge,
            ]);

        } catch (\Exception $e) {
            // 4. Log error and return failure response
            \Log::error("Error awarding badge: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to award badge.',
            ], 500);
        }
    }

    // Revoking a badge
    public function revoke($badgeId, $userId)
    {
        try {
            $badge = Badge::where('id', $badgeId)
                ->where('user_id', $userId)
                ->first();

            if (!$badge) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Badge not found for this user.'
                ], 404);
            }

            $badge->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Badge revoked successfully.'
            ]);

        } catch (\Exception $e) {
            \Log::error("Error revoking badge: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to revoke badge.'
            ], 500);
        }
    }

    // Listing top badges (most earned or rarest)
    public function leaderboard()
    {
        try {
            $badges = Badge::select('titre', 'type', 'icon', \DB::raw('COUNT(*) as total_earned'))
                ->groupBy('titre', 'type', 'icon')
                ->orderByDesc('total_earned') // Most earned first
                ->take(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $badges
            ]);

        } catch (\Exception $e) {
            \Log::error("Error fetching leaderboard: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load leaderboard.'
            ], 500);
        }
    }
    public function calculateBadgeForUser($userId)
    {
        // 1. Récupérer les activités de l’utilisateur
        $reservationsCentre = Reservations_centre::where('user_id', $userId)->count();
        $reservationsGuides = Reservation_guides::where('reserver_id', $userId)->count();
        $reservationsEvents = Reservations_events::where('user_id', $userId)->count();
        $reservationsMaterielles = Reservations_materielles::where('user_id', $userId)->count();

        $totalActions = $reservationsCentre + $reservationsGuides + $reservationsEvents + $reservationsMaterielles;

        // 2. Déterminer le badge ou niveau
        $badge = null;

        if ($totalActions >= 20) {
            $badge = 'Expert';
        } elseif ($totalActions >= 6) {
            $badge = 'Intermédiaire';
        } else {
            $badge = 'Débutant';
        }

        // 3. Enregistrer le badge si pas déjà attribué
        $existing = Badge::where('user_id', $userId)->where('titre', $badge)->first();
        if (!$existing) {
            Badge::create([
                'user_id' => $userId,
                'provider_id' => null, // ou l’admin
                'creation_date' => now(),
                'titre' => $badge,
                'decription' => "Attribué automatiquement selon vos activités",
                'type' => 'badge',
                'icon' => '⭐', // ou un autre icône
            ]);
        }

        return $badge;
    }


}
