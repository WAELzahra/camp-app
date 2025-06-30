<?php

namespace App\Http\Controllers\Event;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\EventInterest;
use App\Http\Requests\EventInterestRequest;
use App\Http\Controllers\Controller;
class EventInterestController extends Controller
{
    // Toggle event interest
public function toggleInterest($eventId)
{
    if (!Auth::check()) {
        return response()->json(['message' => 'Vous devez être connecté pour marquer un événement comme intéressant.'], 401);
    }

    $user = Auth::user();

    // Recherche de l'événement sans fail
    $event = Events::find($eventId);

    if (!$event) {
        return response()->json(['message' => 'Événement non trouvé.'], 404);
    }

    if ($user->interestedEvents()->where('event_id', $eventId)->exists()) {
        $user->interestedEvents()->detach($eventId);

        return response()->json([
            'message' => 'Événement retiré des intérêts.',
            'interested' => false
        ]);
    } else {
        $user->interestedEvents()->attach($eventId);

        return response()->json([
            'message' => 'Événement ajouté aux intérêts.',
            'interested' => true
        ]);
    }
}



}
