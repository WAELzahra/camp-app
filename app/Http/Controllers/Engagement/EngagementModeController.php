<?php

namespace App\Http\Controllers\Engagement;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Notifications\CustomNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Task A-02 — provider engagement mode (agency vs commission).
 * Mode changes go through a provider request + admin approval; rates stay admin-only.
 */
class EngagementModeController extends Controller
{
    /** GET /engagement-mode — current mode for the authenticated provider. */
    public function show(Request $request): JsonResponse
    {
        $profile = Profile::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'engagement_mode'                     => $profile->engagement_mode,
                'commission_rate'                     => $profile->commission_rate,
                'agency_margin'                       => $profile->agency_margin,
                'engagement_mode_change_requested_at' => $profile->engagement_mode_change_requested_at,
                'engagement_mode_change_to'           => $profile->engagement_mode_change_to,
            ],
        ]);
    }

    /** POST /engagement-mode/change-request — request switching to the other mode. */
    public function requestChange(Request $request): JsonResponse
    {
        $profile = Profile::where('user_id', $request->user()->id)->firstOrFail();

        if ($profile->engagement_mode_change_requested_at) {
            return response()->json([
                'success' => false,
                'message' => 'Une demande est déjà en cours d\'examen.',
            ], 422);
        }

        $profile->update([
            'engagement_mode_change_requested_at' => now(),
            'engagement_mode_change_to'           => $profile->engagement_mode === 'agency' ? 'commission' : 'agency',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande envoyée. Un administrateur examinera votre demande.',
        ]);
    }

    /* ───────────────────── Admin side ───────────────────── */

    /** GET /admin/engagement-mode/requests — pending change requests. */
    public function pendingRequests(): JsonResponse
    {
        $requests = Profile::with('user:id,first_name,last_name,email,role_id')
            ->whereNotNull('engagement_mode_change_requested_at')
            ->orderBy('engagement_mode_change_requested_at')
            ->get()
            ->map(fn (Profile $p) => [
                'profile_id'   => $p->id,
                'user_name'    => trim(($p->user->first_name ?? '') . ' ' . ($p->user->last_name ?? '')),
                'email'        => $p->user->email ?? null,
                'type'         => $p->type,
                'current_mode' => $p->engagement_mode,
                'requested_to' => $p->engagement_mode_change_to,
                'requested_at' => $p->engagement_mode_change_requested_at,
            ]);

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /** POST /admin/engagement-mode/{profile}/approve */
    public function approveChange(int $profileId): JsonResponse
    {
        $profile = Profile::with('user')->findOrFail($profileId);

        abort_if(!$profile->engagement_mode_change_requested_at, 422, 'Aucune demande en cours.');

        $profile->update([
            'engagement_mode'                     => $profile->engagement_mode_change_to,
            'engagement_mode_locked_at'           => now(),
            'engagement_mode_change_requested_at' => null,
            'engagement_mode_change_to'           => null,
        ]);

        $profile->user?->notify(new CustomNotification([
            'title'   => 'Changement de mode approuvé',
            'content' => 'Votre mode d\'engagement a été mis à jour : ' . ($profile->engagement_mode === 'agency' ? 'Mode Agence' : 'Mode Commission') . '.',
            'type'    => 'status_update',
        ]));

        return response()->json(['success' => true, 'message' => 'Changement approuvé.']);
    }

    /** POST /admin/engagement-mode/{profile}/reject */
    public function rejectChange(int $profileId): JsonResponse
    {
        $profile = Profile::with('user')->findOrFail($profileId);

        $profile->update([
            'engagement_mode_change_requested_at' => null,
            'engagement_mode_change_to'           => null,
        ]);

        $profile->user?->notify(new CustomNotification([
            'title'   => 'Demande refusée',
            'content' => 'Demande refusée.',
            'type'    => 'status_update',
        ]));

        return response()->json(['success' => true, 'message' => 'Demande refusée.']);
    }

    /** PATCH /admin/engagement-mode/{profile}/rates — admin sets contractual rates. */
    public function updateRates(Request $request, int $profileId): JsonResponse
    {
        $validated = $request->validate([
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'agency_margin'   => 'nullable|numeric|min:0|max:100',
        ]);

        Profile::findOrFail($profileId)->update($validated);

        return response()->json(['success' => true, 'message' => 'Taux mis à jour.']);
    }
}
