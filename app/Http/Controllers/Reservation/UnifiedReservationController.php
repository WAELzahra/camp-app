<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\WalletTransaction;
use App\Services\CommissionService;

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
            ->with(['event.group.profile.profileGroupe', 'user'])
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
            ->with(['materielle', 'fournisseur.profile.profileFournisseur', 'user'])
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
            ->with(['serviceItems', 'user', 'centre.profile.profileCentre'])
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
        $event          = $reservation->event;
        $basePrice      = $event ? round((float)$event->price * (int)$reservation->nbr_place, 2) : 0;
        $discountAmt    = round((float)($reservation->discount_amount ?? 0), 2);
        $platformFeeAmt = round((float)($reservation->platform_fee_amount ?? 0), 2);
        $platformFeeRate = round((float)($reservation->platform_fee_rate ?? 0), 2);
        // Total the camper actually paid = base − discount + platform fee
        $totalPrice = max(0, round($basePrice - $discountAmt + $platformFeeAmt, 2));

        // Commission from the organizer's wallet transaction
        $orgTx = WalletTransaction::where('reference_type', 'event_reservation')
            ->where('reference_id', $reservation->id)
            ->where('type', 'credit')
            ->first();
        if ($orgTx) {
            $commissionAmt  = (float) $orgTx->commission_amount;
            $commissionRate = (float) $orgTx->commission_rate;
        } else {
            $netBase        = max(0, $basePrice - $discountAmt);
            $calc           = CommissionService::calculate('group', $netBase);
            $commissionAmt  = round($calc['commission'], 2);
            $commissionRate = round($calc['rate'] * 100, 2);
        }

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
            'platform_fee_amount' => $platformFeeAmt,
            'platform_fee_rate'   => $platformFeeRate,
            'discount_amount'     => $discountAmt,
            'commission_amount'   => $commissionAmt,
            'commission_rate'     => $commissionRate,
            'payment_method'      => $reservation->payment_method ?? 'wallet',
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
                'id'          => $event->id,
                'title'       => $event->title,
                'description' => $event->description ?? '',
                'location'    => $event->address ?? '',
                'latitude'    => $event->latitude ?? null,
                'longitude'   => $event->longitude ?? null,
                'event_type'  => $event->event_type ?? null,
                'capacity'    => $event->capacity ?? null,
                'price'       => $event->price ?? null,
                'start_date'  => $event->start_date ?? null,
                'end_date'    => $event->end_date ?? null,
            ] : null,
            'group' => ($event && $event->group) ? [
                'id'         => $event->group->id,
                'first_name' => $event->group->first_name,
                'last_name'  => $event->group->last_name,
                'email'      => $event->group->email,
                'phone_number' => $event->group->phone_number,
                'avatar'     => $event->group->avatar ? asset('storage/' . $event->group->avatar) : null,
                'group_name' => $event->group->profile?->profileGroupe?->nom_groupe ?? null,
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
        $materielle     = $reservation->materielle;
        $montantTotal   = round((float)($reservation->montant_total ?? 0), 2);
        $platformFeeAmt = round((float)($reservation->platform_fee_amount ?? 0), 2);
        $platformFeeRate= round((float)($reservation->platform_fee_rate  ?? 0), 2);
        $discountAmt    = round((float)($reservation->discount_amount    ?? 0), 2);

        // Look up supplier's wallet transaction for exact commission (if payment done)
        $supTx = WalletTransaction::where('reference_type', 'materiel_reservation')
            ->where('reference_id', $reservation->id)
            ->where('type', 'credit')
            ->first();
        if ($supTx) {
            $commissionAmt  = (float) $supTx->commission_amount;
            $commissionRate = (float) $supTx->commission_rate;
        } else {
            $netBase        = max(0, $montantTotal - $platformFeeAmt);
            $calc           = CommissionService::calculate('supplier', $netBase);
            $commissionAmt  = round($calc['commission'], 2);
            $commissionRate = round($calc['rate'] * 100, 2);
        }

        // Determine who can approve modifications
        $canApproveModified = false;
        $modifiedBy = null;

        if ($reservation->status === 'modified') {
            $modifiedBy = 'user';
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
            'total_price' => $montantTotal,
            'platform_fee_amount' => $platformFeeAmt,
            'platform_fee_rate'   => $platformFeeRate,
            'discount_amount'     => $discountAmt,
            'commission_amount'   => $commissionAmt,
            'commission_rate'     => $commissionRate,
            'payment_method'      => $reservation->payment_method ?? 'wallet',
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
            'supplier' => $reservation->fournisseur ? (function($f) {
                $pf = $f->profile?->profileFournisseur ?? null;
                return [
                    'id'           => $f->id,
                    'first_name'   => $f->first_name,
                    'last_name'    => $f->last_name,
                    'email'        => $f->email,
                    'phone_number' => $f->phone_number,
                    'ville'        => $f->ville,
                    'avatar'       => $f->avatar ? asset('storage/' . $f->avatar) : null,
                    'product_category' => $pf?->product_category ?? null,
                    'intervale_prix'   => $pf?->intervale_prix ?? null,
                ];
            })($reservation->fournisseur) : null,
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
            'canceled_by' => $reservation->canceled_by ?? null,
            'status_label' => $reservation->status === 'canceled'
                ? ($reservation->canceled_by === 'center' ? 'Cancelled by Center' : 'Cancelled by You')
                : $this->mapCentreStatus($reservation->status),
            'total_price' => $reservation->total_price,
            'platform_fee_amount' => $reservation->platform_fee_amount ?? 0,
            'platform_fee_rate' => $reservation->platform_fee_rate ?? 0,
            'payment_method' => $reservation->payment_method ?? 'wallet',
            'discount_amount' => $reservation->discount_amount ?? 0,
            'commission_amount' => (function() use ($reservation) {
                $tx = WalletTransaction::where('reference_type', 'centre_reservation')
                    ->where('reference_id', $reservation->id)
                    ->where('type', 'credit')
                    ->first();
                if ($tx) return (float) $tx->commission_amount;
                // Reservation not yet confirmed — estimate from config
                $netBase = round((float)($reservation->total_price ?? 0) - (float)($reservation->platform_fee_amount ?? 0), 2);
                return round(CommissionService::calculate('center', $netBase)['commission'], 2);
            })(),
            'commission_rate' => (function() use ($reservation) {
                $tx = WalletTransaction::where('reference_type', 'centre_reservation')
                    ->where('reference_id', $reservation->id)
                    ->where('type', 'credit')
                    ->first();
                if ($tx) return (float) $tx->commission_rate;
                return round(CommissionService::calculate('center', 100)['rate'] * 100, 2);
            })(),
            'nights' => $reservation->nights ?? 1,
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
            'centre' => $reservation->centre ? (function($c) {
                $pc = $c->profile?->profileCentre ?? null;
                return [
                    'id'            => $c->id,
                    'name'          => $pc?->name ?? $c->first_name . ' ' . $c->last_name,
                    'ville'         => $c->ville,
                    'address'       => $c->profile?->address ?? null,
                    'contact_email' => $pc?->contact_email ?? $c->email,
                    'contact_phone' => $pc?->contact_phone ?? $c->phone_number,
                    'manager_name'  => $pc?->manager_name ?? null,
                    'category'      => $pc?->category ?? null,
                    'latitude'      => $pc?->latitude ?? null,
                    'longitude'     => $pc?->longitude ?? null,
                    'capacite'      => $pc?->capacite ?? null,
                    'price_per_night' => $pc?->price_per_night ?? null,
                    'avatar'        => $c->avatar ? asset('storage/' . $c->avatar) : null,
                ];
            })($reservation->centre) : null,
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