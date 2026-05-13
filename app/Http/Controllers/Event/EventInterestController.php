<?php

namespace App\Http\Controllers\Event;

use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class EventInterestController extends Controller
{
    // POST /api/events/{id}/interest  — toggle interest
    public function toggleInterest($eventId)
    {
        $user  = Auth::user();
        $event = Events::find($eventId);

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        if ($user->interestedEvents()->where('event_id', $eventId)->exists()) {
            $user->interestedEvents()->detach($eventId);
            return response()->json(['interested' => false]);
        }

        $user->interestedEvents()->attach($eventId);
        return response()->json(['interested' => true]);
    }

    // GET /api/events/{id}/interest/check  — check if user is interested
    public function check($eventId)
    {
        $interested = Auth::user()
            ->interestedEvents()
            ->where('event_id', $eventId)
            ->exists();

        return response()->json(['interested' => $interested]);
    }

    // GET /api/events/my-interests  — list all events user is interested in
    public function myInterests()
    {
        $events = Auth::user()
            ->interestedEvents()
            ->select('events.id', 'events.title', 'events.start_date', 'events.end_date',
                     'events.price', 'events.event_type', 'events.city', 'events.zone',
                     'events.departure_city', 'events.arrival_city', 'events.capacity')
            ->orderBy('events.start_date')
            ->get();

        return response()->json(['data' => $events]);
    }

    // GET /api/events/my-interest-ids  — just IDs (for batch UI state)
    public function myInterestIds()
    {
        $ids = Auth::user()
            ->interestedEvents()
            ->pluck('events.id')
            ->values();

        return response()->json(['ids' => $ids]);
    }
}
