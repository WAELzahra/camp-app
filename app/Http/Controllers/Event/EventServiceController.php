<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventServiceRequest;
use App\Http\Requests\Event\UpdateEventServiceRequest;
use App\Models\Events;
use App\Models\EventService;
use Illuminate\Support\Facades\Auth;

class EventServiceController extends Controller
{
    /**
     * List all active services for an event (public).
     */
    public function index($eventId)
    {
        $event = Events::findOrFail($eventId);

        $services = EventService::where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $services]);
    }

    /**
     * Create a new service for an event (organizer only).
     */
    public function store(StoreEventServiceRequest $request, $eventId)
    {
        $event = Events::findOrFail($eventId);

        if ($event->group_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validated();

        $service = EventService::create(array_merge($validated, ['event_id' => $event->id]));

        return response()->json(['success' => true, 'data' => $service], 201);
    }

    /**
     * Update an existing service (organizer only).
     */
    public function update(UpdateEventServiceRequest $request, $eventId, $serviceId)
    {
        $event = Events::findOrFail($eventId);

        if ($event->group_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $service = EventService::where('event_id', $event->id)->findOrFail($serviceId);

        $validated = $request->validated();

        $service->update($validated);

        return response()->json(['success' => true, 'data' => $service]);
    }

    /**
     * Delete a service (organizer only).
     */
    public function destroy($eventId, $serviceId)
    {
        $event = Events::findOrFail($eventId);

        if ($event->group_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $service = EventService::where('event_id', $event->id)->findOrFail($serviceId);
        $service->delete();

        return response()->json(['success' => true, 'message' => 'Service deleted.']);
    }
}
