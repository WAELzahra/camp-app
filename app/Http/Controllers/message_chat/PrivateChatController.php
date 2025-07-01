<?php

namespace App\Http\Controllers\message_chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatMessage;

use Illuminate\Support\Facades\Auth;
use App\Events\UserTyping;
use App\Models\ArchivedConversation;

class PrivateChatController extends Controller
{
    /**
     * 📤 Envoyer un message (texte, image, fichier, lien)
     */
    public function send(Request $request)
    {
        $request->validate([
            'receiver_id'   => 'required|exists:users,id',
            'event_id'      => 'required|integer',
            'message'       => 'nullable|string|max:1000',
            'message_type'  => 'required|in:text,image,file,link',
            'file'          => 'nullable|file|max:2048',
        ]);

        $data = [
            'sender_id'     => Auth::id(),
            'receiver_id'   => $request->receiver_id,
            'event_id'      => $request->event_id,
            'message_type'  => $request->message_type,
            'message'       => $request->message,
        ];

        // 📎 Gérer le fichier si présent
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('chat_files', 'public');
            $data['file_path'] = $path;
        }

        $message = ChatMessage::create($data);

        return response()->json([
            'status' => 'Message envoyé !',
            'message' => $message,
        ]);
    }

    /**
     * 💬 Récupérer la conversation entre deux utilisateurs pour un événement
     */
    public function conversation($receiverId, $eventId)
    {
        $userId = Auth::id();

        $messages = ChatMessage::where(function ($query) use ($userId, $receiverId) {
                $query->where('sender_id', $userId)->where('receiver_id', $receiverId);
            })
            ->orWhere(function ($query) use ($userId, $receiverId) {
                $query->where('sender_id', $receiverId)->where('receiver_id', $userId);
            })
            ->where('event_id', $eventId)
            ->whereNull('deleted_at') // ⚠️ Messages supprimés exclus
            ->orderBy('created_at', 'asc')
            ->get();

        // ✅ Marquer les messages comme lus
        ChatMessage::where('sender_id', $receiverId)
            ->where('receiver_id', $userId)
            ->where('event_id', $eventId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'status' => 'read',
            ]);

        return response()->json($messages);
    }

    /**
     * 🔴 Compter les messages non lus pour un événement
     */
    public function unreadCount($event_id)
    {
        $userId = Auth::id();

        $count = ChatMessage::where('receiver_id', $userId)
            ->where('event_id', $event_id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread' => $count]);
    }

    /**
     * ❌ Supprimer (soft delete) un message envoyé
     */
    public function deleteMessage($id)
    {
        $message = ChatMessage::findOrFail($id);

        if ($message->sender_id !== Auth::id()) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $message->delete();

        return response()->json(['status' => 'Message supprimé']);
    }

    /**
     * ✍️ Indiquer à l’autre utilisateur qu’on est en train d’écrire
     */
    public function typing(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'event_id'    => 'required|integer',
        ]);

        broadcast(new UserTyping(Auth::id(), $request->receiver_id, $request->event_id))->toOthers();

        return response()->json(['status' => 'Typing event sent']);
    }

    /**
     * 📂 Archiver une conversation avec un utilisateur pour un événement
     */
    public function archiveConversation(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'event_id' => 'required|integer',
        ]);

        ArchivedConversation::firstOrCreate([
            'user_id'     => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'event_id'    => $request->event_id,
        ]);

        return response()->json(['status' => 'Conversation archivée']);
    }

    /**
     * 📚 Lister toutes les conversations privées de l'utilisateur
     */
    public function listConversations()
    {
        $userId = Auth::id();

        $messages = ChatMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->get();

        $conversations = $messages->groupBy(function ($message) use ($userId) {
            $otherUserId = $message->sender_id === $userId ? $message->receiver_id : $message->sender_id;
            return $otherUserId . '-' . $message->event_id;
        })->map(function ($group) use ($userId) {
            $lastMessage = $group->sortByDesc('created_at')->first();
            $otherUserId = $lastMessage->sender_id === $userId ? $lastMessage->receiver_id : $lastMessage->sender_id;

            return [
                'user_id'      => $otherUserId,
                'event_id'     => $lastMessage->event_id,
                'last_message' => $lastMessage->message,
                'last_date'    => $lastMessage->created_at->toDateTimeString(),
                'unread_count' => $group->where('receiver_id', $userId)->where('is_read', false)->count(),
            ];
        })->values();

        return response()->json($conversations);
    }
}
