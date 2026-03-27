<?php

namespace App\Http\Controllers\Groupe;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\ProfileGroupe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupeCoOwnerController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve the ProfileGroupe for the authenticated group owner,
     * auto-creating Profile/ProfileGroupe if they don't exist yet.
     */
    private function resolveOwnProfileGroupe(): ?ProfileGroupe
    {
        $user    = Auth::user();
        $profile = Profile::firstOrCreate(['user_id' => $user->id], ['type' => 'groupe']);

        return ProfileGroupe::firstOrCreate(
            ['profile_id' => $profile->id],
            ['nom_groupe' => trim($user->first_name . ' ' . $user->last_name) . ' Group']
        );
    }

    // ── Search campers (role_id = 1) ─────────────────────────────────────────
    // GET /api/groupes/co-owners/search?q=…
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $authId = Auth::id();

        $users = User::where('role_id', 1)
            ->where('id', '!=', $authId)
            ->where(function ($query) use ($q) {
                $query->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                      ->orWhere('email', 'LIKE', "%{$q}%");
            })
            ->select('id', 'first_name', 'last_name', 'email', 'avatar')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'first_name' => $u->first_name,
                'last_name'  => $u->last_name,
                'email'      => $u->email,
                'avatar'     => $u->avatar ? asset('storage/' . $u->avatar) : null,
            ]);

        return response()->json(['data' => $users]);
    }

    // ── List co-owners (public, by group user ID) ─────────────────────────────
    // GET /api/groupes/user/{groupUserId}/co-owners
    public function list($groupUserId)
    {
        $profile       = Profile::where('user_id', $groupUserId)->first();
        $profileGroupe = $profile ? ProfileGroupe::where('profile_id', $profile->id)->first() : null;

        if (!$profileGroupe) {
            return response()->json(['data' => []]);
        }

        $coOwners = $profileGroupe->coOwners()
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.avatar')
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'first_name' => $u->first_name,
                'last_name'  => $u->last_name,
                'email'      => $u->email,
                'avatar'     => $u->avatar ? asset('storage/' . $u->avatar) : null,
            ]);

        return response()->json(['data' => $coOwners]);
    }

    // ── Add co-owner (authenticated group owner only) ─────────────────────────
    // POST /api/groupes/co-owners/{userId}
    public function add($userId)
    {
        $authUser = Auth::user();

        if ($authUser->role_id !== 2) {
            return response()->json(['message' => 'Only group accounts can manage co-owners.'], 403);
        }

        // Cannot add yourself
        if ((int) $userId === $authUser->id) {
            return response()->json(['message' => 'You cannot add yourself as a co-owner.'], 422);
        }

        // Target must be a camper
        $target = User::where('id', $userId)->where('role_id', 1)->first();
        if (!$target) {
            return response()->json(['message' => 'User not found or not a camper.'], 404);
        }

        $profileGroupe = $this->resolveOwnProfileGroupe();

        // Sync without detaching existing ones
        if (!$profileGroupe->coOwners()->where('user_id', $userId)->exists()) {
            $profileGroupe->coOwners()->attach($userId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Co-owner added.',
            'user'    => [
                'id'         => $target->id,
                'first_name' => $target->first_name,
                'last_name'  => $target->last_name,
                'email'      => $target->email,
                'avatar'     => $target->avatar ? asset('storage/' . $target->avatar) : null,
            ],
        ]);
    }

    // ── Remove co-owner (authenticated group owner only) ──────────────────────
    // DELETE /api/groupes/co-owners/{userId}
    public function remove($userId)
    {
        $authUser = Auth::user();

        if ($authUser->role_id !== 2) {
            return response()->json(['message' => 'Only group accounts can manage co-owners.'], 403);
        }

        $profileGroupe = $this->resolveOwnProfileGroupe();
        $profileGroupe->coOwners()->detach($userId);

        return response()->json(['success' => true, 'message' => 'Co-owner removed.']);
    }
}
