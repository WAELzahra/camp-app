<?php

namespace App\Http\Controllers\Event;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MessageFromCamper;
class EventParticipantController extends Controller
{

    // Affiche la liste des participants confirmés à un événement spécifique.
     public function index($eventId)
    {
        $event = Events::with(['reservation.user.profile'])->findOrFail($eventId);

        // Seuls les utilisateurs confirmés sont considérés comme "participants"
        $participants = $event->reservation()
            ->where('status', 'confirmed') // ou 'paid' selon votre logique
            ->with('user.profile')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                    'profile' => $r->user->profile ?? null,
                    'nbr_place' => $r->nbr_place,
                ];
            });

        return response()->json($participants);
    }


}
