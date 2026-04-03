<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UnifiedReservationController extends Controller
{
    /**
     * Get all reservations for the authenticated user
     * Combines event, materiel, centre, and guide reservations
     */
    public function getAllReservations(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;
        
        $allReservations = collect();
        
        // 1. Get Event Reservations (as camper)
        $eventAsCamper = $this->getEventReservationsAsCamper($userId);
        $allReservations = $allReservations->concat($eventAsCamper);
        
        // 2. Get Event Reservations (as group owner)
        if ($user->role_id === 2) { // Group role
            $eventAsOwner = $this->getEventReservationsAsOwner($userId);
            $allReservations = $allReservations->concat($eventAsOwner);
        }
        
        // 3. Get Materiel Reservations (as camper/buyer)
        $materielAsCamper = $this->getMaterielReservationsAsCamper($userId);
        $allReservations = $allReservations->concat($materielAsCamper);
        
        // 4. Get Materiel Reservations (as supplier)
        if ($user->role_id === 4) { // Fournisseur role
            $materielAsSupplier = $this->getMaterielReservationsAsSupplier($userId);
            $allReservations = $allReservations->concat($materielAsSupplier);
        }
        
        // 5. Get Centre Reservations (as camper)
        $centreAsCamper = $this->getCentreReservationsAsCamper($userId);
        $allReservations = $allReservations->concat($centreAsCamper);
        
        // 6. Get Centre Reservations (as centre owner)
        if ($user->role_id === 3) { // Centre role
            $centreAsOwner = $this->getCentreReservationsAsOwner($userId);
            $allReservations = $allReservations->concat($centreAsOwner);
        }
        
        // 7. Get Guide Reservations (as camper/reserver)
        $guideAsCamper = $this->getGuideReservationsAsCamper($userId);
        $allReservations = $allReservations->concat($guideAsCamper);
        
        // 8. Get Guide Reservations (as guide)
        if ($user->role_id === 5) { // Guide role
            $guideAsGuide = $this->getGuideReservationsAsGuide($userId);
            $allReservations = $allReservations->concat($guideAsGuide);
        }
        
        // Sort by created_at descending
        $sortedReservations = $allReservations->sortByDesc('created_at')->values();
        
        // Apply filters if provided
        if ($request->has('status') && $request->status) {
            $sortedReservations = $sortedReservations->filter(function($res) use ($request) {
                return $res['status_label'] === $request->status || $res['status'] === $request->status;
            });
        }
        
        if ($request->has('type') && $request->type) {
            $sortedReservations = $sortedReservations->filter(function($res) use ($request) {
                return $res['type'] === $request->type;
            });
        }
        
        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $sortedReservations = $sortedReservations->filter(function($res) use ($search) {
                return str_contains(strtolower($res['title']), $search) ||
                       str_contains(strtolower($res['description'] ?? ''), $search) ||
                       str_contains(strtolower($res['id']), $search);
            });
        }
        
        return response()->json([
            'success' => true,
            'reservations' => $sortedReservations,
            'counts' => [
                'total' => $sortedReservations->count(),
                'by_type' => [
                    'event' => $sortedReservations->where('type', 'event')->count(),
                    'materiel' => $sortedReservations->where('type', 'materiel')->count(),
                    'centre' => $sortedReservations->where('type', 'centre')->count(),
                    'guide' => $sortedReservations->where('type', 'guide')->count(),
                ],
                'by_status' => $this->getStatusCounts($sortedReservations),
            ]
        ]);
    }
    
    /**
     * Get event reservations where user is a camper/participant
     */
    private function getEventReservationsAsCamper($userId)
    {
        return \App\Models\Reservations_events::where('user_id', $userId)
            ->with(['event', 'user'])
            ->get()
            ->map(function($reservation) {
                return $this->formatEventReservation($reservation, 'camper');
            });
    }
    
    /**
     * Get event reservations where user is the group owner
     */
    private function getEventReservationsAsOwner($userId)
    {
        return \App\Models\Reservations_events::where('group_id', $userId)
            ->with(['event', 'user'])
            ->get()
            ->map(function($reservation) {
                return $this->formatEventReservation($reservation, 'owner');
            });
    }
    
    /**
     * Get materiel reservations where user is the camper/buyer
     */
    private function getMaterielReservationsAsCamper($userId)
    {
        return \App\Models\Reservations_materielles::where('user_id', $userId)
            ->with(['materielle', 'fournisseur', 'user'])
            ->get()
            ->map(function($reservation) {
                return $this->formatMaterielReservation($reservation, 'camper');
            });
    }
    
    /**
     * Get materiel reservations where user is the supplier
     */
    private function getMaterielReservationsAsSupplier($userId)
    {
        return \App\Models\Reservations_materielles::where('fournisseur_id', $userId)
            ->with(['materielle', 'user', 'fournisseur'])
            ->get()
            ->map(function($reservation) {
                return $this->formatMaterielReservation($reservation, 'supplier');
            });
    }
    
    /**
     * Get centre reservations where user is the camper
     */
    private function getCentreReservationsAsCamper($userId)
    {
        return \App\Models\Reservations_centre::where('user_id', $userId)
            ->with(['serviceItems', 'user', 'centre'])
            ->get()
            ->map(function($reservation) {
                return $this->formatCentreReservation($reservation, 'camper');
            });
    }
    
    /**
     * Get centre reservations where user is the centre owner
     */
    private function getCentreReservationsAsOwner($userId)
    {
        return \App\Models\Reservations_centre::where('centre_id', $userId)
            ->with(['serviceItems', 'user', 'centre'])
            ->get()
            ->map(function($reservation) {
                return $this->formatCentreReservation($reservation, 'owner');
            });
    }
    
    /**
     * Get guide reservations where user is the camper/reserver
     */
    private function getGuideReservationsAsCamper($userId)
    {
        return \App\Models\Reservation_guide::where('reserver_id', $userId)
            ->with(['circuit', 'user'])
            ->get()
            ->map(function($reservation) {
                return $this->formatGuideReservation($reservation, 'camper');
            });
    }
    
    /**
     * Get guide reservations where user is the guide
     */
    private function getGuideReservationsAsGuide($userId)
    {
        return \App\Models\Reservation_guide::where('guide_id', $userId)
            ->with(['circuit', 'user'])
            ->get()
            ->map(function($reservation) {
                return $this->formatGuideReservation($reservation, 'guide');
            });
    }
    
    /**
     * Format event reservation for unified display
     */
    private function formatEventReservation($reservation, $role)
    {
        $event = $reservation->event;
        $totalPrice = $event ? ($event->price * $reservation->nbr_place) : 0;
        
        return [
            'id' => $reservation->id,
            'original_id' => $reservation->id,
            'type' => 'event',
            'role' => $role,
            'title' => $event->title ?? 'Event',
            'description' => $event->description ?? '',
            'start_date' => $event->start_date ?? null,
            'end_date' => $event->end_date ?? null,
            'date_debut' => $event->start_date ?? null,
            'date_fin' => $event->end_date ?? null,
            'nbr_place' => $reservation->nbr_place,
            'status' => $reservation->status,
            'status_label' => $this->mapEventStatus($reservation->status),
            'total_price' => $totalPrice,
            'user' => $reservation->user ? [
                'id' => $reservation->user->id,
                'first_name' => $reservation->user->first_name,
                'last_name' => $reservation->user->last_name,
                'email' => $reservation->user->email,
                'phone_number' => $reservation->user->phone_number,
                'ville' => $reservation->user->ville,
                'address' => $reservation->user->addresse,
                'avatar' => $reservation->user->avatar ? asset('storage/' . $reservation->user->avatar) : null,
                'created_at' => $reservation->user->created_at,
            ] : null,
            'event' => $event ? [
                'id' => $event->id,
                'title' => $event->title,
                'location' => $event->address ?? '',
            ] : null,
            'location' => $event->address ?? '',
            'created_at' => $reservation->created_at,
            'updated_at' => $reservation->updated_at,
            'service_count' => 1,
            'service_items' => [],
            'modified_by' => $reservation->last_modified_by ?? null,
            'modified_label' => null,
            'can_edit' => $role === 'camper' && in_array($reservation->status, ['pending', 'en_attente_paiement']),
            'can_cancel' => in_array($reservation->status, ['pending', 'confirmed', 'en_attente_validation']),
            'can_approve' => $role === 'owner' && $reservation->status === 'pending',
            'can_reject' => $role === 'owner' && $reservation->status === 'pending',
        ];
    }
    
    /**
     * Format materiel reservation for unified display
     */
    private function formatMaterielReservation($reservation, $role)
    {
        $materielle = $reservation->materielle;
        
        // Determine who can approve modifications
        $canApproveModified = false;
        $modifiedBy = null;
        
        if ($reservation->status === 'modified') {
            $modifiedBy = 'user'; // or 'supplier'
            $canApproveModified = ($role === 'supplier' && $modifiedBy === 'user') ||
                                  ($role === 'camper' && $modifiedBy === 'supplier');
        }
        
        return [
            'id' => $reservation->id,
            'original_id' => $reservation->id,
            'type' => 'materiel',
            'role' => $role,
            'title' => $materielle->nom ?? 'Equipment',
            'description' => $materielle->description ?? '',
            'start_date' => $reservation->date_debut,
            'end_date' => $reservation->date_fin,
            'date_debut' => $reservation->date_debut,
            'date_fin' => $reservation->date_fin,
            'nbr_place' => $reservation->quantite,
            'status' => $reservation->status,
            'status_label' => $this->mapMaterielStatus($reservation->status),
            'total_price' => $reservation->montant_total,
            'user' => $reservation->user ? [
                'id' => $reservation->user->id,
                'first_name' => $reservation->user->first_name,
                'last_name' => $reservation->user->last_name,
                'email' => $reservation->user->email,
                'phone_number' => $reservation->user->phone_number,
                'ville' => $reservation->user->ville,
                'address' => $reservation->user->addresse,
                'avatar' => $reservation->user->avatar ? asset('storage/' . $reservation->user->avatar) : null,
                'created_at' => $reservation->user->created_at,
            ] : null,
            'supplier' => $reservation->fournisseur ? [
                'id' => $reservation->fournisseur->id,
                'first_name' => $reservation->fournisseur->first_name,
                'last_name' => $reservation->fournisseur->last_name,
                'email' => $reservation->fournisseur->email,
            ] : null,
            'materielle' => $materielle ? [
                'id' => $materielle->id,
                'nom' => $materielle->nom,
                'is_rentable' => $materielle->is_rentable,
                'is_sellable' => $materielle->is_sellable,
            ] : null,
            'location' => $reservation->adresse_livraison ?? '',
            'created_at' => $reservation->created_at,
            'updated_at' => $reservation->updated_at,
            'service_count' => 1,
            'service_items' => [],
            'modified_by' => $modifiedBy,
            'can_approve_modified' => $canApproveModified,
            'can_edit' => $role === 'camper' && $reservation->status === 'pending',
            'can_cancel' => in_array($reservation->status, ['pending', 'confirmed']),
            'can_approve' => $role === 'supplier' && $reservation->status === 'pending',
            'can_reject' => $role === 'supplier' && $reservation->status === 'pending',
        ];
    }
    
    /**
     * Format centre reservation for unified display
     */
    private function formatCentreReservation($reservation, $role)
    {
        $serviceItems = $reservation->serviceItems ?? collect();
        $serviceTypes = $serviceItems->map(function($item) {
            return $item->service_name;
        })->join(', ');
        
        $serviceCount = $serviceItems->count();
        
        // Check if this reservation has been modified
        $isModified = $reservation->status === 'modified';
        $modifiedBy = $reservation->last_modified_by ?? null;
        
        // Determine if current user can approve modifications
        $canApproveModified = false;
        if ($isModified && $modifiedBy) {
            $canApproveModified = ($role === 'owner' && $modifiedBy === 'user') ||
                                  ($role === 'camper' && $modifiedBy === 'center');
        }
        
        // Get modified label
        $modifiedLabel = null;
        if ($isModified && $modifiedBy) {
            $modifiedLabel = [
                'text' => $modifiedBy === 'user' ? 'Modified by Camper' : 'Modified by Center',
                'icon' => $modifiedBy === 'user' ? 'user' : 'building',
                'color' => $modifiedBy === 'user' ? 'text-blue-600' : 'text-amber-600',
                'bgColor' => $modifiedBy === 'user' ? 'bg-blue-50' : 'bg-amber-50',
                'borderColor' => $modifiedBy === 'user' ? 'border-blue-200' : 'border-amber-200',
            ];
        }
        
        return [
            'id' => $reservation->id,
            'original_id' => $reservation->id,
            'type' => 'centre',
            'role' => $role,
            'title' => $reservation->centre->name ?? 'Camping Center',
            'description' => $reservation->note ?? $serviceTypes,
            'start_date' => $reservation->date_debut,
            'end_date' => $reservation->date_fin,
            'date_debut' => $reservation->date_debut,
            'date_fin' => $reservation->date_fin,
            'nbr_place' => $reservation->nbr_place,
            'status' => $reservation->status,
            'status_label' => $this->mapCentreStatus($reservation->status),
            'total_price' => $reservation->total_price,
            'user' => $reservation->user ? [
                'id' => $reservation->user->id,
                'first_name' => $reservation->user->first_name,
                'last_name' => $reservation->user->last_name,
                'email' => $reservation->user->email,
                'phone_number' => $reservation->user->phone_number,
                'ville' => $reservation->user->ville,
                'address' => $reservation->user->addresse,
                'avatar' => $reservation->user->avatar ? asset('storage/' . $reservation->user->avatar) : null,
                'created_at' => $reservation->user->created_at,
            ] : null,
            'centre' => $reservation->centre ? [
                'id' => $reservation->centre->id,
                'name' => $reservation->centre->name,
                'ville' => $reservation->centre->ville,
            ] : null,
            'location' => $reservation->centre->ville ?? '',
            'service_items' => $serviceItems->map(function($item) {
                return [
                    'id' => $item->id,
                    'service_name' => $item->service_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'status' => $item->status,
                ];
            }),
            'service_count' => $serviceCount,
            'created_at' => $reservation->created_at,
            'updated_at' => $reservation->updated_at,
            'modified_by' => $modifiedBy,
            'modified_label' => $modifiedLabel,
            'can_approve_modified' => $canApproveModified,
            'can_edit' => $role === 'camper' && $reservation->status === 'pending',
            'can_cancel' => in_array($reservation->status, ['pending', 'approved', 'modified']),
            'can_approve' => $role === 'owner' && $reservation->status === 'pending',
            'can_reject' => $role === 'owner' && $reservation->status === 'pending',
        ];
    }
    
    /**
     * Format guide reservation for unified display
     */
    private function formatGuideReservation($reservation, $role)
    {
        $circuit = $reservation->circuit;
        
        return [
            'id' => $reservation->id,
            'original_id' => $reservation->id,
            'type' => 'guide',
            'role' => $role,
            'title' => $circuit->name ?? 'Guided Tour',
            'description' => $reservation->discription ?? $circuit->description ?? '',
            'start_date' => $circuit->start_date ?? null,
            'end_date' => $circuit->end_date ?? null,
            'date_debut' => $circuit->start_date ?? null,
            'date_fin' => $circuit->end_date ?? null,
            'nbr_place' => 1, // Guide reservations are per person
            'status' => 'confirmed', // Guide reservations are confirmed by default
            'status_label' => 'Confirmed',
            'total_price' => $circuit->price ?? 0,
            'user' => $reservation->user ? [
                'id' => $reservation->user->id,
                'first_name' => $reservation->user->first_name,
                'last_name' => $reservation->user->last_name,
                'email' => $reservation->user->email,
                'phone_number' => $reservation->user->phone_number,
                'ville' => $reservation->user->ville,
                'address' => $reservation->user->addresse,
                'avatar' => $reservation->user->avatar ? asset('storage/' . $reservation->user->avatar) : null,
                'created_at' => $reservation->user->created_at,
            ] : null,
            'guide' => $reservation->guide_id ? [
                'id' => $reservation->guide_id,
                'name' => 'Guide',
            ] : null,
            'circuit' => $circuit ? [
                'id' => $circuit->id,
                'name' => $circuit->name,
                'price' => $circuit->price,
            ] : null,
            'location' => $circuit->start_location ?? '',
            'created_at' => $reservation->creation_date ?? $reservation->created_at,
            'updated_at' => $reservation->updated_at,
            'service_count' => 1,
            'service_items' => [],
            'modified_by' => null,
            'modified_label' => null,
            'can_edit' => false, // Guide reservations cannot be edited
            'can_cancel' => $role === 'camper', // Campers can cancel guide reservations
            'can_approve' => false,
            'can_reject' => false,
        ];
    }
    
    /**
     * Get status counts for filtering
     */
    private function getStatusCounts($reservations)
    {
        return $reservations->groupBy('status_label')
            ->map(function($group) {
                return $group->count();
            })
            ->toArray();
    }
    
    /**
     * Map event status to display label
     */
    private function mapEventStatus($status)
    {
        $map = [
            'en_attente_paiement' => 'Pending Payment',
            'confirmée' => 'Confirmed',
            'en_attente_validation' => 'Under Review',
            'refusée' => 'Rejected',
            'annulée_par_utilisateur' => 'Cancelled by User',
            'annulée_par_organisateur' => 'Cancelled by Organizer',
            'remboursement_en_attente' => 'Refund Pending',
            'remboursée_partielle' => 'Partially Refunded',
            'remboursée_totale' => 'Fully Refunded',
            'modified' => 'Modified',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
    
    /**
     * Map materiel status to display label
     */
    private function mapMaterielStatus($status)
    {
        $map = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'paid' => 'Paid',
            'retrieved' => 'Retrieved',
            'returned' => 'Returned',
            'rejected' => 'Rejected',
            'canceled' => 'Cancelled',
            'cancelled_by_camper' => 'Cancelled by Camper',
            'cancelled_by_fournisseur' => 'Cancelled by Supplier',
            'disputed' => 'Disputed',
            'modified' => 'Modified',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
    
    /**
     * Map centre status to display label
     */
    private function mapCentreStatus($status)
    {
        $map = [
            'pending' => 'Pending',
            'approved' => 'Confirmed',
            'modified' => 'Modified',
            'rejected' => 'Rejected',
            'canceled' => 'Cancelled',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}