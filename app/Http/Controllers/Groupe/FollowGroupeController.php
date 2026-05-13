<?php

namespace App\Http\Controllers\Groupe;

use App\Models\ProfileGroupe;
use App\Models\Profile;
use App\Models\User;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Models\FollowersGroupe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class FollowGroupeController extends Controller
{
    // ── Existing: follow by profile_groupes.id ────────────────────────────────
    public function follow(Request $request, $groupeId)
    {
        $user = Auth::user();

        $groupe = ProfileGroupe::find($groupeId);
        if (!$groupe) {
            return response()->json(['message' => 'Groupe introuvable.'], 404);
        }

        $exists = FollowersGroupe::where('user_id', $user->id)
            ->where('groupe_id', $groupeId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Déjà abonné à ce groupe.'], 200);
        }

        FollowersGroupe::create([
            'user_id'   => $user->id,
            'groupe_id' => $groupeId,
        ]);

        return response()->json(['message' => 'Abonnement réussi.']);
    }

    // ── Existing: unfollow by profile_groupes.id ─────────────────────────────
    public function unfollow(Request $request, $groupeId)
    {
        $user = Auth::user();

        FollowersGroupe::where('user_id', $user->id)
            ->where('groupe_id', $groupeId)
            ->delete();

        return response()->json(['message' => 'Désabonnement réussi.']);
    }

    // ── Existing: list followed groups ───────────────────────────────────────
    public function myFollowedGroupes()
    {
        $user = Auth::user();

        $groupes = FollowersGroupe::where('user_id', $user->id)
            ->with('groupe')
            ->get();

        return response()->json($groupes);
    }

    // ── NEW: join group by group's USER id ───────────────────────────────────
    // POST /api/groupes/user/{groupUserId}/join
    public function joinGroup(Request $request, $groupUserId)
    {
        $authUser = Auth::user();

        // Verify the target is a group user (role_id = 2)
        $groupUser = User::where('id', $groupUserId)->where('role_id', 2)->first();
        if (!$groupUser) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        // Resolve or auto-create ProfileGroupe via Profile
        $profile = Profile::firstOrCreate(
            ['user_id' => $groupUserId],
            ['type' => 'groupe']
        );
        $profileGroupe = ProfileGroupe::firstOrCreate(
            ['profile_id' => $profile->id],
            ['nom_groupe' => trim($groupUser->first_name . ' ' . $groupUser->last_name) . ' Group']
        );

        DB::beginTransaction();
        try {
            // Create follow record if not already a member
            $alreadyMember = FollowersGroupe::where('user_id', $authUser->id)
                ->where('groupe_id', $profileGroupe->id)
                ->exists();

            if (!$alreadyMember) {
                FollowersGroupe::create([
                    'user_id'   => $authUser->id,
                    'groupe_id' => $profileGroupe->id,
                ]);
            }

            // Find or create the shared group conversation
            $conversation = Conversation::where('type', 'group')
                ->where('group_id', $groupUserId)
                ->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'type'            => 'group',
                    'name'            => $profileGroupe->nom_groupe ?? $groupUser->first_name . ' Group',
                    'created_by'      => $groupUserId,
                    'group_id'        => $groupUserId,
                    'last_message_at' => now(),
                ]);

                // Add the group owner as admin
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $groupUserId,
                    'role'            => 'admin',
                    'joined_at'       => now(),
                ]);
            }

            // Add the joining user as participant (or reactivate if left before)
            $participant = ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $authUser->id)
                ->first();

            if (!$participant) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $authUser->id,
                    'role'            => 'member',
                    'joined_at'       => now(),
                ]);

                // Post a system message announcing the new member
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $authUser->id,
                    'content'         => trim($authUser->first_name . ' ' . $authUser->last_name) . ' joined the group.',
                    'type'            => 'system',
                ]);

                $conversation->touch('last_message_at');
            } elseif ($participant->left_at !== null) {
                // User previously left — rejoin
                $participant->update(['left_at' => null, 'joined_at' => now()]);

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $authUser->id,
                    'content'         => trim($authUser->first_name . ' ' . $authUser->last_name) . ' rejoined the group.',
                    'type'            => 'system',
                ]);

                $conversation->touch('last_message_at');
            }

            DB::commit();

            return response()->json([
                'success'         => true,
                'message'         => $alreadyMember ? 'Already a member.' : 'Joined group successfully.',
                'already_member'  => $alreadyMember,
                'conversation_id' => $conversation->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to join group: ' . $e->getMessage()], 500);
        }
    }

    // ── NEW: leave group by group's USER id ──────────────────────────────────
    // DELETE /api/groupes/user/{groupUserId}/leave
    public function leaveGroup($groupUserId)
    {
        $authUser = Auth::user();

        $profile       = Profile::where('user_id', $groupUserId)->first();
        $profileGroupe = $profile ? ProfileGroupe::where('profile_id', $profile->id)->first() : null;

        if ($profileGroupe) {
            FollowersGroupe::where('user_id', $authUser->id)
                ->where('groupe_id', $profileGroupe->id)
                ->delete();
        }

        // Soft-remove from shared group conversation
        $conversation = Conversation::where('type', 'group')
            ->where('group_id', $groupUserId)
            ->first();

        if ($conversation) {
            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $authUser->id)
                ->update(['left_at' => now()]);

            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $authUser->id,
                'content'         => trim($authUser->first_name . ' ' . $authUser->last_name) . ' left the group.',
                'type'            => 'system',
            ]);

            $conversation->touch('last_message_at');
        }

        return response()->json(['success' => true, 'message' => 'Left group successfully.']);
    }

    // ── NEW: check membership by group's USER id ─────────────────────────────
    // GET /api/groupes/user/{groupUserId}/membership
    public function checkMembership($groupUserId)
    {
        $authUser = Auth::user();

        $profile       = Profile::where('user_id', $groupUserId)->first();
        $profileGroupe = $profile ? ProfileGroupe::where('profile_id', $profile->id)->first() : null;

        $isMember = $profileGroupe
            ? FollowersGroupe::where('user_id', $authUser->id)
                ->where('groupe_id', $profileGroupe->id)
                ->exists()
            : false;

        $conversationId = null;
        if ($isMember) {
            $conv = Conversation::where('type', 'group')
                ->where('group_id', $groupUserId)
                ->whereHas('participants', fn($q) => $q->where('user_id', $authUser->id)->whereNull('left_at'))
                ->first();
            $conversationId = $conv?->id;
        }

        return response()->json([
            'is_member'       => $isMember,
            'conversation_id' => $conversationId,
        ]);
    }
}
