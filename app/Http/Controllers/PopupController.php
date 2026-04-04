<?php

namespace App\Http\Controllers;

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
     * Returns active popups that the authenticated user has NOT dismissed.
     */
    public function active(Request $request): JsonResponse
    {
        $user = Auth::user();

        $dismissed = UserPopupState::where('user_id', $user->id)
            ->where('is_dismissed', true)
            ->pluck('popup_id');

        $popups = Popup::where('is_active', true)
            ->whereNotIn('id', $dismissed)
            ->latest()
            ->get(['id', 'title', 'content', 'type', 'is_active']);

        return response()->json(['data' => $popups]);
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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'content'   => 'required|string',
            'type'      => 'required|in:info,warning,promotion,update',
            'is_active' => 'boolean',
        ]);

        $popup = Popup::create($data);

        return response()->json(['data' => $popup, 'message' => 'Popup created.'], 201);
    }

    /**
     * PUT /admin/popups/{popup}
     */
    public function update(Request $request, Popup $popup): JsonResponse
    {
        $data = $request->validate([
            'title'     => 'sometimes|required|string|max:255',
            'content'   => 'sometimes|required|string',
            'type'      => 'sometimes|required|in:info,warning,promotion,update',
            'is_active' => 'sometimes|boolean',
        ]);

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
     * Toggle is_active without a full update payload.
     */
    public function toggle(Popup $popup): JsonResponse
    {
        $popup->update(['is_active' => !$popup->is_active]);
        return response()->json(['data' => $popup, 'message' => 'Status toggled.']);
    }
}
