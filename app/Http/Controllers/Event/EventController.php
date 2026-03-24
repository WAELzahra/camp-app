<?php

namespace App\Http\Controllers\Event;

use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\CampingZone;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Mail\NewEventNotification;
use App\Models\Reservations_events;
use App\Models\ProfileGroupe;
use App\Models\User;
use App\Models\FollowersGroupe;
use App\Models\Profile;
use App\Models\Circuit;
use Illuminate\Validation\Rule;
use App\Notifications\EventInviteNotification;

class EventController extends Controller
{
    // ==================== PUBLIC ROUTES (No auth required) ====================
    /**
     * Get events by group ID (Public - no auth required)
     * This allows anyone to view events created by a specific group
     */
    public function getGroupEvents($groupId, Request $request)
    {
        $query = Events::where('group_id', $groupId)
                    ->with(['group', 'photos']);

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $events = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get all events created by the authenticated group (Authenticated)
     * This is for groups to see their own events (including pending ones)
     */
    public function myEvents(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Check if user is group (role_id = 2)
        if ($user->role_id !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'Only groups can access their events',
                'data' => []
            ], 200);
        }

        try {
            $query = Events::where('group_id', $user->id)
                        ->with(['group', 'photos']);

            // Optional filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('event_type')) {
                $query->where('event_type', $request->event_type);
            }

            // Sort options
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $events = $query->paginate($request->get('per_page', 15));

            // Transform the photos to include URLs (though the model accessor will handle it)
            $events->getCollection()->transform(function ($event) {
                // Add a cover_image property for easy access
                if ($event->photos && $event->photos->isNotEmpty()) {
                    $coverPhoto = $event->photos->firstWhere('is_cover', true);
                    if ($coverPhoto) {
                        $event->cover_image = $coverPhoto->url; // This will use the accessor
                    } else {
                        $event->cover_image = $event->photos->first()->url; // This will use the accessor
                    }
                }
                return $event;
            });

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events: ' . $e->getMessage(),
                'data' => ['data' => []]
            ], 500);
        }
    }
    /**
     * List all active events with filters (Public)
     */
    public function index(Request $request)
    {
        $query = Events::with(['group', 'photos'])  
                      ->where('is_active', true)
                      ->where('status', 'scheduled');
 
        // Search by title
        if ($request->has('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }
 
        // Filter by event type
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }
 
        // Filter by destination (departure or arrival city)
        if ($request->has('destination')) {
            $destination = $request->destination;
            $query->where(function($q) use ($destination) {
                $q->where('departure_city', 'like', '%' . $destination . '%')
                  ->orWhere('arrival_city', 'like', '%' . $destination . '%')
                  ->orWhere('address', 'like', '%' . $destination . '%');
            });
        }
 
        // Price range filters
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
 
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
 
        // Date filters
        if ($request->has('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }
 
        if ($request->has('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }
 
        // Sort options
        $sortBy = $request->get('sort_by', 'start_date');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);
 
        $events = $query->paginate($request->get('per_page', 15));
 
        // Add cover_image to each event for convenience
        $events->getCollection()->transform(function ($event) {
            if ($event->photos && $event->photos->isNotEmpty()) {
                $cover = $event->photos->firstWhere('is_cover', true) ?? $event->photos->first();
                $event->cover_image = $cover->url ?? (
                    $cover->path_to_img
                        ? url('storage/' . $cover->path_to_img)
                        : null
                );
            }
            return $event;
        });
 
        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }
    /**
     * Get public event details (Public)
     */
    public function getEventDetails($id)
    {
        $event = Events::where('is_active', true)
                      ->with(['group', 'photos'])
                      ->find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $event
        ]);
    }

    /**
     * Search events by keyword (Public)
     */
    public function search(Request $request)
    {
        $keyword = trim($request->input('q'));

        if (!$keyword) {
            return response()->json(['message' => 'Please enter a search keyword'], 400);
        }

        $events = Events::where('is_active', true)
            ->where('status', 'scheduled')
            ->where(function ($query) use ($keyword) {
                $query->where('title', 'like', "%$keyword%")
                      ->orWhere('description', 'like', "%$keyword%")
                      ->orWhere('departure_city', 'like', "%$keyword%")
                      ->orWhere('arrival_city', 'like', "%$keyword%")
                      ->orWhereJsonContains('tags', $keyword);
            })
            ->orderBy('start_date', 'asc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get event share links (Public)
     */
    public function getEventShareLinks($id)
    {
        $event = Events::find($id);

        if (!$event || !$event->is_active) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $url = url('/events/' . $event->id);

        $shareLinks = [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url),
            'whatsapp' => 'https://api.whatsapp.com/send?text=' . urlencode("Check out this event: " . $url),
            'telegram' => 'https://t.me/share/url?url=' . urlencode($url) . '&text=' . urlencode('Check out this amazing event!'),
            'twitter' => 'https://twitter.com/intent/tweet?url=' . urlencode($url) . '&text=' . urlencode($event->title),
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($url),
        ];

        return response()->json([
            'success' => true,
            'data' => $shareLinks
        ]);
    }

    /**
     * Get event copy link (Public)
     */
    public function getEventCopyLink($id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $publicLink = url("/events/" . $event->id);

        return response()->json([
            'success' => true,
            'data' => ['link' => $publicLink]
        ]);
    }

    // ==================== AUTHENTICATED ROUTES (User must be logged in) ====================

    /**
     * Get detailed event info (Authenticated users only)
     */
    public function show($id)
    {
        $event = Events::with(['group', 'photos', 'feedbacks' => function($q) {
            $q->where('status', 'approved');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $event
        ]);
    }

    /**
     * Get nearby events (Authenticated users)
     */
    public function notifyNearbyEvents(Request $request, $userId)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:100',
        ]);

        $user = User::findOrFail($userId);
        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius ?? 10;

        // Find nearby events using Haversine formula
        $events = Events::where('is_active', true)
            ->where('status', 'scheduled')
            ->selectRaw("*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
            ->having('distance', '<=', $radius)
            ->orderBy('distance', 'asc')
            ->get();

        // Find nearby camping zones (if you have that model)
        $zones = [];
        if (class_exists('App\Models\CampingZone')) {
            $zones = CampingZone::where('status', true)
                ->where('is_closed', false)
                ->selectRaw("*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance', 'asc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events,
                'camping_zones' => $zones,
            ]
        ]);
    }

    // ==================== GROUP ONLY ROUTES (Group role required) ====================

    /**
     * Create a new event (Groups only)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Check if user is group (role_id = 2)
        if ($user->role_id !== 2) {
            return response()->json(['message' => 'Only groups can create events.'], 403);
        }

        $validated = $request->validate([
            // Event fields
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_type' => 'required|in:camping,hiking,voyage',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'capacity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            
            // Camping specific fields
            'camping_duration' => 'nullable|integer|min:1',
            'camping_gear' => 'nullable|string',
            'is_group_travel' => 'sometimes|boolean',
            
            // Trip/Voyage fields
            'departure_city' => 'nullable|string',
            'arrival_city' => 'nullable|string',
            'departure_time' => 'nullable|date_format:H:i',
            'estimated_arrival_time' => 'nullable|date_format:H:i',
            'bus_company' => 'nullable|string',
            'bus_number' => 'nullable|string',
            'city_stops' => 'nullable|array',
            'city_stops.*.city' => 'required|string',
            'city_stops.*.arrival_time' => 'nullable|date_format:H:i',
            'city_stops.*.departure_time' => 'nullable|date_format:H:i',
            
            // Location fields
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string',
            
            // Hiking specific fields
            'difficulty' => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration' => 'nullable|numeric|min:0.5|max:48',
            'elevation_gain' => 'nullable|integer|min:0|max:8000',
            
            // Tags
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            
            // Images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:25600', // 25MB max
            'cover_image_index' => 'nullable|integer|min:0',
        ]);

        // Create the event
        $event = Events::create([
            'group_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_type' => $validated['event_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'capacity' => $validated['capacity'],
            'price' => $validated['price'],
            'remaining_spots' => $validated['capacity'],
            'camping_duration' => $validated['camping_duration'] ?? null,
            'camping_gear' => $validated['camping_gear'] ?? null,
            'is_group_travel' => $request->boolean('is_group_travel', false),
            'departure_city' => $validated['departure_city'] ?? null,
            'arrival_city' => $validated['arrival_city'] ?? null,
            'departure_time' => $validated['departure_time'] ?? null,
            'estimated_arrival_time' => $validated['estimated_arrival_time'] ?? null,
            'bus_company' => $validated['bus_company'] ?? null,
            'bus_number' => $validated['bus_number'] ?? null,
            'city_stops' => isset($validated['city_stops']) ? json_encode($validated['city_stops']) : null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'address' => $validated['address'] ?? null,
            'difficulty' => $validated['difficulty'] ?? null,
            'hiking_duration' => $validated['hiking_duration'] ?? null,
            'elevation_gain' => $validated['elevation_gain'] ?? null,
            'tags' => isset($validated['tags']) ? json_encode($validated['tags']) : null,
            'status' => 'pending',
            'is_active' => false,
        ]);

        // Handle image uploads
        if ($request->hasFile('images')) {
            $coverIndex = $request->input('cover_image_index');
            
            foreach ($request->file('images') as $index => $image) {
                // Store the image
                $path = $image->store('events/' . $event->id, 'public');
                
                // Determine if this is the cover image
                $isCover = ($coverIndex !== null && (int)$coverIndex === $index);
                
                // Create photo record
                Photo::create([
                    'path_to_img' => $path,
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                    'is_cover' => $isCover,
                    'order' => $index,
                ]);
            }
        }

        // Notify followers
        $this->notifyFollowers($user->id, $event);

        // Load the photos relationship
        $event->load('photos');

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully. Waiting for admin approval.',
            'data' => $event
        ], 201);
    }
   /**
     * Send event invitations to a list of users (Group owner only)
     *
     * POST /api/events/{id}/invite
     * Body: { "user_ids": [1, 2, 3], "message": "optional custom message" }
     */
    public function sendInvites(Request $request, $id)
    {
        $sender = Auth::user();

        // Must be a group
        if ($sender->role_id !== 2) {
            return response()->json(['message' => 'Only groups can invite participants.'], 403);
        }

        $event = Events::where('id', $id)
                       ->where('group_id', $sender->id)
                       ->firstOrFail();

        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'message'    => 'nullable|string|max:500',
        ]);

        $senderName = trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? ''))
                   ?: $sender->name
                   ?: 'A group';

        $eventPayload = [
            'id'         => $event->id,
            'title'      => $event->title,
            'start_date' => $event->start_date,
            'price'      => $event->price,
            'event_type' => $event->event_type,
        ];

        $senderPayload = [
            'id'   => $sender->id,
            'name' => $senderName,
        ];

        $customMessage = $request->input('message', '');

        $notified = 0;
        $skipped  = 0;

        foreach ($request->user_ids as $userId) {
            // Don't notify yourself
            if ($userId == $sender->id) {
                $skipped++;
                continue;
            }

            $recipient = User::find($userId);
            if (!$recipient) {
                $skipped++;
                continue;
            }

            try {
                $recipient->notify(
                    new EventInviteNotification($eventPayload, $senderPayload, $customMessage)
                );
                $notified++;
            } catch (\Exception $e) {
                \Log::error("Failed to notify user {$userId}: " . $e->getMessage());
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$notified} invitation(s) sent successfully.",
            'data'    => [
                'notified' => $notified,
                'skipped'  => $skipped,
            ],
        ]);
    }
    /**
     * Update an event (Group owner only)
     */
   public function update(Request $request, $id)
    {
        $user = Auth::user();

        $event = Events::where('id', $id)
                       ->where('group_id', $user->id)
                       ->firstOrFail();

        // Only allow updates if event is still pending or scheduled
        if (!in_array($event->status, ['pending', 'scheduled'])) {
            return response()->json([
                'message' => 'Cannot update event that has already started or finished'
            ], 403);
        }

        // ── Pre-process city_stops ────────────────────────────────────────────
        // The frontend may send it as a JSON string (old behaviour) or as a
        // real array via FormData array-notation (new behaviour).  Normalise to
        // an array before validation so the `array` rule always passes.
        if ($request->has('city_stops') && is_string($request->city_stops)) {
            $decoded = json_decode($request->city_stops, true);
            if (is_array($decoded)) {
                $request->merge(['city_stops' => $decoded]);
            }
        }

        // Same for tags
        if ($request->has('tags') && is_string($request->tags)) {
            $decoded = json_decode($request->tags, true);
            if (is_array($decoded)) {
                $request->merge(['tags' => $decoded]);
            }
        }

        $validated = $request->validate([
            'title'                   => 'sometimes|string|max:255',
            'description'             => 'nullable|string',
            'start_date'              => 'sometimes|date',
            'end_date'                => 'sometimes|date|after_or_equal:start_date',
            'capacity'                => 'sometimes|integer|min:1',
            'price'                   => 'sometimes|numeric|min:0',

            // Camping
            'camping_duration'        => 'nullable|integer|min:1',
            'camping_gear'            => 'nullable|string',
            'is_group_travel'         => 'sometimes|boolean',

            // Transport / voyage
            'departure_city'          => 'nullable|string',
            'arrival_city'            => 'nullable|string',
            'departure_time'          => 'nullable|date_format:H:i',
            // ↓ Removed `after:departure_time` — it fails when departure_time
            //   is absent from the request. A simple format check is enough.
            'estimated_arrival_time'  => 'nullable|date_format:H:i',
            'bus_company'             => 'nullable|string',
            'bus_number'              => 'nullable|string',

            // city_stops — accepts array (FormData notation) or omitted
            'city_stops'              => 'nullable|array',
            'city_stops.*.city'       => 'required_with:city_stops|string',
            'city_stops.*.arrival_time'   => 'nullable|date_format:H:i',
            'city_stops.*.departure_time' => 'nullable|date_format:H:i',

            // Location
            'latitude'                => 'nullable|numeric|between:-90,90',
            'longitude'               => 'nullable|numeric|between:-180,180',
            'address'                 => 'nullable|string',

            // Hiking
            'difficulty'              => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration'         => 'nullable|numeric|min:0.5|max:48',
            'elevation_gain'          => 'nullable|integer|min:0|max:8000',

            // Tags
            'tags'                    => 'nullable|array',
            'tags.*'                  => 'string',

            // Image management
            'new_images'              => 'nullable|array',
            'new_images.*'            => 'image|mimes:jpeg,png,jpg,gif|max:25600',
            'cover_image_index'       => 'nullable|integer|min:0',
            'delete_images'           => 'nullable|array',
            'delete_images.*'         => 'integer|exists:photos,id',
        ]);

        // ── Apply scalar field updates ────────────────────────────────────────
        $skip = ['new_images', 'cover_image_index', 'delete_images', 'tags', 'city_stops', 'is_group_travel'];

        foreach ($validated as $key => $value) {
            if (in_array($key, $skip)) continue;
            $event->$key = $value;
        }

        // is_group_travel — use boolean() so "0"/"1"/true/false all work
        if ($request->has('is_group_travel')) {
            $event->is_group_travel = $request->boolean('is_group_travel');
        }

        // JSON-encode tags and city_stops for storage
        if (array_key_exists('tags', $validated)) {
            $event->tags = $validated['tags'] ? json_encode($validated['tags']) : null;
        }

        if (array_key_exists('city_stops', $validated)) {
            $event->city_stops = $validated['city_stops'] ? json_encode($validated['city_stops']) : null;
        }

        // ── Adjust remaining_spots when capacity changes ──────────────────────
        if (isset($validated['capacity']) && $validated['capacity'] != $event->getOriginal('capacity')) {
            $bookedSpots = $event->getOriginal('capacity') - $event->getOriginal('remaining_spots');
            $event->remaining_spots = $validated['capacity'] - $bookedSpots;
        }

        $event->save();

        // ── Delete images ─────────────────────────────────────────────────────
        if ($request->has('delete_images')) {
            $photos = Photo::whereIn('id', $request->input('delete_images'))
                           ->where('event_id', $event->id)
                           ->get();

            foreach ($photos as $photo) {
                Storage::disk('public')->delete($photo->path_to_img);
                $photo->delete();
            }
        }

        // ── Upload new images ─────────────────────────────────────────────────
        if ($request->hasFile('new_images')) {
            $coverIndex       = $request->input('cover_image_index');
            $currentPhotoCount = $event->photos()->count();

            foreach ($request->file('new_images') as $index => $image) {
                $path    = $image->store('events/' . $event->id, 'public');
                $isCover = ($coverIndex !== null && (int) $coverIndex === $index);

                Photo::create([
                    'path_to_img' => $path,
                    'user_id'     => $user->id,
                    'event_id'    => $event->id,
                    'is_cover'    => $isCover,
                    'order'       => $currentPhotoCount + $index,
                ]);
            }
        }

        // ── Update cover image (no new files) ─────────────────────────────────
        if ($request->has('cover_image_index') && !$request->hasFile('new_images')) {
            $coverIndex = (int) $request->input('cover_image_index');
            $photos     = $event->photos()->orderBy('order')->get();

            if ($photos->isNotEmpty() && $coverIndex < $photos->count()) {
                $event->photos()->update(['is_cover' => false]);
                $photos[$coverIndex]->update(['is_cover' => true]);
            }
        }

        $event->load('photos');

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data'    => $event,
        ]);
    }
    /**
     * Delete an event (Group owner or Admin)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        $event = Events::where('id', $id)->firstOrFail();
        
        // Check if user is group owner or admin
        if ($event->group_id !== $user->id && $user->role_id !== 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if event has any confirmed reservations
        if ($event->reservation()->whereIn('status', ['confirmée', 'en_attente_paiement'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete event with active reservations'
            ], 403);
        }

        // Delete associated photos from storage
        foreach ($event->photos as $photo) {
            Storage::disk('public')->delete($photo->path_to_img);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }

    /**
     * Get event participants (Group owner only)
     */
    public function participants($id, Request $request)
    {
        $event = Events::findOrFail($id);

        $status = $request->query('status');
        $query = Reservations_events::with('user')->where('event_id', $id);

        if ($status) {
            $query->where('status', $status);
        }

        $participants = $query->get();

        return response()->json([
            'success' => true,
            'data' => $participants
        ]);
    }
    /**
     * Update participant status (Group owner only)
     */
    public function updateStatus($id, Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'en_attente_paiement',
                    'confirmée',
                    'en_attente_validation',
                    'refusée',
                    'annulée_par_utilisateur',
                    'annulée_par_organisateur',
                    'remboursement_en_attente',
                    'remboursée_partielle',
                    'remboursée_totale',
                ]),
            ],
        ]);

        $reservation = Reservations_events::findOrFail($id);
        
        $event = Events::where('id', $reservation->event_id)
                      ->where('group_id', $user->id)
                      ->firstOrFail();

        $reservation->status = $request->status;
        $reservation->save();

        // If reservation is confirmed, decrease available spots
        if ($request->status === 'confirmée') {
            $event->decrement('remaining_spots', $reservation->nbr_place);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    }

    // ==================== ADMIN ONLY ROUTES ====================

    /**
     * Approve or reject event (Admin only)
     */
    public function moderate(Request $request, $id)
    {
        $user = Auth::user();
        
        // Check if admin (role_id = 6)
        if ($user->role_id !== 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string'
        ]);

        $event = Events::findOrFail($id);

        if ($request->action === 'approve') {
            $event->is_active = true;
            $event->status = 'scheduled';
            $message = 'Event approved successfully';
        } else {
            $event->status = 'rejected';
            $event->is_active = false;
            $event->rejection_reason = $request->rejection_reason;
            $message = 'Event rejected';
        }

        $event->save();

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Get all events for admin management
     */
    public function adminIndex(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role_id !== 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Events::with('group');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        $events = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Notify followers about new event
     */
    private function notifyFollowers($groupId, $event)
    {
        try {
            $profile = Profile::where('user_id', $groupId)->first();
            if (!$profile) return;

            $profileGroupe = ProfileGroupe::where('profile_id', $profile->id)->first();
            if (!$profileGroupe) return;

            $followersUserIds = FollowersGroupe::where('groupe_id', $profileGroupe->id)
                                              ->pluck('user_id')
                                              ->toArray();
            
            $users = User::whereIn('id', $followersUserIds)->get();

            foreach ($users as $user) {
                if ($user->email) {
                    Mail::to($user->email)->send(new NewEventNotification($event, $user->name));
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to send event notifications: ' . $e->getMessage());
        }
    }
}