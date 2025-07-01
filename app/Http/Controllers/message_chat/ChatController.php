<?php



namespace App\Http\Controllers\message_chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // Enregistrer le message en base
        $chatMessage = ChatMessage::create([
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        // Diffuser l'événement (broadcast)
        broadcast(new MessageSent(Auth::user(), $chatMessage->message))->toOthers();

        return response()->json(['status' => 'Message sent!']);
    }
}

