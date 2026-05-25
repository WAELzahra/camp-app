<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\EventService;
use Illuminate\Http\Request;
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
    public function store(Request $request, $eventId)
    {
        $event = Events::findOrFail($eventId);

        if ($event->group_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'price'        => 'required|numeric|min:0',
            'pricing_unit' => 'required|string|max:100',
            'max_quantity' => 'nullable|integer|min:1',
        ]);

        $service = EventService::create(array_merge($validated, ['event_id' => $event->id]));

        return response()->json(['success' => true, 'data' => $service], 201);
    }

    /**
     * Update an existing service (organizer only).
     */
    public function update(Request $request, $eventId, $serviceId)
    {
        $event = Events::findOrFail($eventId);

        if ($event->group_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $service = EventService::where('event_id', $event->id)->findOrFail($serviceId);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'price'        => 'sometimes|numeric|min:0',
            'pricing_unit' => 'sometimes|string|max:100',
            'max_quantity' => 'nullable|integer|min:1',
            'is_active'    => 'sometimes|boolean',
        ]);

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
