<?php

namespace App\Http\Controllers\Event;

use App\Events\NewNotificationCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\ApproveRejectEventRequest;
use App\Http\Requests\Event\InviteToEventRequest;
use App\Http\Requests\Event\NotifyNearbyEventsRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventReservationStatusRequest;
use App\Http\Resources\EventReservationResource;
use App\Http\Resources\EventResource;
use App\Mail\NewEventNotification;
use App\Models\Balance;
use App\Models\CampingZone;
use App\Models\EventReservationService;
use App\Models\Events;
use App\Models\FollowersGroupe;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\ProfileGroupe;
use App\Models\Reservations_events;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\EventInviteNotification;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
            ->with(['group', 'group.acceptedSupplierLink.supplier:id,uuid,first_name,last_name,avatar', 'photos']);

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
        $events->getCollection()->transform(function ($event) {
            $event->accepted_supplier = $event->group?->acceptedSupplierLink?->supplier ?? null;

            return $event;
        });

        return response()->json([
            'success' => true,
            'data' => $events,
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
                'data' => [],
            ], 200);
        }

        try {
            $query = Events::where('group_id', $user->id)
                ->with(['group', 'group.acceptedSupplierLink.supplier:id,uuid,first_name,last_name,avatar', 'photos']);

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
                'data' => EventResource::collection($events),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events: '.$e->getMessage(),
                'data' => ['data' => []],
            ], 500);
        }
    }

    /**
     * List all events (Public) - returns ALL events for client-side filtering
     */
    public function index(Request $request)
    {
        $query = Events::with(['group', 'group.acceptedSupplierLink.supplier:id,uuid,first_name,last_name,avatar', 'photos'])
            ->orderByRaw('ISNULL(start_date) ASC, start_date ASC'); // NULL start_date (custom) at end

        // Optional: Basic search - keep this as it's efficient
        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('departure_city', 'like', "%{$search}%")
                    ->orWhere('arrival_city', 'like', "%{$search}%");
            });
        }

        // Optional: Filter by event type (keep for basic filtering)
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Return more events for client-side filtering
        $perPage = $request->get('per_page', 500);
        $events = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events),
        ]);
    }

    /**
     * Get public event details (Public)
     */
    public function getEventDetails($id)
    {
        // No is_active filter — finished/past events must stay viewable.
        $with = ['group', 'group.acceptedSupplierLink.supplier:id,uuid,first_name,last_name,avatar', 'photos'];
        if (is_numeric($id)) {
            $event = Events::with($with)->find($id);
        } else {
            $event = Events::with($with)->where('slug', $id)->first();
            if (!$event && ($numId = static::decodeBase64Id($id))) {
                $event = Events::with($with)->find($numId);
            }
        }

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
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

        $events = Events::with(['group', 'group.acceptedSupplierLink.supplier:id,uuid,first_name,last_name,avatar', 'photos'])
            ->where('is_active', true)
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
            'data' => EventResource::collection($events),
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

        $url = url('/events/'.$event->id);

        $shareLinks = [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.urlencode($url),
            'whatsapp' => 'https://api.whatsapp.com/send?text='.urlencode('Check out this event: '.$url),
            'telegram' => 'https://t.me/share/url?url='.urlencode($url).'&text='.urlencode('Check out this amazing event!'),
            'twitter' => 'https://twitter.com/intent/tweet?url='.urlencode($url).'&text='.urlencode($event->title),
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url='.urlencode($url),
        ];

        return response()->json([
            'success' => true,
            'data' => $shareLinks,
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

        $publicLink = url('/events/'.$event->id);

        return response()->json([
            'success' => true,
            'data' => ['link' => $publicLink],
        ]);
    }

    // ==================== AUTHENTICATED ROUTES (User must be logged in) ====================

    /**
     * Get detailed event info (Authenticated users only)
     */
    public function show($id)
    {
        $event = Events::with(['group', 'photos', 'feedbacks' => function ($q) {
            $q->where('status', 'approved');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * Get nearby events (Authenticated users)
     */
    public function notifyNearbyEvents(NotifyNearbyEventsRequest $request, $userId)
    {

        $user = User::findOrFail($userId);
        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius ?? 10;

        // Find nearby events using Haversine formula
        $events = Events::where('is_active', true)
            ->where('status', 'scheduled')
            ->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$lat, $lng, $lat])
            ->having('distance', '<=', $radius)
            ->orderBy('distance', 'asc')
            ->get();

        // Find nearby camping zones (if you have that model)
        $zones = [];
        if (class_exists('App\Models\CampingZone')) {
            $zones = CampingZone::where('status', true)
                ->where('is_closed', false)
                ->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$lat, $lng, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance', 'asc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events,
                'camping_zones' => $zones,
            ],
        ]);
    }

    // ==================== GROUP ONLY ROUTES (Group role required) ====================

    /**
     * Create a new event (Groups only)
     */
    public function store(StoreEventRequest $request)
    {
        $user = Auth::user();

        // Check if user is group (role_id = 2)
        if ($user->role_id !== 2) {
            return response()->json(['message' => 'Only groups can create events.'], 403);
        }

        $validated = $request->validated();

        // Create the event
        $event = Events::create([
            'group_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_type' => $validated['event_type'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'price' => $validated['price'],
            'remaining_spots' => $validated['capacity'] ?? null,
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
                $path = $image->store('events/'.$event->id, 'public');

                // Determine if this is the cover image
                $isCover = ($coverIndex !== null && (int) $coverIndex === $index);

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

        // Handle services sent as JSON string in FormData
        if ($request->filled('services')) {
            $servicesData = is_string($request->services)
                ? json_decode($request->services, true)
                : $request->services;

            if (is_array($servicesData)) {
                foreach ($servicesData as $svc) {
                    if (!empty($svc['name']) && isset($svc['price'])) {
                        \App\Models\EventService::create([
                            'event_id' => $event->id,
                            'name' => $svc['name'],
                            'description' => $svc['description'] ?? null,
                            'price' => (float) $svc['price'],
                            'pricing_unit' => $svc['pricing_unit'] ?? 'per person',
                            'max_quantity' => isset($svc['max_quantity']) ? (int) $svc['max_quantity'] : null,
                            'is_active' => true,
                        ]);
                    }
                }
            }
        }

        // Notify followers
        $this->notifyFollowers($user->id, $event);

        // Load the photos relationship
        $event->load('photos', 'services');

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully. Waiting for admin approval.',
            'data' => $event,
        ], 201);
    }

    /**
     * Send event invitations to a list of users (Group owner only)
     *
     * POST /api/events/{id}/invite
     * Body: { "user_ids": [1, 2, 3], "message": "optional custom message" }
     */
    public function sendInvites(InviteToEventRequest $request, $id)
    {
        $sender = Auth::user();

        // Must be a group
        if ($sender->role_id !== 2) {
            return response()->json(['message' => 'Only groups can invite participants.'], 403);
        }

        $event = Events::where('id', $id)
            ->where('group_id', $sender->id)
            ->firstOrFail();

        $senderName = trim(($sender->first_name ?? '').' '.($sender->last_name ?? ''))
                   ?: $sender->name
                   ?: 'A group';

        $eventPayload = [
            'id' => $event->id,
            'title' => $event->title,
            'start_date' => $event->start_date,
            'price' => $event->price,
            'event_type' => $event->event_type,
        ];

        $senderPayload = [
            'id' => $sender->id,
            'name' => $senderName,
        ];

        $customMessage = $request->input('message', '');

        $notified = 0;
        $skipped = 0;

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

                // Broadcast via WebSocket so the notification appears in real time
                $dbNotification = $recipient->notifications()->latest()->first();
                if ($dbNotification) {
                    broadcast(new NewNotificationCreated(
                        userId: $recipient->id,
                        notificationId: $dbNotification->id,
                        title: "You're invited to {$event->title}",
                        content: $customMessage ?: "You have been invited to join \"{$event->title}\".",
                        type: 'event_invitation',
                        priority: 'medium',
                        actionUrl: "/event/{$event->id}/details",
                        actionText: 'View Event',
                        senderId: $sender->id,
                        createdAt: $dbNotification->created_at->toISOString(),
                    ));
                }
            } catch (\Exception $e) {
                \Log::error("Failed to notify user {$userId}: ".$e->getMessage());
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$notified} invitation(s) sent successfully.",
            'data' => [
                'notified' => $notified,
                'skipped' => $skipped,
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
                'message' => 'Cannot update event that has already started or finished',
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

        $validated = $request->validated();

        // ── Apply scalar field updates ────────────────────────────────────────
        $skip = ['new_images', 'cover_image_index', 'delete_images', 'tags', 'city_stops', 'is_group_travel'];

        foreach ($validated as $key => $value) {
            if (in_array($key, $skip)) {
                continue;
            }
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
            $coverIndex = $request->input('cover_image_index');
            $currentPhotoCount = $event->photos()->count();

            foreach ($request->file('new_images') as $index => $image) {
                $path = $image->store('events/'.$event->id, 'public');
                $isCover = ($coverIndex !== null && (int) $coverIndex === $index);

                Photo::create([
                    'path_to_img' => $path,
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                    'is_cover' => $isCover,
                    'order' => $currentPhotoCount + $index,
                ]);
            }
        }

        // ── Update cover image (no new files) ─────────────────────────────────
        if ($request->has('cover_image_index') && !$request->hasFile('new_images')) {
            $coverIndex = (int) $request->input('cover_image_index');
            $photos = $event->photos()->orderBy('order')->get();

            if ($photos->isNotEmpty() && $coverIndex < $photos->count()) {
                $event->photos()->update(['is_cover' => false]);
                $photos[$coverIndex]->update(['is_cover' => true]);
            }
        }

        $event->load('photos');

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $event,
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
                'message' => 'Cannot delete event with active reservations',
            ], 403);
        }

        // Delete associated photos from storage
        foreach ($event->photos as $photo) {
            Storage::disk('public')->delete($photo->path_to_img);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
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

        $participants = $query->with('services')->get();

        // Enrich each participant with commission fields using the group's custom rate
        $enriched = $participants->map(function ($p) use ($event) {
            $servicesTotal = round($p->services->sum('subtotal'), 2);
            $base = max(0, round(
                ($p->nbr_place * (float) $event->price) + $servicesTotal - (float) ($p->discount_amount ?? 0),
                2
            ));
            $calc = CommissionService::calculateForUser('group', $base, $event->group_id);
            $p->commission_rate = round($calc['rate'] * 100, 2);
            $p->commission_amount = $calc['commission'];
            $p->net_revenue = $calc['net_revenue'];
            $p->base_amount = $base;
            $p->services_total = $servicesTotal;

            return $p;
        });

        return response()->json([
            'success' => true,
            'data' => EventReservationResource::collection($enriched),
        ]);
    }

    /**
     * Update participant status (Group owner only)
     */
    public function updateStatus($id, UpdateEventReservationStatusRequest $request)
    {
        $user = Auth::user();

        $reservation = Reservations_events::findOrFail($id);

        $event = Events::where('id', $reservation->event_id)
            ->where('group_id', $user->id)
            ->firstOrFail();

        $newStatus = $request->status;
        $netBase = round(($reservation->nbr_place * $event->price) - ($reservation->discount_amount ?? 0), 2);
        $servicesTotal = round(EventReservationService::where('event_reservation_id', $reservation->id)->sum('subtotal'), 2);

        // ── Case 1: Group cancels a CONFIRMED (already paid) reservation → full refund ──
        if ($reservation->payment_method === 'wallet'
            && $reservation->status === 'confirmée'
            && $newStatus === 'annulée_par_organisateur') {

            DB::beginTransaction();
            try {
                $feeData = CommissionService::addServiceFee($netBase + $servicesTotal);
                $totalToPay = round($feeData['total'], 2);
                // Use stored net_amount for exact clawback (prevents mismatch if rate changed)
                $origTx = \App\Models\WalletTransaction::where('user_id', $reservation->group_id)
                    ->where('type', 'credit')
                    ->where('category', 'reservation_income')
                    ->where('reference_type', 'event_reservation')
                    ->where('reference_id', $reservation->id)
                    ->first();
                $netToClawback = $origTx
                    ? (float) $origTx->net_amount
                    : CommissionService::calculateForUser('group', $netBase + $servicesTotal, $reservation->group_id)['net_revenue'];

                // Clawback from organizer exactly what they received
                Balance::forUser($reservation->group_id)->debiter($netToClawback);
                WalletTransaction::logDebit(
                    $reservation->group_id, 'refund_out', $netToClawback,
                    'event_reservation', $reservation->id,
                    "Remboursement annulation organisateur : {$event->title}",
                    $reservation->user_id
                );

                // Full refund to camper
                Balance::forUser($reservation->user_id)->crediter($totalToPay);
                WalletTransaction::logCredit(
                    $reservation->user_id, 'refund_in',
                    $totalToPay, 0, 0, $totalToPay,
                    'event_reservation', $reservation->id,
                    "Remboursement annulation organisateur : {$event->title}",
                    $reservation->group_id
                );

                // Restore spots
                $event->increment('remaining_spots', $reservation->nbr_place);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Event organizer cancellation refund failed: '.$e->getMessage());

                return response()->json(['success' => false, 'message' => 'Erreur lors du remboursement.'], 500);
            }
        }

        // ── Case 2: Group acts on a PENDING reservation ──────────────────────────
        // Funds were already escrowed (locked) at reservation creation — do NOT debit again.
        if ($reservation->payment_method === 'wallet' && $reservation->status === 'en_attente_validation') {

            if ($newStatus === 'confirmée') {
                // Release escrow and pay organizer — camper was already charged at booking
                DB::beginTransaction();
                try {
                    $platformFeeAmt = round((float) ($reservation->platform_fee_amount ?? 0), 2);
                    // Release the full escrowed amount: base + services + stored platform fee
                    $totalToRelease = round($netBase + $servicesTotal + $platformFeeAmt, 2);
                    Balance::forUser($reservation->user_id)->releaseEscrow($totalToRelease);

                    // Credit organizer for event base + services revenue, minus commission
                    $orgBase = $netBase + $servicesTotal;
                    $orgCalc = CommissionService::calculateForUser('group', $orgBase, $reservation->group_id);
                    Balance::forUser($reservation->group_id)->crediter($orgCalc['net_revenue']);
                    WalletTransaction::logCredit(
                        $reservation->group_id, 'reservation_income',
                        $orgBase,
                        round($orgCalc['rate'] * 100, 2),
                        $orgCalc['commission'],
                        $orgCalc['net_revenue'],
                        'event_reservation', $reservation->id,
                        "Revenu réservation événement : {$event->title}",
                        $reservation->user_id
                    );

                    // Log admin wallet revenue
                    if ($platformFeeAmt > 0) {
                        \App\Models\AdminWalletTransaction::log(
                            'platform_fee', $platformFeeAmt,
                            'event_reservation', $reservation->id,
                            "Platform fee — {$event->title} (réservation #{$reservation->id})",
                            $reservation->user_id
                        );
                    }
                    if ($orgCalc['commission'] > 0) {
                        \App\Models\AdminWalletTransaction::log(
                            'commission', $orgCalc['commission'],
                            'event_reservation', $reservation->id,
                            "Commission groupe — {$event->title} (réservation #{$reservation->id})",
                            $reservation->group_id
                        );
                    }

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    \Log::error('Event approval wallet payment failed: '.$e->getMessage());

                    return response()->json(['success' => false, 'message' => 'Erreur lors du paiement.'], 500);
                }

            } elseif (in_array($newStatus, ['refusée', 'annulée_par_organisateur'])) {
                // Refund escrowed funds — funds were locked at booking, return them now
                DB::beginTransaction();
                try {
                    $feeData = CommissionService::addServiceFee($netBase + $servicesTotal);
                    $totalToPay = round($feeData['total'], 2);
                    Balance::forUser($reservation->user_id)->refundEscrow($totalToPay);
                    WalletTransaction::logCredit(
                        $reservation->user_id, 'refund_in',
                        $totalToPay, 0, 0, $totalToPay,
                        'event_reservation', $reservation->id,
                        "Remboursement — réservation {$newStatus} : {$event->title}",
                        $reservation->group_id
                    );
                    $event->increment('remaining_spots', $reservation->nbr_place);
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    \Log::error('Event rejection escrow refund failed: '.$e->getMessage());

                    return response()->json(['success' => false, 'message' => 'Erreur lors du remboursement.'], 500);
                }
            }
        }

        $reservation->status = $newStatus;
        $reservation->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
        ]);
    }

    // ==================== ADMIN ONLY ROUTES ====================

    /**
     * Approve or reject event (Admin only)
     */
    public function moderate(ApproveRejectEventRequest $request, $id)
    {
        $user = Auth::user();

        // Check if admin (role_id = 6)
        if ($user->role_id !== 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

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
            'message' => $message,
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
            'data' => $events,
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
            if (!$profile) {
                return;
            }

            $profileGroupe = ProfileGroupe::where('profile_id', $profile->id)->first();
            if (!$profileGroupe) {
                return;
            }

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
            \Log::error('Failed to send event notifications: '.$e->getMessage());
        }
    }
}
