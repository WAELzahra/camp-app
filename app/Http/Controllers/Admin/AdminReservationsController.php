<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkReservationActionRequest;
use App\Http\Requests\Admin\CheckMaterialAvailabilityRequest;
use App\Http\Requests\Admin\ExportReservationsRequest;
use App\Http\Requests\Admin\IndexReservationsRequest;
use App\Mail\ReservationStatusUpdated;
use App\Models\Balance;
use App\Models\CampingCentre;
use App\Models\Events;
use App\Models\Materielles;
use App\Models\Payments;
use App\Models\PromoCode;
use App\Models\Reservation_guide;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AdminReservationsController extends Controller
{
    const RESERVATION_TYPES = [
        'center' => Reservations_centre::class,
        'events' => Reservations_events::class,
        'materielle' => Reservations_materielles::class,
        'guides' => Reservation_guide::class,
    ];

    const STATUS_MAPPING = [
        'center' => [
            'pending' => 'En attente',
            'pending_payment' => 'En attente de paiement',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
            'modified' => 'Modifiée',
            'canceled' => 'Annulée',
        ],
        'events' => [
            'en_attente_paiement' => 'En attente de paiement',
            'pending_payment' => 'En attente de paiement',
            'confirmée' => 'Confirmée',
            'en_attente_validation' => 'En attente de validation',
            'refusée' => 'Refusée',
            'annulée_par_utilisateur' => 'Annulée par utilisateur',
            'annulée_par_organisateur' => 'Annulée par organisateur',
            'remboursement_en_attente' => 'Remboursement en attente',
            'remboursée_partielle' => 'Remboursée partielle',
            'remboursée_totale' => 'Remboursée totale',
        ],
        'materielle' => [
            'pending' => 'En attente',
            'pending_payment' => 'En attente de paiement',
            'confirmed' => 'Confirmée',
            'paid' => 'Payée',
            'retrieved' => 'Récupérée',
            'returned' => 'Retournée',
            'rejected' => 'Rejetée',
            'cancelled_by_camper' => 'Annulée par le campeur',
            'cancelled_by_fournisseur' => 'Annulée par le fournisseur',
            'disputed' => 'Litige',
        ],
        'guides' => [
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
            'canceled' => 'Annulée',
        ],
    ];

    /**
     * Liste toutes les réservations (tous types confondus)
     */
    public function index(IndexReservationsRequest $request)
    {

        $type = $request->get('type', 'all');
        $reservations = collect();

        if ($type === 'all' || $type === 'center') {
            $centerReservations = $this->getCenterReservations($request);
            $reservations = $reservations->concat($centerReservations);
        }

        if ($type === 'all' || $type === 'events') {
            $eventReservations = $this->getEventReservations($request);
            $reservations = $reservations->concat($eventReservations);
        }

        if ($type === 'all' || $type === 'materielle') {
            $materialReservations = $this->getMaterialReservations($request);
            $reservations = $reservations->concat($materialReservations);
        }

        if ($type === 'all' || $type === 'guides') {
            $guideReservations = $this->getGuideReservations($request);
            $reservations = $reservations->concat($guideReservations);
        }

        // Tri global
        $sortBy = $request->get('sort_by', 'date_debut');
        $sortDir = $request->get('sort_dir', 'desc');

        $reservations = $reservations->sortBy([
            [$sortBy, $sortDir],
        ])->values();

        // Pagination manuelle
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);
        $paginated = $this->paginateCollection($reservations, $perPage, $page);

        return response()->json([
            'success' => true,
            'data' => $paginated->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $reservations->count(),
                'last_page' => ceil($reservations->count() / $perPage),
                'types' => $this->getReservationCounts(),
            ],
        ]);
    }

    /**
     * Récupère les réservations de centres
     */
    private function getCenterReservations(Request $request)
    {
        $query = Reservations_centre::with(['user', 'serviceItems.service'])
            ->select(
                'id',
                'user_id',
                'centre_id',
                'date_debut',
                'date_fin',
                'nbr_place',
                'nights',
                'note',
                'status',
                'payments_id',
                'total_price',
                'platform_fee_amount',
                'platform_fee_rate',
                'payment_method',
                'discount_amount',
                'promo_code_id',
                'service_count',
                'created_at',
                'updated_at',
                DB::raw("'center' as reservation_type")
            );

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('date_debut', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date_fin', '<=', $request->date_to);
        }

        $items = $query->get();

        $centreIds = $items->pluck('centre_id')->filter()->unique()->toArray();
        $centres = CampingCentre::whereIn('id', $centreIds)->get()->keyBy('id');

        $promoIds = $items->pluck('promo_code_id')->filter()->unique()->toArray();
        $promoCodes = PromoCode::whereIn('id', $promoIds)
            ->get(['id', 'code', 'discount_type', 'discount_value'])
            ->keyBy('id');

        return $items->map(function ($item) use ($centres, $promoCodes) {
            $centre = $centres[$item->centre_id] ?? null;
            $item->centre = $centre ? (object) [
                'id' => $centre->id,
                'name' => $centre->nom,
                'address' => $centre->adresse,
            ] : null;

            $item->display_name = "Réservation Centre #{$item->id}";
            $item->subtitle = 'Centre: '.($centre ? $centre->nom : 'N/A');
            $item->capacity = $item->nbr_place;
            $item->price = (float) ($item->total_price ?? 0);
            $item->code = 'CR'.str_pad($item->id, 3, '0', STR_PAD_LEFT);
            $item->nights_count = $item->nights ?? (
                $item->date_debut && $item->date_fin
                    ? max(1, \Carbon\Carbon::parse($item->date_debut)->diffInDays(\Carbon\Carbon::parse($item->date_fin)))
                    : null
            );
            $item->promo_code = $item->promo_code_id ? ($promoCodes[$item->promo_code_id] ?? null) : null;

            return $item;
        });
    }

    /**
     * Récupère les réservations d'événements
     */
    private function getEventReservations(Request $request)
    {
        $query = Reservations_events::with(['user', 'event', 'group'])
            ->join('events as ev', 'ev.id', '=', 'reservations_events.event_id')
            ->select(
                'reservations_events.id',
                'reservations_events.user_id',
                'reservations_events.group_id',
                'reservations_events.event_id',
                'reservations_events.name',
                'reservations_events.email',
                'reservations_events.phone',
                'reservations_events.nbr_place',
                'reservations_events.status',
                'reservations_events.payment_id',
                'reservations_events.discount_amount',
                'reservations_events.platform_fee_amount',
                'reservations_events.platform_fee_rate',
                'reservations_events.payment_method',
                'reservations_events.promo_code_id',
                'reservations_events.created_at',
                'reservations_events.updated_at',
                DB::raw("'events' as reservation_type"),
                'ev.start_date as date_debut',
                'ev.end_date as date_fin',
                'ev.price as event_price_per_person'
            );

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('reservations_events.status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('reservations_events.user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reservations_events.name', 'like', "%{$search}%")
                    ->orWhere('reservations_events.email', 'like', "%{$search}%")
                    ->orWhere('reservations_events.phone', 'like', "%{$search}%")
                    ->orWhere('ev.title', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('ev.start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('ev.end_date', '<=', $request->date_to);
        }

        $items = $query->get();

        $promoIds = $items->pluck('promo_code_id')->filter()->unique()->toArray();
        $promoCodes = PromoCode::whereIn('id', $promoIds)
            ->get(['id', 'code', 'discount_type', 'discount_value'])
            ->keyBy('id');

        return $items->map(function ($item) use ($promoCodes) {
            $item->display_name = $item->name ?? "Réservation Événement #{$item->id}";
            $item->subtitle = 'Événement: '.($item->event->title ?? 'N/A');
            $item->capacity = $item->nbr_place;

            $gross = (float) ($item->event_price_per_person ?? 0) * (int) $item->nbr_place;
            $discount = (float) ($item->discount_amount ?? 0);
            $fee = (float) ($item->platform_fee_amount ?? 0);
            $item->price = max(0, round($gross - $discount + $fee, 2));

            $item->code = 'ER'.str_pad($item->id, 3, '0', STR_PAD_LEFT);
            $item->promo_code = $item->promo_code_id ? ($promoCodes[$item->promo_code_id] ?? null) : null;

            return $item;
        });
    }

    /**
     * Récupère les réservations de matériel (CORRIGÉ)
     */
    private function getMaterialReservations(Request $request)
    {
        $query = Reservations_materielles::with(['user', 'fournisseur', 'materielle'])
            ->select(
                'id',
                'user_id',
                'fournisseur_id',
                'materielle_id',
                'type_reservation',
                'date_debut',
                'date_fin',
                'quantite',
                'montant_total',
                'platform_fee_amount',
                'platform_fee_rate',
                'payment_method',
                'discount_amount',
                'promo_code_id',
                'frais_livraison',
                'mode_livraison',
                'status',
                'pin_used_at',
                'confirmed_at',
                'retrieved_at',
                'created_at',
                'updated_at',
                DB::raw("'materielle' as reservation_type")
            );

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('materielle', function ($mq) use ($search) {
                        $mq->where('nom', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('date_debut', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date_fin', '<=', $request->date_to);
        }

        $items = $query->get();

        $promoIds = $items->pluck('promo_code_id')->filter()->unique()->toArray();
        $promoCodes = PromoCode::whereIn('id', $promoIds)
            ->get(['id', 'code', 'discount_type', 'discount_value'])
            ->keyBy('id');

        return $items->map(function ($item) use ($promoCodes) {
            // Compute supplier display name from User first_name/last_name
            if ($item->fournisseur) {
                $fn = trim(($item->fournisseur->first_name ?? '').' '.($item->fournisseur->last_name ?? ''));
                $item->fournisseur->name = $fn ?: ($item->fournisseur->email ?? 'Unknown');
                $item->fournisseur->email = $item->fournisseur->email ?? null;
                $item->fournisseur->phone = $item->fournisseur->phone_number ?? $item->fournisseur->phone ?? null;
            }

            $item->display_name = "Réservation Matériel #{$item->id}";
            $item->subtitle = 'Matériel: '.($item->materielle->nom ?? 'N/A')
                .' ('.ucfirst($item->type_reservation ?? 'location').')';
            $item->capacity = $item->quantite;
            $item->price = $item->montant_total ?? 0;
            $item->code = 'MR'.str_pad($item->id, 3, '0', STR_PAD_LEFT);
            $item->promo_code = $item->promo_code_id ? ($promoCodes[$item->promo_code_id] ?? null) : null;
            // PIN status for admin display (never expose the hashed pin_code itself)
            $item->pin_status = match (true) {
                $item->pin_used_at !== null => 'used',
                in_array($item->status, ['confirmed', 'paid']) => 'generated',
                default => 'not_generated',
            };

            return $item;
        });
    }

    /**
     * Récupère les réservations de guides (CORRIGÉ)
     */
    private function getGuideReservations(Request $request)
    {
        $query = Reservation_guide::with(['user', 'circuit', 'guide'])
            ->select(
                'id',
                'reserver_id as user_id',
                'guide_id',
                'circuit_id',
                'creation_date as date_debut',
                'creation_date as date_fin',
                'type',
                'discription as note',
                'status',
                'created_at',
                'updated_at',
                DB::raw("'guides' as reservation_type")
            );

        // Filtres
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('reserver_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('discription', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('circuit', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('creation_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('creation_date', '<=', $request->date_to);
        }

        return $query->get()->map(function ($item) {
            $item->display_name = "Réservation Guide #{$item->id}";
            $item->subtitle = 'Circuit: '.($item->circuit->name ?? 'N/A');
            $item->capacity = 1;
            $item->price = 0;
            $item->code = 'GR'.str_pad($item->id, 3, '0', STR_PAD_LEFT);

            return $item;
        });
    }

    /**
     * Récupère les détails d'une réservation spécifique
     */
    public function show($type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide',
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];
        $relations = $this->getRelationsForType($type);

        try {
            $reservation = $modelClass::with($relations)->findOrFail($id);
            $reservation = $this->enrichReservation($reservation, $type);

            return response()->json([
                'success' => true,
                'data' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée',
            ], 404);
        }
    }

    /**
     * Met à jour une réservation
     */
    public function update(Request $request, $type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide',
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];

        try {
            $reservation = $modelClass::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée',
            ], 404);
        }

        if (!$this->canModifyReservation($reservation, $type)) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à modifier cette réservation',
            ], 403);
        }

        $validator = $this->getValidatorForType($request, $type, $reservation);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $rejectedStatuses = [
            'center' => ['rejected', 'canceled'],
            'events' => ['refusée', 'annulée_par_organisateur', 'annulée_par_utilisateur'],
            'materielle' => ['rejected', 'cancelled_by_fournisseur', 'cancelled_by_camper'],
            'guides' => ['rejected', 'canceled'],
        ];

        DB::beginTransaction();

        try {
            $oldStatus = $reservation->status;
            $data = $validator->validated();

            if ($type === 'events' && isset($data['nbr_place']) && $data['nbr_place'] != $reservation->nbr_place) {
                $this->handleEventPlaceChange($reservation, $data['nbr_place']);
            }

            if ($request->action === 'cancel' && $type === 'events') {
                $this->restoreEventPlaces($reservation);
            }

            $reservation->update($data);
            $newStatus = $reservation->status;

            if ($oldStatus !== $newStatus && $this->shouldSendEmail($reservation, $type)) {
                $this->sendStatusUpdateEmail($reservation, $type, $oldStatus);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }

        // Refund wallet escrow after commit if status changed to a rejection/cancellation
        $newStatus = $reservation->status;
        $isRefundStatus = in_array($newStatus, $rejectedStatuses[$type] ?? []);
        if ($isRefundStatus) {
            $this->processAdminRefund($reservation, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réservation mise à jour avec succès',
            'data' => $reservation->fresh($this->getRelationsForType($type)),
        ]);
    }

    /**
     * Issue a full wallet refund to the camper when admin rejects or cancels a reservation.
     * Must be called AFTER DB::commit() so wallet operations are independent of the status transaction.
     */
    private function processAdminRefund($reservation, string $type): void
    {
        $refTypeMap = [
            'center' => 'centre_reservation',
            'events' => 'event_reservation',
            'materielle' => 'materiel_reservation',
            'guides' => null,
        ];

        $referenceType = $refTypeMap[$type] ?? null;
        if (!$referenceType) {
            return; // guides do not use wallet escrow
        }

        $userId = $reservation->user_id ?? $reservation->reserver_id ?? null;
        if (!$userId) {
            \Log::warning('Admin refund skipped: no user_id', ['type' => $type, 'reservation_id' => $reservation->id]);

            return;
        }

        // Guard against double-refund — check for an existing refund credit for this reservation
        $alreadyRefunded = WalletTransaction::where('user_id', $userId)
            ->where('type', 'credit')
            ->where('category', 'refund_in')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $reservation->id)
            ->exists();

        if ($alreadyRefunded) {
            \Log::info('Admin refund skipped: already refunded', ['type' => $type, 'reservation_id' => $reservation->id]);

            return;
        }

        // Find the original escrow debit — only present for wallet payments
        $escrowTx = WalletTransaction::where('user_id', $userId)
            ->where('type', 'debit')
            ->where('category', 'reservation_payment')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $reservation->id)
            ->first();

        if (!$escrowTx) {
            \Log::info('Admin refund skipped: no escrow debit found (non-wallet payment or escrow already released)', [
                'type' => $type, 'reservation_id' => $reservation->id,
                'user_id' => $userId, 'reference_type' => $referenceType,
                'payment_method' => $reservation->payment_method ?? 'unknown',
            ]);

            return;
        }

        $amount = (float) $escrowTx->amount_gross;
        if ($amount <= 0) {
            \Log::info('Admin refund skipped: zero amount in escrow tx', ['type' => $type, 'reservation_id' => $reservation->id]);

            return;
        }

        try {
            Balance::forUser($userId)->refundEscrow($amount);
            WalletTransaction::logCredit(
                $userId, 'refund_in',
                $amount, 0, 0, $amount,
                $referenceType, $reservation->id,
                "Remboursement admin — réservation #{$reservation->id}",
                Auth::id()
            );
            \Log::info('Admin refund issued', [
                'type' => $type,
                'reservation_id' => $reservation->id,
                'amount' => $amount,
                'user_id' => $userId,
                'admin_id' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Admin refund failed', [
                'type' => $type,
                'reservation_id' => $reservation->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Actions groupées sur les réservations
     */
    public function bulkAction(BulkReservationActionRequest $request)
    {

        $modelClass = self::RESERVATION_TYPES[$request->type];
        $results = [
            'success' => [],
            'failed' => [],
        ];

        // Collected after the loop so wallet refunds run OUTSIDE the DB transaction
        $needsRefund = [];

        DB::beginTransaction();

        try {
            foreach ($request->ids as $id) {
                try {
                    $reservation = $modelClass::find($id);

                    if (!$reservation) {
                        $results['failed'][] = ['id' => $id, 'reason' => 'Réservation non trouvée'];

                        continue;
                    }

                    if (!$this->canModifyReservation($reservation, $request->type)) {
                        $results['failed'][] = ['id' => $id, 'reason' => 'Non autorisé'];

                        continue;
                    }

                    $oldStatus = $reservation->status;

                    switch ($request->action) {
                        case 'approve':
                            $reservation->status = $this->getApprovedStatus($request->type);
                            break;
                        case 'reject':
                            $reservation->status = $this->getRejectedStatus($request->type);
                            break;
                        case 'pending':
                            $reservation->status = $this->getPendingStatus($request->type);
                            break;
                        case 'cancel':
                            $reservation->status = $this->getCanceledStatus($request->type);
                            break;
                        case 'delete':
                            $reservation->delete();
                            $results['success'][] = ['id' => $id, 'action' => 'deleted'];

                            continue 2;
                    }

                    $reservation->save();

                    if ($request->action === 'cancel' && $request->type === 'events') {
                        $this->restoreEventPlaces($reservation);
                    }

                    // Queue for refund — processed after DB::commit() so wallet ops are independent
                    if (in_array($request->action, ['reject', 'cancel'])) {
                        $needsRefund[] = [$reservation, $request->type];
                    }

                    if ($this->shouldSendEmail($reservation, $request->type)) {
                        $this->sendStatusUpdateEmail($reservation, $request->type, $oldStatus);
                    }

                    $results['success'][] = ['id' => $id, 'status' => $reservation->status];

                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Processing failed.'];
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors des actions groupées: '.$e->getMessage(),
            ], 500);
        }

        // Process wallet refunds OUTSIDE the transaction — status changes are already committed
        foreach ($needsRefund as [$reservation, $type]) {
            $this->processAdminRefund($reservation, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Actions groupées effectuées',
            'data' => $results,
        ]);
    }

    /**
     * Supprime une réservation
     */
    public function destroy($type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide',
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];

        try {
            $reservation = $modelClass::findOrFail($id);

            if (!$this->canModifyReservation($reservation, $type)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé à supprimer cette réservation',
                ], 403);
            }

            if ($type === 'events' && in_array($reservation->status, ['confirmée', 'en_attente_validation'])) {
                $this->restoreEventPlaces($reservation);
            }

            $reservation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Réservation supprimée avec succès',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée',
            ], 404);
        }
    }

    /**
     * Statistiques des réservations
     */
    public function stats(Request $request)
    {
        $stats = [];

        foreach (self::RESERVATION_TYPES as $type => $modelClass) {
            $baseQuery = function () use ($modelClass, $request) {
                $q = $modelClass::query();
                if ($request->has('date_from')) {
                    $q->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->has('date_to')) {
                    $q->whereDate('created_at', '<=', $request->date_to);
                }

                return $q;
            };

            $stats[$type] = [
                'total' => $baseQuery()->count(),
                'by_status' => $baseQuery()
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status'),
                'recent' => $baseQuery()
                    ->whereDate('created_at', '>=', now()->subDays(7))
                    ->count(),
            ];
        }

        $stats['total_all'] = array_sum(array_column($stats, 'total'));
        $stats['recent_all'] = array_sum(array_column($stats, 'recent'));

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Export des réservations
     */
    public function export(ExportReservationsRequest $request)
    {

        $reservations = $this->getReservationsForExport($request);

        if ($request->format === 'csv') {
            return $this->exportToCsv($reservations);
        } else {
            return $this->exportToExcel($reservations);
        }
    }

    /**
     * Vérifier la disponibilité du matériel
     */
    public function checkMaterialAvailability(CheckMaterialAvailabilityRequest $request, $materialId)
    {

        $materiel = Materielles::findOrFail($materialId);

        $reservationsExistantes = Reservations_materielles::where('materielle_id', $materialId)
            ->whereIn('status', ['confirmed', 'paid', 'retrieved'])
            ->where(function ($q) use ($request) {
                $q->whereBetween('date_debut', [$request->date_debut, $request->date_fin])
                    ->orWhereBetween('date_fin', [$request->date_debut, $request->date_fin]);
            })
            ->sum('quantite');

        $disponible = ($materiel->quantite_dispo - $reservationsExistantes) >= $request->quantite;

        return response()->json([
            'success' => true,
            'material' => $materiel->nom,
            'disponible' => $disponible,
            'quantite_disponible' => $materiel->quantite_dispo - $reservationsExistantes,
            'quantite_demandee' => $request->quantite,
        ]);
    }

    /**
     * Méthodes utilitaires privées
     */
    private function getRelationsForType($type)
    {
        $relations = [
            'center' => ['user', 'centre', 'serviceItems.service.category'],
            'events' => ['user', 'event', 'group'],
            'materielle' => ['user', 'fournisseur', 'materielle'],
            'guides' => ['user', 'circuit', 'guide'],
        ];

        return $relations[$type] ?? [];
    }

    private function enrichReservation($reservation, $type)
    {
        switch ($type) {
            case 'center':
                $reservation->total_price = $reservation->total_price ?? ($reservation->calculateTotal() ?? 0);
                $reservation->service_types = $reservation->serviceTypes ?? null;
                $reservation->services_by_category = $reservation->servicesByCategory ?? null;
                break;
            case 'events':
                $reservation->event_title = $reservation->event->title ?? null;
                $reservation->organizer = $reservation->group->name ?? null;
                break;
            case 'materielle':
                $reservation->material_name = $reservation->materielle->nom ?? null;
                $reservation->supplier_name = $reservation->fournisseur->name ?? null;
                $reservation->material_type = $reservation->materielle->type ?? null;
                $reservation->tarif_nuit = $reservation->materielle->tarif_nuit ?? null;
                break;
            case 'guides':
                $reservation->circuit_name = $reservation->circuit->name ?? null;
                $reservation->guide_name = User::find($reservation->guide_id)->name ?? null;
                break;
        }

        $reservation->status_label = $this->getStatusLabel($type, $reservation->status);

        return $reservation;
    }

    private function getStatusLabel($type, $status)
    {
        return self::STATUS_MAPPING[$type][$status] ?? $status;
    }

    private function getValidatorForType(Request $request, $type, $reservation)
    {
        $rules = [
            'center' => [
                'status' => 'sometimes|in:pending,approved,rejected,modified,canceled',
                'nbr_place' => 'sometimes|integer|min:1',
                'date_debut' => 'sometimes|date',
                'date_fin' => 'sometimes|date|after_or_equal:date_debut',
                'note' => 'nullable|string',
            ],
            'events' => [
                'status' => 'sometimes|in:en_attente_paiement,confirmée,en_attente_validation,refusée,annulée_par_utilisateur,annulée_par_organisateur,remboursement_en_attente,remboursée_partielle,remboursée_totale',
                'nbr_place' => 'sometimes|integer|min:1',
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email',
                'phone' => 'sometimes|string',
            ],
            'materielle' => [
                'status' => 'sometimes|in:pending,confirmed,paid,retrieved,returned,rejected,cancelled_by_camper,cancelled_by_fournisseur,disputed',
                'quantite' => 'sometimes|integer|min:1',
                'date_debut' => 'sometimes|date',
                'date_fin' => 'sometimes|date|after_or_equal:date_debut',
                'montant_total' => 'sometimes|numeric|min:0',
            ],
            'guides' => [
                'status' => 'sometimes|in:pending,approved,rejected,canceled',
                'type' => 'sometimes|string',
                'discription' => 'nullable|string',
            ],
        ];

        return Validator::make($request->all(), $rules[$type]);
    }

    private function canModifyReservation($reservation, $type)
    {
        $user = Auth::user();

        // Admin always allowed (role_id = 6)
        if ($user && $user->role_id === 6) {
            return true;
        }

        switch ($type) {
            case 'center':
                return isset($reservation->user_id) && $reservation->user_id === ($user->id ?? null);
            case 'events':
                return isset($reservation->user_id) && $reservation->user_id === ($user->id ?? null);
            case 'materielle':
                return isset($reservation->user_id) && $reservation->user_id === ($user->id ?? null);
            case 'guides':
                return isset($reservation->reserver_id) && $reservation->reserver_id === ($user->id ?? null);
            default:
                return false;
        }
    }

    private function handleEventPlaceChange($reservation, $newPlaces)
    {
        $event = Events::findOrFail($reservation->event_id);
        $diff = $newPlaces - $reservation->nbr_place;

        if ($diff > 0 && $event->nbr_place_restante < $diff) {
            throw new \Exception('Pas assez de places disponibles');
        }

        $event->nbr_place_restante -= $diff;
        $event->save();
    }

    private function restoreEventPlaces($reservation)
    {
        $event = Events::find($reservation->event_id);
        if ($event) {
            $event->nbr_place_restante += $reservation->nbr_place;
            $event->save();
        }
    }

    /**
     * Envoie un email de confirmation
     */
    public function sendConfirmationEmail(Request $request, $type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide',
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];

        try {
            $reservation = $modelClass::with($this->getRelationsForType($type))->findOrFail($id);

            $email = null;
            if ($type === 'events' && isset($reservation->email)) {
                $email = $reservation->email;
            } elseif (isset($reservation->user) && isset($reservation->user->email)) {
                $email = $reservation->user->email;
            }

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun email associé à cette réservation',
                ], 400);
            }

            Mail::to($email)->send(new ReservationStatusUpdated($reservation, $type));

            return response()->json([
                'success' => true,
                'message' => 'Confirmation email sent successfully',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Generate an emergency admin override PIN for a materielle reservation.
     * Used when the camper's PIN is unavailable (app crash, no phone, etc.).
     * The PIN is shown once in the response and is never stored in plaintext.
     */
    public function generateMasterPin(int $id)
    {
        $reservation = Reservations_materielles::find($id);

        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Reservation not found'], 404);
        }

        if (!in_array($reservation->status, ['confirmed', 'paid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency PIN can only be generated for confirmed reservations awaiting pickup.',
            ], 400);
        }

        $rawPin = $reservation->generateAdminPin();

        \Log::warning('Admin generated emergency master PIN', [
            'admin_id' => Auth::id(),
            'reservation_id' => $id,
            'camper_id' => $reservation->user_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Emergency PIN generated. Show this to the supplier — it will not be shown again.',
            'pin' => $rawPin,
        ]);
    }

    /**
     * Crée une nouvelle réservation
     */
    public function store(Request $request, $type)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide',
            ], 400);
        }

        $rules = [
            'center' => [
                'user_id' => 'required|exists:users,id',
                'centre_id' => 'required|exists:centres,id',
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
                'nbr_place' => 'required|integer|min:1',
                'status' => 'sometimes|in:pending,approved,rejected,modified,canceled',
                'note' => 'nullable|string',
                'payments_id' => 'nullable|exists:payments,id',
            ],
            'events' => [
                'user_id' => 'required|exists:users,id',
                'event_id' => 'required|exists:events,id',
                'group_id' => 'required|exists:groups,id',
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'nbr_place' => 'required|integer|min:1',
                'status' => 'sometimes|in:en_attente_paiement,confirmée,en_attente_validation,refusée,annulée_par_utilisateur,annulée_par_organisateur,remboursement_en_attente,remboursée_partielle,remboursée_totale',
                'payment_id' => 'nullable|exists:payments,id',
                'created_by' => 'nullable|exists:users,id',
            ],
            'materielle' => [
                'user_id' => 'required|exists:users,id',
                'materielle_id' => 'required|exists:materielles,id',
                'fournisseur_id' => 'required|exists:fournisseurs,id',
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
                'quantite' => 'required|integer|min:1',
                'montant_payer' => 'required|numeric|min:0',
                'status' => 'sometimes|in:pending,approved,rejected,canceled',
                'payments_id' => 'nullable|exists:payments,id',
            ],
            'guides' => [
                'user_id' => 'required|exists:users,id',
                'guide_id' => 'required|exists:users,id',
                'circuit_id' => 'required|exists:circuits,id',
                'date_debut' => 'required|date',
                'date_fin' => 'nullable|date|after_or_equal:date_debut',
                'type' => 'nullable|string|max:255',
                'discription' => 'nullable|string',
                'status' => 'sometimes|in:pending,approved,rejected,canceled',
            ],
        ];

        $validator = Validator::make($request->all(), $rules[$type]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            if ($type === 'guides') {
                // Map frontend field names to actual DB column names
                $guideData = [
                    'reserver_id' => $data['user_id'],
                    'guide_id' => $data['guide_id'],
                    'circuit_id' => $data['circuit_id'],
                    'creation_date' => $data['date_debut'],
                    'type' => $data['type'] ?? null,
                    'discription' => $data['discription'] ?? null,
                ];
                // Add status if the column exists
                if (isset($data['status'])) {
                    $guideData['status'] = $data['status'];
                }
                $reservation = Reservation_guide::create($guideData);
            } elseif ($type === 'events') {
                if (!isset($data['status'])) {
                    $data['status'] = 'en_attente_paiement';
                }
                // Check available places
                $event = Events::findOrFail($data['event_id']);
                if ($event->nbr_place_restante < $data['nbr_place']) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Pas assez de places disponibles pour cet événement',
                    ], 422);
                }
                $event->nbr_place_restante -= $data['nbr_place'];
                $event->save();

                $reservation = Reservations_events::create($data);
            } else {
                if (!isset($data['status'])) {
                    $data['status'] = 'pending';
                }
                $modelClass = self::RESERVATION_TYPES[$type];
                $reservation = $modelClass::create($data);
            }

            DB::commit();

            $relations = $this->getRelationsForType($type);
            $reservation->load($relations);
            $reservation = $this->enrichReservation($reservation, $type);

            return response()->json([
                'success' => true,
                'message' => 'Réservation créée avec succès',
                'data' => $reservation,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }

    private function shouldSendEmail($reservation, $type)
    {
        $email = null;
        if ($type === 'events' && isset($reservation->email)) {
            $email = $reservation->email;
        } elseif (isset($reservation->user) && isset($reservation->user->email)) {
            $email = $reservation->user->email;
        }

        return $email && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    private function sendStatusUpdateEmail($reservation, $type, $oldStatus)
    {
        try {
            $email = null;
            if ($type === 'events' && isset($reservation->email)) {
                $email = $reservation->email;
            } elseif (isset($reservation->user) && isset($reservation->user->email)) {
                $email = $reservation->user->email;
            }

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($email)->send(new ReservationStatusUpdated(
                    $reservation,
                    $type,
                    $oldStatus
                ));
            }
        } catch (\Throwable $e) {
            // Never let a mail failure crash the HTTP response
            \Log::error("Reservation status email failed [{$type}#{$reservation->id}]: ".$e->getMessage());
        }
    }

    private function getApprovedStatus($type)
    {
        return [
            'center' => 'approved',
            'events' => 'confirmée',
            'materielle' => 'confirmed',   // DB enum: confirmed (not approved)
            'guides' => 'approved',
        ][$type];
    }

    private function getRejectedStatus($type)
    {
        return [
            'center' => 'rejected',
            'events' => 'refusée',
            'materielle' => 'rejected',
            'guides' => 'rejected',
        ][$type];
    }

    private function getPendingStatus($type)
    {
        return [
            'center' => 'pending',
            'events' => 'en_attente_validation',
            'materielle' => 'pending',
            'guides' => 'pending',
        ][$type];
    }

    private function getCanceledStatus($type)
    {
        return [
            'center' => 'canceled',
            'events' => 'annulée_par_organisateur',
            'materielle' => 'cancelled_by_fournisseur',  // admin cancels on behalf of supplier
            'guides' => 'canceled',
        ][$type];
    }

    private function paginateCollection($collection, $perPage, $page)
    {
        return $collection->slice(($page - 1) * $perPage, $perPage);
    }

    private function getReservationCounts()
    {
        return [
            'center' => Reservations_centre::count(),
            'events' => Reservations_events::count(),
            'materielle' => Reservations_materielles::count(),
            'guides' => Reservation_guide::count(),
        ];
    }

    private function getReservationsForExport(Request $request)
    {
        $type = $request->type;

        if ($type === 'all') {
            $reservations = collect();
            foreach (['center', 'events', 'materielle', 'guides'] as $t) {
                $method = 'get'.ucfirst($t).'Reservations';
                if (method_exists($this, $method)) {
                    $reservations = $reservations->concat($this->$method($request));
                }
            }

            return $reservations;
        }

        $method = 'get'.ucfirst($type).'Reservations';

        return method_exists($this, $method) ? $this->$method($request) : collect();
    }

    private function exportToCsv($reservations)
    {
        $filename = 'reservations_'.date('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($reservations) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'Type',
                'Code',
                'Titre',
                'Client',
                'Email',
                'Date début',
                'Date fin',
                'Capacité',
                'Prix',
                'Statut',
                'Date création',
            ]);

            foreach ($reservations as $r) {
                $clientName = 'N/A';
                $clientEmail = 'N/A';

                if (isset($r->user) && isset($r->user->name)) {
                    $clientName = $r->user->name;
                    $clientEmail = $r->user->email ?? 'N/A';
                } elseif (isset($r->name)) {
                    $clientName = $r->name;
                    $clientEmail = $r->email ?? 'N/A';
                }

                fputcsv($file, [
                    $r->id,
                    $r->reservation_type ?? 'N/A',
                    $r->code ?? 'N/A',
                    $r->display_name ?? 'N/A',
                    $clientName,
                    $clientEmail,
                    $r->date_debut ?? 'N/A',
                    $r->date_fin ?? 'N/A',
                    $r->capacity ?? $r->nbr_place ?? $r->quantite ?? 'N/A',
                    $r->price ?? $r->montant_payer ?? $r->total_price ?? '0',
                    $r->status ?? 'N/A',
                    isset($r->created_at) ? $r->created_at->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportToExcel($reservations)
    {
        return $this->exportToCsv($reservations);
    }
}
