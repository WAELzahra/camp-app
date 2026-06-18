<?php

namespace App\Http\Controllers;

use App\Http\Requests\Popup\StorePopupRequest;
use App\Http\Requests\Popup\UpdatePopupRequest;
use App\Models\Popup;
use App\Models\UserPopupState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PopupController extends Controller
{
    /* ──────────────────────────────────────────────
       PUBLIC / AUTHENTICATED
    ────────────────────────────────────────────── */

    /**
     * GET /admin-popups/active
     * Returns active ENGAGEMENT popups the user has not dismissed,
     * filtered by target_roles (null = all roles).
     */
    public function active(Request $request): JsonResponse
    {
        $user = Auth::user();

        $dismissed = UserPopupState::where('user_id', $user->id)
            ->where('is_dismissed', true)
            ->pluck('popup_id');

        $popups = Popup::where('is_active', true)
            ->where('popup_kind', 'engagement')
            ->whereNotIn('id', $dismissed)
            ->latest()
            ->get();

        // Filter by target_roles client-side friendly (role_id match or null = everyone)
        $filtered = $popups->filter(function (Popup $p) use ($user) {
            if (empty($p->target_roles)) {
                return true;
            }          // null → all roles

            return in_array($user->role_id, $p->target_roles);
        })->values();

        return response()->json(['data' => $filtered]);
    }

    /**
     * GET /admin-popups/welcome
     * Returns the active WELCOME popup configured for the user's role.
     */
    public function welcome(Request $request): JsonResponse
    {
        $user = Auth::user();

        $popup = Popup::where('is_active', true)
            ->where('popup_kind', 'welcome')
            ->latest()
            ->get()
            ->first(function (Popup $p) use ($user) {
                if (empty($p->target_roles)) {
                    return true;
                }

                return in_array($user->role_id, $p->target_roles);
            });

        return response()->json(['data' => $popup]);
    }

    /**
     * POST /admin-popups/{popup}/dismiss
     * Mark a popup as dismissed for the current user.
     */
    public function dismiss(Popup $popup): JsonResponse
    {
        $user = Auth::user();

        UserPopupState::updateOrCreate(
            ['user_id' => $user->id, 'popup_id' => $popup->id],
            ['is_dismissed' => true]
        );

        return response()->json(['message' => 'Popup dismissed.']);
    }

    /* ──────────────────────────────────────────────
       ADMIN CRUD
    ────────────────────────────────────────────── */

    /**
     * GET /admin/popups
     */
    public function index(): JsonResponse
    {
        $popups = Popup::latest()->get();

        return response()->json(['data' => $popups]);
    }

    /**
     * POST /admin/popups
     */
    public function store(StorePopupRequest $request): JsonResponse
    {
        $data = $request->validated();

        $popup = Popup::create($data);

        return response()->json(['data' => $popup, 'message' => 'Popup created.'], 201);
    }

    /**
     * PUT /admin/popups/{popup}
     */
    public function update(UpdatePopupRequest $request, Popup $popup): JsonResponse
    {
        $data = $request->validated();

        $popup->update($data);

        return response()->json(['data' => $popup, 'message' => 'Popup updated.']);
    }

    /**
     * DELETE /admin/popups/{popup}
     */
    public function destroy(Popup $popup): JsonResponse
    {
        $popup->delete();

        return response()->json(['message' => 'Popup deleted.']);
    }

    /**
     * PATCH /admin/popups/{popup}/toggle
     */
    public function toggle(Popup $popup): JsonResponse
    {
        $popup->update(['is_active' => !$popup->is_active]);

        return response()->json(['data' => $popup, 'message' => 'Status toggled.']);
    }
}
