<?php

namespace App\Http\Controllers\Favorites;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    private const VALID_TYPES = ['profile', 'centre', 'zone', 'equipment', 'annonce'];

    // ── POST /api/favorites  { type, target_id } ─────────────────────────────
    public function toggle(Request $request)
    {
        $request->validate([
            'type'      => ['required', 'in:profile,centre,zone,equipment,annonce'],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        $userId   = Auth::id();
        $type     = $request->input('type');
        $targetId = (int) $request->input('target_id');

        $existing = Favorite::where('user_id', $userId)
            ->where('favoritable_type', $type)
            ->where('favoritable_id', $targetId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['favorited' => false]);
        }

        Favorite::create([
            'user_id'          => $userId,
            'favoritable_type' => $type,
            'favoritable_id'   => $targetId,
        ]);

        return response()->json(['favorited' => true]);
    }

    // ── GET /api/favorites?type=centre  (with details) ────────────────────────
    public function list(Request $request)
    {
        $type = $request->query('type');

        $query = Favorite::where('user_id', Auth::id());

        if ($type && in_array($type, self::VALID_TYPES)) {
            $query->where('favoritable_type', $type);
        }

        $favorites = $query->get();

        $items = $favorites->map(fn($f) => $f->resolveTarget())->filter()->values();

        return response()->json(['data' => $items]);
    }

    // ── GET /api/favorites/ids?type=centre  (just IDs array) ─────────────────
    // Used by list pages to know which items have their heart filled.
    public function ids(Request $request)
    {
        $type = $request->query('type');

        $query = Favorite::where('user_id', Auth::id());

        if ($type && in_array($type, self::VALID_TYPES)) {
            $query->where('favoritable_type', $type);
        }

        $ids = $query->pluck('favoritable_id')->values();

        return response()->json(['ids' => $ids]);
    }

    // ── GET /api/favorites/check/{type}/{id} ──────────────────────────────────
    public function check($type, $id)
    {
        if (!in_array($type, self::VALID_TYPES)) {
            return response()->json(['favorited' => false]);
        }

        $favorited = Favorite::where('user_id', Auth::id())
            ->where('favoritable_type', $type)
            ->where('favoritable_id', (int) $id)
            ->exists();

        return response()->json(['favorited' => $favorited]);
    }
}
