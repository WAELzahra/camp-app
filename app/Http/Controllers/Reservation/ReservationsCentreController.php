<?php

namespace App\Http\Controllers\Reservation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Reservations_centre;
use App\Models\ReservationServiceItem;
use App\Models\ProfileCentre;
use App\Models\Profile;
use App\Mail\ReservationCanceledByUser;
use App\Mail\CentreReservationConfirmed;
use App\Mail\ReservationRejected;
use App\Mail\NewReservationNotification;
use App\Mail\ReservationModifiedByCenter;
class ReservationsCentreController extends Controller
{
    /**
     * Display the list of reservations for a centre (with service items).
     */
    public function index(Request $request)
    {
        $idCentre = Auth::id();

        $query = Reservations_centre::where('centre_id', $idCentre);
        
        // Eager load relationships if requested
        if ($request->has('with')) {
            $with = explode(',', $request->with);
            foreach ($with as $relation) {
                if (in_array(trim($relation), ['serviceItems', 'serviceItems.service', 'serviceItems.service.category', 'user', 'centre'])) {
                    $query->with(trim($relation));
                }
            }
        }
        
        $reservations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Centre reservations retrieved successfully.',
            'reservations' => $reservations
        ], 200);
    }

    /**
     * Display the list of reservations that belong to the authenticated user (with service items).
     */
    public function index_user(Request $request)
    {
        $idUser = Auth::id();

        $query = Reservations_centre::where('user_id', $idUser);
        
        // Eager load relationships if requested
        if ($request->has('with')) {
            $with = explode(',', $request->with);
            foreach ($with as $relation) {
                if (in_array(trim($relation), ['serviceItems', 'serviceItems.service', 'serviceItems.service.category', 'user', 'centre'])) {
                    $query->with(trim($relation));
                }
            }
        }
        
        $reservations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'User reservations retrieved successfully.',
            'reservations' => $reservations
        ], 200);
    }

    /**
     * Display a specific reservation by its ID with service items.
     */
    public function show(int $idReservation)
    {
        $reservation = Reservations_centre::with([
            'serviceItems',
            'serviceItems.service',
            'serviceItems.service.category',
            'user',
            'centre'
        ])->find($idReservation);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found.'], 404);
        }

        return response()->json([
            'message' => 'Reservation retrieved successfully.',
            'reservation' => $reservation
        ], 200);
    }

    /**
     * Store a new reservation with multiple service items.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'centre_id' => 'required|exists:users,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'nbr_place' => 'required|integer|min:1',
            'note' => 'nullable|string',
            'service_items' => 'required|array|min:1',
            'service_items.*.profile_center_service_id' => 'required|exists:profile_center_services,id',
            'service_items.*.quantity' => 'required|integer|min:1',
            'service_items.*.unit_price' => 'required|numeric|min:0',
            'service_items.*.service_name' => 'required|string',
            'service_items.*.unit' => 'required|string',
        ], [
            'centre_id.required' => 'The centre ID is required.',
            'centre_id.exists' => 'The selected centre does not exist.',
            'date_debut.required' => 'Start date is required.',
            'date_fin.required' => 'End date is required.',
            'date_fin.after_or_equal' => 'The end date must be after or equal to the start date.',
            'nbr_place.required' => 'The number of places is required.',
            'service_items.required' => 'At least one service item is required.',
            'service_items.*.profile_center_service_id.required' => 'Service ID is required for each item.',
            'service_items.*.quantity.required' => 'Quantity is required for each service.',
        ]);

        $userId = Auth::id();

        // Verify the centre is valid
        $centreUser = User::where('id', $request->centre_id)
            ->where('role_id', 3)
            ->first();

        if (!$centreUser) {
            return response()->json([
                'message' => 'The selected centre_id does not belong to a user with the role "centre".'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Calculate total price
            $totalPrice = 0;
            foreach ($request->service_items as $item) {
                $subtotal = $item['unit_price'] * $item['quantity'];
                $totalPrice += $subtotal;
            }

            // Get service types for the reservation type field
            $serviceTypes = [];
            foreach ($request->service_items as $item) {
                // You might want to fetch the service to get its category
                $service = \App\Models\ProfileCenterService::find($item['profile_center_service_id']);
                if ($service && $service->category) {
                    $serviceTypes[] = $service->category->name;
                }
            }
            $type = !empty($serviceTypes) ? implode(', ', array_unique($serviceTypes)) : 'Multiple Services';

            // Create the main reservation
            $reservationCentre = Reservations_centre::create([
                'user_id' => $userId,
                'centre_id' => $request->centre_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'note' => $request->note,
                'type' => $type,
                'nbr_place' => $request->nbr_place,
                'status' => 'pending',
                'total_price' => $totalPrice,
                'service_count' => count($request->service_items)
            ]);

            // Create service items
            foreach ($request->service_items as $item) {
                $subtotal = $item['unit_price'] * $item['quantity'];
                
                ReservationServiceItem::create([
                    'reservation_id' => $reservationCentre->id,
                    'profile_center_service_id' => $item['profile_center_service_id'],
                    'service_name' => $item['service_name'],
                    'service_description' => $item['service_description'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                    'service_date_debut' => $item['service_date_debut'] ?? $request->date_debut,
                    'service_date_fin' => $item['service_date_fin'] ?? $request->date_fin,
                    'notes' => $item['notes'] ?? null,
                    'status' => 'pending'
                ]);
            }

            DB::commit();

            // Send notification email
            $user = Auth::user();
            if ($centreUser) {
                $reservationCentre->load(['serviceItems', 'user']);
                Mail::to($centreUser->email)->send(new NewReservationNotification($centreUser, $user, $reservationCentre));
            }

            return response()->json([
                'message' => 'Reservation created successfully.',
                'reservation' => $reservationCentre->load('serviceItems')
            ], 201);
            
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Conflict: Reservation already exists for this period.',
                ], 409);
            }
            
            return response()->json([
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
            
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a reservation and its service items.
     */
    public function update(Request $request, int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);
        
        // Check if user is authorized to update this reservation
        $userId = Auth::id();
        if ($reservation->user_id != $userId && $reservation->centre_id != $userId) {
            return response()->json([
                'message' => 'Unauthorized to update this reservation.'
            ], 403);
        }

        $validated = $request->validate([
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
            'nbr_place' => 'sometimes|integer|min:1',
            'note' => 'nullable|string',
            'service_items' => 'sometimes|array',
            'service_items.*.id' => 'nullable', 
            'service_items.*.service_id' => 'required|exists:profile_center_services,id', 
            'service_items.*.service_name' => 'sometimes|string',
            'service_items.*.quantity' => 'required|integer|min:1',
            'service_items.*.unit_price' => 'required|numeric|min:0',
            'service_items.*.unit' => 'sometimes|string',
            'service_items.*.notes' => 'nullable|string',
            'service_items.*.is_new' => 'sometimes|boolean', 
            'service_items.*.is_removed' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();

        try {
            // Update reservation basic info
            if ($request->has('date_debut')) {
                $reservation->date_debut = $request->date_debut;
            }
            if ($request->has('date_fin')) {
                $reservation->date_fin = $request->date_fin;
            }
            if ($request->has('nbr_place')) {
                $reservation->nbr_place = $request->nbr_place;
            }
            if ($request->has('note')) {
                $reservation->note = $request->note;
            }

            $totalPrice = 0;
            $activeServiceCount = 0;
            
            // Update service items if provided
            if ($request->has('service_items')) {
                foreach ($request->service_items as $itemData) {
                    // Handle removed items
                    if (isset($itemData['is_removed']) && $itemData['is_removed'] === true) {
                        if (isset($itemData['id']) && is_numeric($itemData['id'])) {
                            ReservationServiceItem::where('id', $itemData['id'])
                                ->where('reservation_id', $reservation->id)
                                ->delete();
                        }
                        continue;
                    }

                    // Handle new items
                    $isNewItem = isset($itemData['is_new']) && $itemData['is_new'] === true;
                    $hasTempId = isset($itemData['id']) && str_starts_with($itemData['id'], 'new-');
                    
                    if ($isNewItem || $hasTempId || !isset($itemData['id'])) {
                        // Create new service item
                        $newServiceItem = ReservationServiceItem::create([
                            'reservation_id' => $reservation->id,
                            'profile_center_service_id' => $itemData['service_id'],
                            'service_name' => $itemData['service_name'] ?? 'Service',
                            'service_description' => $itemData['service_description'] ?? null,
                            'unit_price' => $itemData['unit_price'] ?? 0,
                            'unit' => $itemData['unit'] ?? 'item',
                            'quantity' => $itemData['quantity'] ?? 1,
                            'notes' => $itemData['notes'] ?? null,
                            'subtotal' => ($itemData['unit_price'] ?? 0) * ($itemData['quantity'] ?? 1),
                            'status' => 'pending',
                            'service_date_debut' => $reservation->date_debut,
                            'service_date_fin' => $reservation->date_fin
                        ]);
                        
                        $totalPrice += $newServiceItem->subtotal;
                        $activeServiceCount++;
                        continue;
                    }

                    // Handle existing items
                    if (isset($itemData['id']) && is_numeric($itemData['id'])) {
                        $serviceItem = ReservationServiceItem::where('id', $itemData['id'])
                            ->where('reservation_id', $reservation->id)
                            ->first();

                        if (!$serviceItem) {
                            continue;
                        }

                        // Track changes
                        $hasChanges = false;
                        if (isset($itemData['quantity']) && $itemData['quantity'] != $serviceItem->quantity) {
                            $serviceItem->quantity = $itemData['quantity'];
                            $hasChanges = true;
                        }
                        
                        if (isset($itemData['unit_price']) && $itemData['unit_price'] != $serviceItem->unit_price) {
                            $serviceItem->unit_price = $itemData['unit_price'];
                            $hasChanges = true;
                        }
                        
                        if (isset($itemData['notes']) && $itemData['notes'] != $serviceItem->notes) {
                            $serviceItem->notes = $itemData['notes'];
                            $hasChanges = true;
                        }

                        if ($hasChanges) {
                            // Recalculate subtotal
                            $serviceItem->subtotal = $serviceItem->unit_price * $serviceItem->quantity;
                            $serviceItem->save();
                        }
                        
                        $totalPrice += $serviceItem->subtotal;
                        $activeServiceCount++;
                    }
                }
                
                // Update service_count field
                $reservation->service_count = $activeServiceCount;
                
                // Update total price if we have calculated it
                if ($totalPrice > 0) {
                    $reservation->total_price = $totalPrice;
                }
            }

            // Update reservation status ONLY if it was previously approved and being modified by camper
            // If status is "pending" (under review), keep it as "pending"
            // If status is "approved" and camper modifies it, change to "modified"
            if ($reservation->status === 'approved' && $reservation->user_id == $userId) {
                $reservation->status = 'modified';
                $reservation->last_modified_by = 'user';
                $reservation->last_modified_at = now();
            }
            // If status is "pending" and camper modifies it, keep it as "pending" (under review)
            // No status change needed

            $reservation->save();
            DB::commit();

            return response()->json([
                'message' => 'Reservation updated successfully.',
                'reservation' => $reservation->load('serviceItems')
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating reservation: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Cancel a reservation (change status to 'canceled').
     */
    public function destroy(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);
        
        // Check if user is authorized to cancel this reservation
        $userId = Auth::id();
        if ($reservation->user_id != $userId && $reservation->centre_id != $userId) {
            return response()->json([
                'message' => 'Unauthorized to cancel this reservation.'
            ], 403);
        }

        $reservation->status = 'canceled';
        $updated = $reservation->save();

        if (!$updated) {
            return response()->json(['message' => 'Failed to cancel reservation.'], 500);
        }

        // Also cancel all service items
        ReservationServiceItem::where('reservation_id', $reservation->id)
            ->update(['status' => 'canceled']);

        // Send notification emails
        $user = $reservation->user;
        $center = $reservation->centre;

        if ($center && $user) {
            Mail::to($center->email)->send(new ReservationCanceledByUser($center, $user));
        }

        return response()->json([
            'message' => 'Reservation cancelled successfully.',
            'reservation' => $reservation
        ], 200);
    }
    /**
     * Get user's reservation history for a specific reservation
     * (Accessible by both the user and the centre for that reservation)
     */
    public function getUserReservationHistory(int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);
        
        // Check if current user is authorized (either the user or the centre)
        $userId = Auth::id();
        if ($reservation->user_id != $userId && $reservation->centre_id != $userId) {
            return response()->json([
                'message' => 'Unauthorized to view this user\'s history.'
            ], 403);
        }
        
        // Get the user's other reservations
        $userReservations = Reservations_centre::where('user_id', $reservation->user_id)
            ->where('id', '!=', $id)
            ->with(['serviceItems'])
            ->orderBy('created_at', 'desc')
            ->limit(10) // Limit to 10 most recent
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'type' => $r->type,
                    'description' => $r->note ?: ($r->serviceItems->isNotEmpty() 
                        ? $r->serviceItems->map(fn($item) => $item->service_name)->join(', ')
                        : "Reservation #{$r->id}"),
                    'date_debut' => $r->date_debut,
                    'date_fin' => $r->date_fin,
                    'status' => $r->status,
                    'total_price' => $r->total_price,
                    'service_count' => $r->serviceItems->count()
                ];
            });

        return response()->json([
            'message' => 'User reservation history retrieved successfully.',
            'history' => $userReservations
        ], 200);
    }
    /**
     * Confirm (approve) a reservation and its service items.
     */
    public function confirm(int $id)
        {
            // Get reservation with relationships
            $reservation = Reservations_centre::with(['user', 'serviceItems'])->findOrFail($id);
            
            // Check authorization
            if ($reservation->centre_id != Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Update status
            $reservation->status = 'approved';
            $reservation->save();
            
            // Also approve all service items
            ReservationServiceItem::where('reservation_id', $reservation->id)
                ->update(['status' => 'approved']);

            // Send email - SIMPLE like verification
            if ($reservation->user && $reservation->user->email) {
                // Use the SAME pattern as verification service
                \Mail::to($reservation->user->email)->send(new \App\Mail\CentreReservationConfirmed($reservation));
            }

            return response()->json([
                'message' => 'Reservation approved and email sent',
                'reservation' => $reservation
            ], 200);
        }


    /**
     * Reject a reservation and its service items.
     */
    public function reject(Request $request, int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);
        
        // Only centers can reject reservations
        $userId = Auth::id();
        if ($reservation->centre_id != $userId) {
            return response()->json([
                'message' => 'Only the center can reject reservations.'
            ], 403);
        }

        $reservation->status = 'rejected';
        $updated = $reservation->save();

        if (!$updated) {
            return response()->json(['message' => 'Failed to reject reservation.'], 500);
        }
        
        // Also reject all service items
        ReservationServiceItem::where('reservation_id', $reservation->id)
            ->update([
                'status' => 'rejected',
                'rejected_by' => 'center',
                'rejection_reason' => $request->input('reason', 'No reason provided'),
                'rejected_at' => now()
            ]);

        $user = $reservation->user;
        $reason = $request->input('reason', 'No reason provided');

        if ($user) {
            Mail::to($user->email)->send(new ReservationRejected($user, $reason));
        }

        return response()->json([
            'message' => 'Reservation rejected successfully.',
            'reservation' => $reservation
        ], 200);
    }
        /**
     * NEW: Partial acceptance - center rejects some services, accepts others
     */
    public function partialAccept(Request $request, int $id)
    {
        $reservation = Reservations_centre::with(['serviceItems', 'user'])
            ->findOrFail($id);
        
        // Only centers can modify
        if ($reservation->centre_id != Auth::id()) {
            return response()->json([
                'message' => 'Only the center can modify reservations.'
            ], 403);
        }

        $validated = $request->validate([
            'rejected_services' => 'required|array|min:1',
            'rejected_services.*.service_item_id' => 'required|exists:reservation_service_items,id',
            'rejected_services.*.reason' => 'required|string|max:500',
            'general_reason' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $rejectedCount = 0;
            $acceptedCount = 0;
            
            // Track all service IDs for validation
            $allServiceIds = $reservation->serviceItems->pluck('id')->toArray();
            
            // **CRITICAL FIX: First, reset ALL services to pending**
            // This ensures clean state before applying new statuses
            ReservationServiceItem::where('reservation_id', $reservation->id)
                ->update([
                    'status' => ReservationServiceItem::STATUS_PENDING,
                    'rejected_by' => null,
                    'rejection_reason' => null,
                    'rejected_at' => null
                ]);
            
            // Refresh the collection after reset
            $reservation->load('serviceItems');
            
            // Process rejected services
            foreach ($validated['rejected_services'] as $rejected) {
                if (!in_array($rejected['service_item_id'], $allServiceIds)) {
                    throw new \Exception("Invalid service item ID: {$rejected['service_item_id']}");
                }
                
                $serviceItem = ReservationServiceItem::find($rejected['service_item_id']);
                $serviceItem->markAsRejectedByCenter($rejected['reason']);
                $rejectedCount++;
            }
            
            // **FIXED: Mark remaining services as approved (no pending check needed)**
            $rejectedIds = collect($validated['rejected_services'])->pluck('service_item_id')->toArray();
            $remainingServices = $reservation->serviceItems->whereNotIn('id', $rejectedIds);
            
            foreach ($remainingServices as $service) {
                // Remove the pending check - ALL remaining should be approved
                $service->status = ReservationServiceItem::STATUS_APPROVED;
                $service->rejected_by = null;
                $service->rejection_reason = null;
                $service->rejected_at = null;
                $service->save();
                $acceptedCount++;
            }
            
            // Check if ANY services remain approved
            if ($acceptedCount === 0) {
                // All services rejected = full rejection
                $reservation->status = Reservations_centre::STATUS_REJECTED;
                $reservation->save();
                
                DB::commit();
                
                return response()->json([
                    'message' => 'All services rejected. Reservation fully rejected.',
                    'rejected_count' => $rejectedCount,
                    'accepted_count' => $acceptedCount,
                    'reservation' => $reservation->fresh(['serviceItems'])
                ], 200);
            }
            
            // Partial acceptance - mark as modified by center
            $reservation->markAsModifiedByCenter();
            
            // Update total price to reflect only accepted services
            $newTotal = $reservation->serviceItems
                ->where('status', ReservationServiceItem::STATUS_APPROVED)
                ->sum('subtotal');
            $reservation->total_price = $newTotal;
            $reservation->save();
            
            // **IMPORTANT: Refresh ALL data before sending email**
            $reservation->refresh();
            $reservation->load(['serviceItems', 'user']);
            
            // Send notification email
            if ($reservation->user && $reservation->user->email) {
                Mail::to($reservation->user->email)->send(
                    new ReservationModifiedByCenter($reservation, $validated)
                );
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Reservation partially accepted. ' . $acceptedCount . ' service(s) approved, ' . $rejectedCount . ' service(s) rejected.',
                'rejected_count' => $rejectedCount,
                'accepted_count' => $acceptedCount,
                'new_total_price' => $newTotal,
                'modification_info' => $reservation->getModificationInfo(),
                'reservation' => $reservation->fresh(['serviceItems'])
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Failed to process partial acceptance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the center's availability status based on current reservations.
     */
    public function update_status()
    {
        $idCentre = Auth::id(); 

        // Count approved AND modified reservations that are still active
        $activeReservations = Reservations_centre::where('centre_id', $idCentre)
            ->where('date_fin', '>', now())
            ->whereIn('status', ['approved', 'modified']) // Modified reservations are also active
            ->count();

        $profile = Profile::where('user_id', $idCentre)->first();

        if (!$profile) {
            return response()->json(['message' => 'Profile not found for this user.'], 404);
        }

        $profileCentre = ProfileCentre::where('profile_id', $profile->id)->first();

        if (!$profileCentre) {
            return response()->json(['message' => 'Centre capacity info not found.'], 404);
        }

        $capacity_total = $profileCentre->capacite;

        if ($activeReservations >= $capacity_total) {
            $profileCentre->disponibilite = false;
            $profileCentre->save();

            return response()->json([
                'message' => 'Centre is full. Status updated to unavailable.',
                'vacant_places' => 0
            ]);
        } else {
            $vacant = $capacity_total - $activeReservations;

            return response()->json([
                'message' => 'Centre still has available spots.',
                'vacant_places' => $vacant
            ]);
        }
    }
    /**
     * Get statistics for the center's reservations.
     */
    public function statistics()
    {
        $idCentre = Auth::id();

        $stats = [
            'total_reservations' => Reservations_centre::where('centre_id', $idCentre)->count(),
            'pending_reservations' => Reservations_centre::where('centre_id', $idCentre)
                ->where('status', 'pending')->count(),
            'approved_reservations' => Reservations_centre::where('centre_id', $idCentre)
                ->where('status', 'approved')->count(),
            'modified_reservations' => Reservations_centre::where('centre_id', $idCentre)
                ->where('status', 'modified')->count(),
            'rejected_reservations' => Reservations_centre::where('centre_id', $idCentre)
                ->where('status', 'rejected')->count(),
            'canceled_reservations' => Reservations_centre::where('centre_id', $idCentre)
                ->where('status', 'canceled')->count(),
            'total_revenue' => Reservations_centre::where('centre_id', $idCentre)
                ->where('status', 'approved')
                ->sum('total_price'),
            'modified_revenue' => Reservations_centre::where('centre_id', $idCentre)
                ->where('status', 'modified')
                ->sum('total_price'),
            'popular_services' => DB::table('reservation_service_items')
                ->join('profile_center_services', 'reservation_service_items.profile_center_service_id', '=', 'profile_center_services.id')
                ->join('reservations_centres', 'reservation_service_items.reservation_id', '=', 'reservations_centres.id')
                ->where('reservations_centres.centre_id', $idCentre)
                ->whereIn('reservations_centres.status', ['approved', 'modified']) // Include modified
                ->where('reservation_service_items.status', 'approved') // Only count approved services
                ->select('profile_center_services.name', DB::raw('SUM(reservation_service_items.quantity) as total_quantity'))
                ->groupBy('profile_center_services.id', 'profile_center_services.name')
                ->orderBy('total_quantity', 'desc')
                ->limit(5)
                ->get()
        ];

        return response()->json([
            'message' => 'Statistics retrieved successfully.',
            'statistics' => $stats
        ], 200);
    }
}