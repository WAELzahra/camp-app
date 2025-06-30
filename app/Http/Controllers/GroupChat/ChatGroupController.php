<?php

namespace App\Http\Controllers\GroupChat;

use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\ChatGroupUser;
use App\Models\ChatGroupMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatGroupTypingStatus; 

class ChatGroupController extends Controller
{

    /**
     * 🏕️ Créer un nouveau groupe de chat
     */
  public function store(Request $request)
    {
        $groupId = Auth::id();

        // Limitation : un groupe toutes les 24h
        $last = ChatGroup::where('group_id', $groupId)->latest()->first();
        if ($last && $last->created_at->diffInMinutes(now()) < 1440) {
            return response()->json(['error' => 'Vous pouvez créer un nouveau groupe toutes les 24 heures.'], 429);
        }

        // Validation
        $request->validate([
            'name' => 'required|string|max:255|unique:chat_groups,name,NULL,id,group_id,' . $groupId,
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Création du groupe
        $token = Str::random(32);
        $chatGroup = ChatGroup::create([
            'group_id' => $groupId,
            'name' => $request->name,
            'invitation_token' => $token,
        ]);

        // Ajouter les membres
        foreach ($request->user_ids as $userId) {
            ChatGroupUser::create([
                'chat_group_id' => $chatGroup->id,
                'user_id' => $userId,
            ]);
        }

        // Ajouter le créateur lui-même
        ChatGroupUser::firstOrCreate([
            'chat_group_id' => $chatGroup->id,
            'user_id' => $groupId, // attention si group != User
        ]);

        return response()->json([
            'status' => 'Groupe de chat créé avec succès',
            'chat_group_id' => $chatGroup->id,
            'invitation_link' => url('/api/group-chat/join/' . $token),
        ]);
    }

    /**
     * 📋 Voir les groupes créés par le groupe de camping
     */
    public function myGroups()
    {
        $groupId = Auth::id();
        $groups = ChatGroup::where('group_id', $groupId)
            ->withCount('users', 'messages')
            ->latest()
            ->get();
        return response()->json($groups);
    }

    /**
     * ❌ Supprimer un groupe (par le créateur uniquement)
     */
    public function destroy($id)
    {
        $groupId = Auth::id();
        $chatGroup = ChatGroup::where('id', $id)->where('group_id', $groupId)->first();

        if (!$chatGroup) {
            return response()->json(['error' => 'Groupe non trouvé ou accès refusé'], 403);
        }

        $chatGroup->delete();
        return response()->json(['message' => 'Groupe supprimé avec succès.']);
    }

    /**
     * 🔗 Rejoindre un groupe via un lien d'invitation
     */
    public function joinByToken($token)
    {
        $userId = Auth::id();
        $group = ChatGroup::where('invitation_token', $token)->first();

        if (!$group) {
            return response()->json(['error' => 'Lien invalide.'], 404);
        }

        $alreadyInGroup = ChatGroupUser::where('chat_group_id', $group->id)
            ->where('user_id', $userId)
            ->exists();

        if (!$alreadyInGroup) {
            ChatGroupUser::create([
                'chat_group_id' => $group->id,
                'user_id' => $userId,
            ]);
        }

        return response()->json(['message' => 'Vous avez rejoint le groupe avec succès.']);
    }

    /**
     * 💬 Envoyer un message dans un groupe
     */
    public function sendMessage(Request $request, $chat_group_id)
    {
        $userId = Auth::id();

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $isMember = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Accès refusé à ce groupe'], 403);
        }

        $msg = ChatGroupMessage::create([
            'chat_group_id' => $chat_group_id,
            'sender_id' => $userId,
            'message' => $request->message,
        ]);

        return response()->json(['message' => 'Message envoyé', 'data' => $msg]);
    }

    /**
     * 📥 Récupérer les messages d’un groupe
     */
    public function getMessages($chat_group_id)
    {
        $userId = Auth::id();

        $isMember = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $messages = ChatGroupMessage::where('chat_group_id', $chat_group_id)
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get();

        return response()->json($messages);
    }

    /**
     * 👥 Récupérer les membres d’un groupe
     */
    public function getMembers($chat_group_id)
    {
        $userId = Auth::id();

        $isMember = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Accès refusé à ce groupe'], 403);
        }

        $members = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->with('user:id,name,email')
            ->get()
            ->pluck('user');

        return response()->json($members);
    }

    /**
     * 📝 Modifier le nom du groupe (par le créateur)
     */
    public function renameGroup(Request $request, $chat_group_id)
    {
        $groupId = Auth::id();

        $request->validate([
            'name' => 'required|string|max:255|unique:chat_groups,name,NULL,id,group_id,' . $groupId,
        ]);

        $chatGroup = ChatGroup::where('id', $chat_group_id)
            ->where('group_id', $groupId)
            ->first();

        if (!$chatGroup) {
            return response()->json(['error' => 'Groupe non trouvé ou accès refusé.'], 403);
        }

        $chatGroup->name = $request->name;
        $chatGroup->save();

        return response()->json(['message' => 'Nom du groupe modifié avec succès.', 'data' => $chatGroup]);
    }

    /**
     * 🚪 Quitter un groupe (campeur uniquement)
     */
    public function leaveGroup($chat_group_id)
    {
        $userId = Auth::id();
        $chatGroup = ChatGroup::find($chat_group_id);

        if (!$chatGroup) {
            return response()->json(['error' => 'Groupe introuvable.'], 404);
        }

        if ($chatGroup->group_id === $userId) {
            return response()->json(['error' => 'Le créateur ne peut pas quitter ce groupe. Supprimez-le à la place.'], 403);
        }

        $member = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $userId)
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Vous ne faites pas partie de ce groupe.'], 403);
        }

        $member->delete();

        return response()->json(['message' => 'Vous avez quitté le groupe.']);
    }

    /**
     * ✍️ Marquer qu’un utilisateur est en train d’écrire
     */
    public function typingStatus(Request $request, $chat_group_id)
    {
        $userId = Auth::id();

        $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        ChatGroupTypingStatus::updateOrCreate(
            ['chat_group_id' => $chat_group_id, 'user_id' => $userId],
            ['is_typing' => $request->is_typing, 'updated_at' => now()]
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * 🔍 Voir qui est en train d’écrire (dernières 10 secondes)
     */
    public function typingUsers($chat_group_id)
    {
        $users = ChatGroupTypingStatus::where('chat_group_id', $chat_group_id)
            ->where('is_typing', true)
            ->where('updated_at', '>=', now()->subSeconds(10))
            ->with('user:id,name')
            ->get()
            ->pluck('user');

        return response()->json($users);
    }

    /**
     * 📂 Archiver / désarchiver un groupe
     */
    public function archive($chat_group_id)
    {
        $groupId = Auth::id();

        $chatGroup = ChatGroup::where('id', $chat_group_id)
            ->where('group_id', $groupId)
            ->first();

        if (!$chatGroup) {
            return response()->json(['error' => 'Groupe non trouvé ou accès refusé.'], 403);
        }

        $chatGroup->is_archived = !$chatGroup->is_archived;
        $chatGroup->save();

        return response()->json([
            'message' => $chatGroup->is_archived ? 'Groupe archivé.' : 'Groupe désarchivé.',
            'data' => $chatGroup,
        ]);
    }

    /**
     * ❌ Supprimer un membre (par le responsable du groupe)
     */
    public function removeMember($chat_group_id, $user_id)
    {
        $groupId = Auth::id();

        $chatGroup = ChatGroup::where('id', $chat_group_id)
            ->where('group_id', $groupId)
            ->first();

        if (!$chatGroup) {
            return response()->json(['error' => 'Groupe non trouvé ou accès refusé.'], 403);
        }

        if ($groupId == $user_id) {
            return response()->json(['error' => 'Vous ne pouvez pas vous retirer vous-même.'], 403);
        }

        $member = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user_id)
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Ce membre ne fait pas partie du groupe.'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Membre supprimé avec succès.']);
    }

}
