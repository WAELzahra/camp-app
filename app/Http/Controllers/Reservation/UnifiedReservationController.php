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
        $reservations = \App\Models\Reservations_events::where('user_id', $userId)
            ->with(['event.group.profile.profileGroupe', 'user', 'services.service'])
            ->get();
        $txMap = $this->batchLoadWalletTx('event_reservation', $reservations->pluck('id'));
        return $reservations->map(fn($r) => $this->formatEventReservation($r, 'camper', $txMap->get($r->id)));
    }

    /**
     * Get event reservations where user is the group owner
     */
    private function getEventReservationsAsOwner($userId)
    {
        $reservations = \App\Models\Reservations_events::where('group_id', $userId)
            ->with(['event', 'user', 'services.service'])
            ->get();
        $txMap = $this->batchLoadWalletTx('event_reservation', $reservations->pluck('id'));
        return $reservations->map(fn($r) => $this->formatEventReservation($r, 'owner', $txMap->get($r->id)));
    }

    /**
     * Get materiel reservations where user is the camper/buyer
     */
    private function getMaterielReservationsAsCamper($userId)
    {
        $reservations = \App\Models\Reservations_materielles::where('user_id', $userId)
            ->with(['materielle', 'fournisseur.profile.profileFournisseur', 'user'])
            ->get();
        $txMap = $this->batchLoadWalletTx('materiel_reservation', $reservations->pluck('id'));
        return $reservations->map(fn($r) => $this->formatMaterielReservation($r, 'camper', $txMap->get($r->id)));
    }

    /**
     * Get materiel reservations where user is the supplier
     */
    private function getMaterielReservationsAsSupplier($userId)
    {
        $reservations = \App\Models\Reservations_materielles::where('fournisseur_id', $userId)
            ->with(['materielle', 'user', 'fournisseur'])
            ->get();
        $txMap = $this->batchLoadWalletTx('materiel_reservation', $reservations->pluck('id'));
        return $reservations->map(fn($r) => $this->formatMaterielReservation($r, 'supplier', $txMap->get($r->id)));
    }

    /**
     * Get centre reservations where user is the camper
     */
    private function getCentreReservationsAsCamper($userId)
    {
        $reservations = \App\Models\Reservations_centre::where('user_id', $userId)
            ->with(['serviceItems', 'user', 'centre.profile.profileCentre'])
            ->get();
        $txMap = $this->batchLoadWalletTx('centre_reservation', $reservations->pluck('id'));
        return $reservations->map(fn($r) => $this->formatCentreReservation($r, 'camper', $txMap->get($r->id)));
    }

    /**
     * Get centre reservations where user is the centre owner
     */
    private function getCentreReservationsAsOwner($userId)
    {
        $reservations = \App\Models\Reservations_centre::where('centre_id', $userId)
            ->with(['serviceItems', 'user', 'centre'])
            ->get();
        $txMap = $this->batchLoadWalletTx('centre_reservation', $reservations->pluck('id'));
        return $reservations->map(fn($r) => $this->formatCentreReservation($r, 'owner', $txMap->get($r->id)));
    }

    /**
     * Batch-load wallet credit transactions for a set of reservation IDs, keyed by reference_id.
     */
    private function batchLoadWalletTx(string $referenceType, $ids): \Illuminate\Support\Collection
    {
        $ids = $ids->filter()->unique()->toArray();
        if (empty($ids)) return collect();
        return WalletTransaction::where('reference_type', $referenceType)
            ->whereIn('reference_id', $ids)
            ->where('type', 'credit')
            ->get()
            ->keyBy('reference_id');
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
    private function formatEventReservation($reservation, $role, $orgTx = null)
    {
        $event          = $reservation->event;
        $basePrice      = $event ? round((float)$event->price * (int)$reservation->nbr_place, 2) : 0;
        $discountAmt    = round((float)($reservation->discount_amount ?? 0), 2);
        $platformFeeAmt = round((float)($reservation->platform_fee_amount ?? 0), 2);
        $platformFeeRate = round((float)($reservation->platform_fee_rate ?? 0), 2);

        $eventServices  = $reservation->services ?? collect();
        $servicesTotal  = round($eventServices->sum('subtotal'), 2);

        // Total = base + add-on services − discount + platform fee
        $totalPrice = max(0, round($basePrice + $servicesTotal - $discountAmt + $platformFeeAmt, 2));

        // Commission from the pre-loaded wallet transaction (or estimate)
        if ($orgTx) {
            $commissionAmt  = (float) $orgTx->commission_amount;
            $commissionRate = (float) $orgTx->commission_rate;
        } else {
            $netBase        = max(0, $basePrice - $discountAmt);
            $calc           = CommissionService::calculateForUser('group', $netBase, $event->group_id ?? 0);
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
            'payment_reference'   => $reservation->payment_reference ?? null,
            'payment_option'      => $reservation->payment_option ?? null,
            'amount_now'          => $reservation->amount_now ?? null,
            'amount_later'        => $reservation->amount_later ?? null,
            'balance_due_at'      => $reservation->balance_due_at ?? null,
            'user' => $this->safeUserRef($reservation->user),
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
            'group' => ($event && $event->group) ? array_merge(
                $this->safeUserRef($event->group),
                ['group_name' => $event->group->profile?->profileGroupe?->nom_groupe ?? null]
            ) : null,
            'location' => $event->address ?? '',
            'created_at' => $reservation->created_at,
            'updated_at' => $reservation->updated_at,
            'service_count' => $eventServices->count(),
            'service_items' => $eventServices->map(function ($svc) {
                return [
                    'id'                  => $svc->id,
                    'service_name'        => $svc->service?->name ?? 'Service',
                    'service_description' => $svc->service?->description ?? null,
                    'quantity'            => (int) $svc->quantity,
                    'unit_price'          => (float) ($svc->price_snapshot ?? $svc->service?->price ?? 0),
                    'unit'                => $svc->pricing_unit_snapshot ?? $svc->service?->pricing_unit ?? null,
                    'subtotal'            => (float) ($svc->subtotal ?? 0),
                    'notes'               => $svc->notes ?? null,
                ];
            })->values()->toArray(),
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
    private function formatMaterielReservation($reservation, $role, $supTx = null)
    {
        $materielle     = $reservation->materielle;
        $montantTotal   = round((float)($reservation->montant_total ?? 0), 2);
        $platformFeeAmt = round((float)($reservation->platform_fee_amount ?? 0), 2);
        $platformFeeRate= round((float)($reservation->platform_fee_rate  ?? 0), 2);
        $discountAmt    = round((float)($reservation->discount_amount    ?? 0), 2);

        if ($supTx) {
            $commissionAmt  = (float) $supTx->commission_amount;
            $commissionRate = (float) $supTx->commission_rate;
        } else {
            $netBase        = max(0, $montantTotal - $platformFeeAmt);
            $calc           = CommissionService::calculateForUser('supplier', $netBase, $reservation->fournisseur_id ?? 0);
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
            'payment_reference'   => $reservation->payment_reference ?? null,
            'payment_option'      => $reservation->payment_option ?? null,
            'amount_now'          => $reservation->amount_now ?? null,
            'amount_later'        => $reservation->amount_later ?? null,
            'balance_due_at'      => $reservation->balance_due_at ?? null,
            'user' => $this->safeUserRef($reservation->user),
            'supplier' => $reservation->fournisseur ? (function($f) {
                $pf = $f->profile?->profileFournisseur ?? null;
                return array_merge($this->safeUserRef($f), [
                    'product_category' => $pf?->product_category ?? null,
                    'intervale_prix'   => $pf?->intervale_prix ?? null,
                ]);
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
    private function formatCentreReservation($reservation, $role, $centreTx = null)
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
            'payment_method'    => $reservation->payment_method ?? 'wallet',
            'payment_reference' => $reservation->payment_reference ?? null,
            'payment_option'    => $reservation->payment_option ?? null,
            'amount_now'        => $reservation->amount_now ?? null,
            'amount_later'      => $reservation->amount_later ?? null,
            'balance_due_at'    => $reservation->balance_due_at ?? null,
            'discount_amount' => $reservation->discount_amount ?? 0,
            'commission_amount' => $centreTx
                ? (float) $centreTx->commission_amount
                : round(CommissionService::calculateForUser('center', max(0, round((float)($reservation->total_price ?? 0) - (float)($reservation->platform_fee_amount ?? 0), 2)), $reservation->centre_id ?? 0)['commission'], 2),
            'commission_rate' => $centreTx
                ? (float) $centreTx->commission_rate
                : round(CommissionService::calculateForUser('center', 100, $reservation->centre_id ?? 0)['rate'] * 100, 2),
            'nights' => $reservation->nights ?? 1,
            'user' => $this->safeUserRef($reservation->user),
            'centre' => $reservation->centre ? (function($c) {
                $pc = $c->profile?->profileCentre ?? null;
                return [
                    'uuid'            => $c->uuid,
                    'name'            => $pc?->name ?? $c->first_name . ' ' . $c->last_name,
                    'ville'           => $c->ville,
                    'address'         => $c->profile?->address ?? null,
                    'contact_email'   => $pc?->contact_email,
                    'contact_phone'   => $pc?->contact_phone,
                    'manager_name'    => $pc?->manager_name ?? null,
                    'category'        => $pc?->category ?? null,
                    'latitude'        => $pc?->latitude ?? null,
                    'longitude'       => $pc?->longitude ?? null,
                    'capacite'        => $pc?->capacite ?? null,
                    'price_per_night' => $pc?->price_per_night ?? null,
                    'avatar'          => $c->avatar ? storage_url($c->avatar) : null,
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
            'user' => $this->safeUserRef($reservation->user),
            'guide' => $reservation->guide_id ? [
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
     * Returns a safe, public-facing user reference for embedding in reservation responses.
     * Strips numeric id, role_id, and all internal/sensitive fields.
     * Keeps contact info (email, phone) since both parties in a reservation legitimately
     * need to communicate with each other.
     */
    private function safeUserRef(?object $user): ?array
    {
        if (!$user) return null;
        return [
            'uuid'         => $user->uuid,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->email,
            'phone_number' => $user->phone_number,
            'avatar'       => $user->avatar ? storage_url($user->avatar) : null,
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
            'en_attente_paiement'          => 'Pending Payment',
            'confirmée'                    => 'Confirmed',
            'en_attente_validation'        => 'Under Review',
            'refusée'                      => 'Rejected',
            'annulée_par_utilisateur'      => 'Cancelled by User',
            'annulée_par_organisateur'     => 'Cancelled by Organizer',
            'remboursement_en_attente'     => 'Refund Pending',
            'remboursée_partielle'         => 'Partially Refunded',
            'remboursée_totale'            => 'Fully Refunded',
            'modified'                     => 'Modified',
            // Manual payment statuses
            'pending'                      => 'Pending',
            'paiement_soumis'              => 'Transfer Submitted',
            'paiement_invalide'            => 'Transfer Rejected',
            'confirmée_solde_en_attente'   => 'Deposit Confirmed — Balance Due',
            'solde_soumis'                 => 'Balance Transfer Submitted',
            'entièrement_payée'            => 'Fully Paid',
            'annulée_solde_impayé'         => 'Cancelled — Balance Unpaid',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Map materiel status to display label
     */
    private function mapMaterielStatus($status)
    {
        $map = [
            'pending'                    => 'Pending',
            'confirmed'                  => 'Confirmed',
            'paid'                       => 'Paid',
            'retrieved'                  => 'Retrieved',
            'returned'                   => 'Returned',
            'rejected'                   => 'Rejected',
            'canceled'                   => 'Cancelled',
            'cancelled_by_camper'        => 'Cancelled by Camper',
            'cancelled_by_fournisseur'   => 'Cancelled by Supplier',
            'disputed'                   => 'Disputed',
            'modified'                   => 'Modified',
            // Manual payment statuses
            'paiement_soumis'            => 'Transfer Submitted',
            'paiement_invalide'          => 'Transfer Rejected',
            'confirmée_solde_en_attente' => 'Deposit Confirmed — Balance Due',
            'solde_soumis'               => 'Balance Transfer Submitted',
            'entièrement_payée'          => 'Fully Paid',
            'annulée_solde_impayé'       => 'Cancelled — Balance Unpaid',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Map centre status to display label
     */
    private function mapCentreStatus($status)
    {
        $map = [
            'pending'                    => 'Pending',
            'approved'                   => 'Confirmed',
            'modified'                   => 'Modified',
            'rejected'                   => 'Rejected',
            'canceled'                   => 'Cancelled',
            // Manual payment statuses
            'paiement_soumis'            => 'Transfer Submitted',
            'paiement_invalide'          => 'Transfer Rejected',
            'confirmée_solde_en_attente' => 'Deposit Confirmed — Balance Due',
            'solde_soumis'               => 'Balance Transfer Submitted',
            'entièrement_payée'          => 'Fully Paid',
            'annulée_solde_impayé'       => 'Cancelled — Balance Unpaid',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}