<?php

namespace App\Listeners;
use App\Events\MessageSent;
use App\Models\ChatMessage;
class SaveMessageListener
{
    public function handle(MessageSent $event)
    {
        // Exemple : enregistrer le message en base
        ChatMessage::create([
            'user_id' => $event->user->id,
            'message' => $event->message,
            // autres champs...
        ]);

        // Ou autre traitement backend
    }
}
