<?php

namespace App\Http\Controllers\Reservation;

use App\Models\Reservations_events;
use App\Models\Events;
use App\Models\Payments;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReservationStatusUpdated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Controllers\Controller;
use App\Mail\EventReservationCanceledNotifyOwner;
use App\Mail\EventReservationCreated;
use App\Mail\EventReservationStatusChanged;
use App\Mail\EventReservationCanceledByGroup;
use App\Models\Balance;
use App\Models\WalletTransaction;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;


class ReservationEventController extends Controller
{
    /**
     * Lister les participants d'un événement avec option de filtrage par statut.
     */
    public function participants($eventId, Request $request)
    {
        $event = Events::find($eventId);
        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        $status = $request->query('status');

        $query = Reservations_events::with('user')->where('event_id', $eventId);

        if ($status) {
            $query->where('status', $status);
        }

        $participants = $query->get();

        return response()->json([
            'event_id' => $eventId,
            'event_title' => $event->title,
            'participants' => $participants,
        ]);
    }
    /**
     * Get all participations for the authenticated user (camper)
     */
    public function myParticipations(Request $request)
    {
        $user = Auth::user();
        
        // Only campers (role_id = 1) can access their participations
        if ($user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Only campers can access their participations',
                'data' => ['data' => []]
            ], 200);
        }

        try {
            $query = Reservations_events::where('user_id', $user->id)
                ->with(['event' => function($q) {
                    $q->with('photos');
                }]);

            // Optional filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('event_type') && $request->event_type) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('event_type', $request->event_type);
                });
            }

            // Sort options
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $participations = $query->paginate($request->get('per_page', 15));

            // Transform events to include cover_image
            $participations->getCollection()->transform(function($participation) {
                if ($participation->event && $participation->event->photos->isNotEmpty()) {
                    $coverPhoto = $participation->event->photos->firstWhere('is_cover', true);
                    if ($coverPhoto) {
                        $participation->event->cover_image = $coverPhoto->url;
                    } else {
                        $participation->event->cover_image = $participation->event->photos->first()->url;
                    }
                }
                return $participation;
            });

            return response()->json([
                'success' => true,
                'data' => $participations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch participations: ' . $e->getMessage(),
                'data' => ['data' => []]
            ], 500);
        }
    }
    public function cancelByUser(Request $request, $id)
    {
        $user = Auth::user();

        // Find the reservation — must belong to this user
        $reservation = \App\Models\Reservations_events::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Only allow cancellation on active statuses
        $cancellableStatuses = ['en_attente_paiement', 'confirmée', 'en_attente_validation'];
        if (!in_array($reservation->status, $cancellableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'This reservation cannot be canceled in its current status.',
            ], 422);
        }

        // Load the event
        $event = \App\Models\Events::findOrFail($reservation->event_id);

        // ── Fee calculation using dynamic cancellation policy ───────────────────
        // $totalPrice = base price (nbr_place × event->price), without platform fee
        // $gross = what camper actually paid (base + platform fee)
        $totalPrice     = $reservation->nbr_place * $event->price;
        $platformFeeAmt = (float) ($reservation->platform_fee_amount ?? 0);
        $gross          = round($totalPrice + $platformFeeAmt, 2);
        $startDate      = \Carbon\Carbon::parse($event->start_date);

        // Policy applies to gross (total paid), consistent with centre & equipment
        $feeCalc                = CancellationPolicyService::preview('event', $startDate, $gross, null, \Carbon\Carbon::parse($reservation->created_at));
        $cancellationFeePercent = $feeCalc ? $feeCalc['fee_percentage'] : 0;
        $cancellationFee        = $feeCalc ? $feeCalc['fee_amount']     : 0;
        $refundAmount           = $feeCalc ? $feeCalc['refund_amount']  : $gross;
        $processingFee          = 0;
        $isLate                 = $feeCalc ? ($feeCalc['hours_remaining'] < 24) : false;

        // ── Wallet refund — only if the reservation was already confirmed (money was taken) ──
        if ($reservation->payment_method === 'wallet' && $reservation->status === 'confirmée') {
            // Organizer net_revenue: commission is taken from the base price (not gross)
            $calc          = \App\Services\CommissionService::calculate('group', $totalPrice);
            $orgNetRevenue = $calc['net_revenue'];

            // Platform cancellation fee reduces camper's refund
            $platformCancFee = \App\Models\PlatformCancellationFee::feeAmount('camper', $gross);
            $actualRefund    = max(0, round($refundAmount - $platformCancFee, 2));
            $cancFeeForOrg   = round($gross - $refundAmount, 2); // policy fee the organizer keeps

            DB::beginTransaction();
            try {
                // Claw back from organizer exactly what they received
                Balance::forUser($event->group_id)->debiter($orgNetRevenue);
                WalletTransaction::logDebit(
                    $event->group_id, 'refund_out', $orgNetRevenue,
                    'event_reservation', $reservation->id,
                    "Remboursement annulation camper — réservation #{$reservation->id}",
                    $user->id
                );

                // Credit camper (refund minus platform cancellation fee)
                if ($actualRefund > 0) {
                    Balance::forUser($user->id)->crediter($actualRefund);
                    WalletTransaction::logCredit(
                        $user->id, 'refund_in', $actualRefund, 0, 0, $actualRefund,
                        'event_reservation', $reservation->id,
                        "Remboursement annulation — réservation #{$reservation->id}",
                        $event->group_id
                    );
                }

                // Credit organizer their cancellation fee (policy fee they retain)
                if ($cancFeeForOrg > 0) {
                    Balance::forUser($event->group_id)->crediter($cancFeeForOrg);
                    WalletTransaction::logCredit(
                        $event->group_id, 'reservation_income', $cancFeeForOrg, 0, 0, $cancFeeForOrg,
                        'event_reservation', $reservation->id,
                        "Frais d'annulation — réservation #{$reservation->id}",
                        $user->id
                    );
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Event cancellation wallet refund failed: ' . $e->getMessage());
            }
        }
        // If status was en_attente_validation, no money was ever taken — nothing to refund

        // ── Update reservation status ────────────────────────────────────────────
        $reservation->update([
            'status'     => 'annulée_par_utilisateur',
            'updated_at' => now(),
        ]);

        // ── Restore remaining spots on the event ────────────────────────────────
        $event->increment('remaining_spots', $reservation->nbr_place);

        // ── Send email to camper ─────────────────────────────────────────────────
        try {
            Mail::to($user->email)->send(new \App\Mail\EventReservationCanceledByUser(
                user:            $user,
                event:           $event,
                reservation:     $reservation,
                refundAmount:    $refundAmount,
                cancellationFee: $cancellationFee,
                processingFee:   $processingFee,
            ));
        } catch (\Exception $e) {
            \Log::error('Failed to send cancellation email to user: ' . $e->getMessage());
        }

        // ── Send email to event owner ────────────────────────────────────────────
        try {
            $owner = \App\Models\User::find($event->group_id);
            if ($owner) {
                Mail::to($owner->email)->send(new \App\Mail\EventReservationCanceledNotifyOwner(
                    owner:       $owner,
                    user:        $user,
                    event:       $event,
                    reservation: $reservation,
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send cancellation notification to owner: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservation canceled successfully.',
            'data'    => [
                'reservation_id'          => $reservation->id,
                'status'                  => 'annulée_par_utilisateur',
                'total_price'             => $totalPrice,
                'cancellation_fee'        => $cancellationFee,
                'cancellation_fee_percent'=> $cancellationFeePercent,
                'processing_fee'          => $processingFee,
                'refund_amount'           => $refundAmount,
                'is_late_cancellation'    => $isLate,
            ],
        ]);
    }
    /**
     * Statistiques du nombre de participants par statut pour un événement.
     */
    public function participantStats($eventId)
    {
        $event = Events::find($eventId);
        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        $stats = Reservations_events::where('event_id', $eventId)
            ->select('status', \DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'event_id' => $eventId,
            'event_title' => $event->title,
            'stats' => $stats,
        ]);
    }

    /**
     * Ajouter manuellement un participant à un événement.
     */
    public function addManualParticipant(Request $request, $eventId)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'nullable|email|max:255',
            'phone'     => 'nullable|string|max:50',
            'nbr_place' => 'required|integer|min:1',
            'status'    => 'nullable|in:confirmée,en_attente_paiement,en_attente_validation',
        ]);

        $event = Events::findOrFail($eventId);

        $remaining = $event->remaining_spots ?? $event->nbr_place_restante ?? 0;
        if ($remaining < $request->nbr_place) {
            return response()->json(['message' => 'Not enough spots available for this event.'], 400);
        }

        $reservation = Reservations_events::create([
            'event_id'   => $eventId,
            'group_id'   => auth()->id(),
            'name'       => $request->name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'nbr_place'  => $request->nbr_place,
            'status'     => $request->status ?? 'confirmée',
            'created_by' => auth()->id(),
        ]);

        // Decrement whichever field the Events model exposes
        if (isset($event->remaining_spots)) {
            $event->decrement('remaining_spots', $request->nbr_place);
        } else {
            $event->nbr_place_restante -= $request->nbr_place;
            $event->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Participant added successfully.',
            'data'    => $reservation,
        ]);
    }

    /**
     * Supprimer une réservation/participant.
     */
    public function destroy($reservationId)
    {
        $reservation = Reservations_events::find($reservationId);

        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable'], 404);
        }

        $reservation->delete();

        return response()->json(['message' => 'Réservation supprimée avec succès']);
    }

    /**
     * Recherche avancée de participants sur un événement.
     */
    public function search(Request $request, $eventId)
    {
        $query = Reservations_events::where('event_id', $eventId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'message' => 'Aucun participant trouvé pour cet événement avec les critères spécifiés.'
            ], 404);
        }

        return response()->json($results);
    }

    /**
     * Mettre à jour un participant ajouté manuellement, avec gestion des places.
     */
public function updateManualParticipant(Request $request, $reservationId)
{
    $reservation = Reservations_events::findOrFail($reservationId);

    if ($reservation->created_by !== auth()->id()) {
        return response()->json(['message' => 'Non autorisé à modifier ce participant.'], 403);
    }

    // Récupérer les données JSON brutes
    $data = $request->json()->all();

    if (empty($data)) {
        return response()->json(['message' => 'Aucune donnée reçue pour la mise à jour.'], 400);
    }

    // Valider uniquement les champs présents
    $rules = [
        'name' => 'sometimes|string',
        'email' => 'sometimes|email',
        'phone' => 'sometimes|string',
        'nbr_place' => 'sometimes|integer|min:1',
    ];

    $validator = \Validator::make($data, $rules);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Construire le tableau des champs à mettre à jour
    $dataToUpdate = [];

    foreach (['name', 'email', 'phone', 'nbr_place'] as $field) {
        if (array_key_exists($field, $data)) {
            $dataToUpdate[$field] = $data[$field];
        }
    }

    if (empty($dataToUpdate)) {
        return response()->json(['message' => 'Aucune donnée à mettre à jour.'], 400);
    }

    // Si nbr_place est modifié, vérifier la disponibilité des places restantes
    if (isset($dataToUpdate['nbr_place'])) {
        $diff = $dataToUpdate['nbr_place'] - $reservation->nbr_place;
        $event = Events::findOrFail($reservation->event_id);

        if ($diff > 0 && $event->nbr_place_restante < $diff) {
            return response()->json(['message' => 'Pas assez de places disponibles.'], 400);
        }

        $event->nbr_place_restante -= $diff;
        $event->save();
    }

    // Mise à jour en base
    $reservation->update($dataToUpdate);

    return response()->json([
        'message' => 'Participant mis à jour avec succès.',
        'participant' => $reservation->fresh(),
    ]);
}


/**
     * Créer une réservation avec le statut "en_attente_paiement".
     *
     * Accepts: event_id, nbr_place, group_id, name, email, phone
     * Frontend sends name/email/phone explicitly — do NOT use $user->name
     * because the User model uses first_name + last_name (no `name` column).
     */
    /**
     * Conflict check endpoint — kept for route compatibility but always returns no conflict.
     * Campers are free to join multiple events with overlapping dates.
     */
    public function checkConflict($eventId)
    {
        return response()->json(['conflict' => false]);
    }

    public function createReservationWithPayment(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'event_id'       => 'required|exists:events,id',
            'nbr_place'      => 'required|integer|min:1',
            'group_id'       => 'required|exists:users,id',
            'promo_code'     => 'nullable|string|max:50',
            'name'           => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'payment_method' => 'nullable|in:wallet,card',
        ]);

        // Card payment is not available in v1
        if ($request->payment_method === 'card') {
            return response()->json([
                'success' => false,
                'message' => 'Le paiement en ligne par carte sera bientôt disponible. Veuillez utiliser votre wallet.',
                'coming_soon' => true,
            ], 422);
        }

        $event = Events::findOrFail($request->event_id);

        $resolvedName = $request->name
            ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: $user->email;

        // ── Promo code ───────────────────────────────────────────────────────────
        $promoCodeId    = null;
        $discountAmount = 0;
        $basePrice      = (float) $event->price * (int) $request->nbr_place;

        if (!empty($request->promo_code)) {
            $promo = PromoCode::where('code', strtoupper(trim($request->promo_code)))->first();
            if (!$promo) {
                return response()->json(['message' => 'Invalid promo code.'], 422);
            }
            $check = $promo->isValid('event', $basePrice);
            if (!$check['valid']) {
                return response()->json(['message' => $check['reason']], 422);
            }
            $discountAmount = $promo->calculateDiscount($basePrice);
            $promoCodeId    = $promo->id;
        }

        // ── Price calculation ────────────────────────────────────────────────────
        $netBase    = max(0, round($basePrice - $discountAmount, 2));
        $feeData    = CommissionService::addServiceFee($netBase);
        $totalToPay = $feeData['total'];          // what camper pays
        $platformFeeAmt  = $feeData['fee_amount'];
        $platformFeeRate = round($feeData['fee_rate'] * 100, 2);

        // ── Check balance upfront so camper knows before submitting ─────────────
        if ($totalToPay > 0) {
            $camperBalance = Balance::forUser($user->id);
            if ($camperBalance->solde_disponible < $totalToPay) {
                return response()->json([
                    'success' => false,
                    'message' => "Solde wallet insuffisant. Disponible : {$camperBalance->solde_disponible} TND, requis : {$totalToPay} TND.",
                ], 422);
            }
        }

        // ── Create reservation — no money moves until the group approves ─────────
        DB::beginTransaction();
        try {
            // ── Spots decrement ──────────────────────────────────────────────────
            if (isset($event->remaining_spots)) {
                $event->decrement('remaining_spots', $request->nbr_place);
            } elseif (isset($event->nbr_place_restante)) {
                $event->decrement('nbr_place_restante', $request->nbr_place);
            }

            if ($promoCodeId) {
                PromoCode::find($promoCodeId)?->incrementUsage();
            }

            $reservation = Reservations_events::create([
                'user_id'              => $user->id,
                'group_id'             => $request->group_id,
                'event_id'             => $request->event_id,
                'name'                 => $resolvedName,
                'email'                => $request->email  ?? $user->email,
                'phone'                => $request->phone  ?? $user->phone_number ?? null,
                'nbr_place'            => $request->nbr_place,
                'status'               => 'en_attente_validation',
                'created_by'           => $user->id,
                'promo_code_id'        => $promoCodeId,
                'discount_amount'      => $discountAmount,
                'payment_method'       => 'wallet',
                'platform_fee_amount'  => $platformFeeAmt,
                'platform_fee_rate'    => $platformFeeRate,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Event reservation creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue. Veuillez réessayer.'], 500);
        }

        try {
            Mail::to($reservation->email ?? $user->email)
                ->send(new EventReservationCreated($reservation, $event));
        } catch (\Exception $e) {
            \Log::error('Failed to send event reservation confirmation: ' . $e->getMessage());
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Réservation confirmée et paiement effectué via wallet.',
            'reservation' => $reservation,
            'payment'     => [
                'method'           => 'wallet',
                'total_paid'       => $totalToPay,
                'base_price'       => $netBase,
                'platform_fee'     => $platformFeeAmt,
                'platform_fee_pct' => $platformFeeRate,
            ],
        ], 201);
    }

    /**
     * Afficher une réservation avec ses détails.
     */
    public function show($reservationId)
    {
        $reservation = Reservations_events::with('event')->findOrFail($reservationId);

        return response()->json($reservation);
    }

    public function toutesMesReservations(Request $request)
{
    $user = Auth::user();

    $query = Reservations_events::where('group_id', $user->id)
        ->with(['event', 'user', 'payment']);

    // 🔍 Filtres dynamiques
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    if ($request->filled('email')) {
        $query->where('email', 'like', '%' . $request->email . '%');
    }

    if ($request->filled('phone')) {
        $query->where('phone', 'like', '%' . $request->phone . '%');
    }

    if ($request->filled('from')) {
        $query->whereDate('created_at', '>=', $request->from);
    }

    if ($request->filled('to')) {
        $query->whereDate('created_at', '<=', $request->to);
    }

    // 🔄 Récupération des données paginées
    $reservations = $query->orderBy('created_at', 'desc')->paginate(10);

    // ✅ Vérification si vide
    if ($reservations->isEmpty()) {
        return response()->json([
            'message' => 'Aucune réservation trouvée.'
        ], 200); // ou 204 si tu préfères
    }

    return response()->json($reservations);
}

    public function reservationsParEvenement($event_id)
    {
        $user = Auth::user();

        $reservations = Reservations_events::where('group_id', $user->id)
            ->where('event_id', $event_id)
            ->with(['user', 'payment'])
            ->orderByDesc('created_at')
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json(['message' => 'Aucune réservation trouvée pour cet événement.'], 200);
        }

        return response()->json($reservations);
    }

    // Export des réservations (CSV)
    public function exportReservations(Request $request)
    {
        $user = Auth::user();

        $reservations = Reservations_events::where('group_id', $user->id)->with(['event'])->get();

        if ($reservations->isEmpty()) {
            return response()->json(['message' => 'Aucune réservation à exporter.'], 200);
        }

        $csvData = [];
        $csvData[] = ["Nom", "Email", "Téléphone", "Événement", "Statut", "Places", "Date"];

        foreach ($reservations as $r) {
            $csvData[] = [
                $r->name,
                $r->email,
                $r->phone,
                optional($r->event)->title,
                $r->status,
                $r->nbr_place,
                $r->created_at,
            ];
        }

        $filename = 'reservations_export_' . now()->timestamp . '.csv';
        $handle = fopen(storage_path("app/$filename"), 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return response()->download(storage_path("app/$filename"))->deleteFileAfterSend(true);
    }

    //  Mise à jour du statut (confirmé/refusé)
    public function updateStatus(Request $request, $id)
    {
        $reservation = Reservations_events::findOrFail($id);

        if ($reservation->group_id !== Auth::id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->json()->all();

        $validator = \Validator::make($data, [
            'status' => 'required|in:confirmé,refusé,en_attente,annulé'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Validation échouée.'], 422);
        }

        $newStatus = $data['status'];
        $event     = Events::findOrFail($reservation->event_id);
        $netBase   = round(($reservation->nbr_place * $event->price) - ($reservation->discount_amount ?? 0), 2);

        // ── Wallet movements triggered by group decision ──────────────────────────
        if ($reservation->payment_method === 'wallet' && $reservation->status === 'en_attente_validation') {

            if ($newStatus === 'confirmé') {
                // Release escrowed funds to organizer now that they accepted
                DB::beginTransaction();
                try {
                    $orgCalc = CommissionService::calculate('group', $netBase);
                    Balance::forUser($reservation->group_id)->crediter($orgCalc['net_revenue']);
                    WalletTransaction::logCredit(
                        $reservation->group_id, 'reservation_income',
                        $netBase,
                        round($orgCalc['rate'] * 100, 2),
                        $orgCalc['commission'],
                        $orgCalc['net_revenue'],
                        'event_reservation', $reservation->id,
                        "Revenu réservation événement : {$event->title}",
                        $reservation->user_id
                    );
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    \Log::error('Event reservation approval wallet credit failed: ' . $e->getMessage());
                    return response()->json(['success' => false, 'message' => 'Erreur lors du paiement au groupe.'], 500);
                }

            } elseif ($newStatus === 'refusé') {
                // Full refund to camper — group rejected, they never had the money
                DB::beginTransaction();
                try {
                    $feeData   = CommissionService::addServiceFee($netBase);
                    $totalPaid = round($feeData['total'], 2);
                    Balance::forUser($reservation->user_id)->crediter($totalPaid);
                    WalletTransaction::logCredit(
                        $reservation->user_id, 'refund_in',
                        $totalPaid, 0, 0, $totalPaid,
                        'event_reservation', $reservation->id,
                        "Remboursement — réservation refusée #{$reservation->id}",
                        $reservation->group_id
                    );
                    $event->increment('remaining_spots', $reservation->nbr_place);
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    \Log::error('Event reservation rejection wallet refund failed: ' . $e->getMessage());
                    return response()->json(['success' => false, 'message' => 'Erreur lors du remboursement.'], 500);
                }
            }
        }

        $reservation->status = $newStatus;
        $reservation->save();

        try {
            if ($reservation->email) {
                Mail::to($reservation->email)->send(new EventReservationStatusChanged($reservation, $event));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send event status change email: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Statut mis à jour avec succès.']);
    }


    // Annulation avec remboursement
    // Annuler une réservation et déclencher un remboursement.

    public function cancelReservation($id)
    {
        $reservation = Reservations_events::with('payment')->findOrFail($id);

        if ($reservation->group_id !== Auth::id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $event   = Events::findOrFail($reservation->event_id);
        $netBase = round(($reservation->nbr_place * $event->price) - ($reservation->discount_amount ?? 0), 2);

        // ── Wallet refund when group cancels ─────────────────────────────────────
        if ($reservation->payment_method === 'wallet') {
            DB::beginTransaction();
            try {
                if ($reservation->status === 'confirmée') {
                    // Money was collected at approval → full refund to camper + clawback from organizer
                    $feeData   = CommissionService::addServiceFee($netBase);
                    $totalPaid = round($feeData['total'], 2);
                    $orgCalc   = CommissionService::calculate('group', $netBase);

                    Balance::forUser($reservation->group_id)->debiter($orgCalc['net_revenue']);
                    WalletTransaction::logDebit(
                        $reservation->group_id, 'refund_out', $orgCalc['net_revenue'],
                        'event_reservation', $reservation->id,
                        "Remboursement — annulation groupe #{$reservation->id}",
                        $reservation->user_id
                    );

                    Balance::forUser($reservation->user_id)->crediter($totalPaid);
                    WalletTransaction::logCredit(
                        $reservation->user_id, 'refund_in',
                        $totalPaid, 0, 0, $totalPaid,
                        'event_reservation', $reservation->id,
                        "Remboursement — annulation par groupe #{$reservation->id}",
                        $reservation->group_id
                    );
                }
                // en_attente_validation: no money was taken at booking — nothing to refund

                $event->increment('remaining_spots', $reservation->nbr_place);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Group cancellation wallet refund failed: ' . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'Erreur lors du remboursement.'], 500);
            }
        }

        $reservation->status = 'annulé';
        $reservation->save();

        // Notify the participant that their reservation was cancelled by the group
        try {
            if ($reservation->email) {
                Mail::to($reservation->email)->send(new EventReservationCanceledByGroup($reservation, $event));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send group cancellation email to participant: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Réservation annulée et remboursement lancé.']);
    }

    //  Modifier le nombre de places
    public function updatePlaces(Request $request, $id)
{
    $data = $request->json()->all();

    $validator = \Validator::make($data, [
        'nbr_place' => 'required|integer|min:1'
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
    }

    $reservation = Reservations_events::findOrFail($id);

    if ($reservation->group_id !== Auth::id()) {
        return response()->json(['message' => 'Non autorisé.'], 403);
    }

    $event = Events::findOrFail($reservation->event_id);

    $ancienne_place = $reservation->nbr_place;
    $nouvelle_place = $data['nbr_place'];
    $diff = $nouvelle_place - $ancienne_place;

    // Vérifier si assez de places restantes si on augmente
    if ($diff > 0 && $event->nbr_place_restante < $diff) {
        return response()->json(['message' => 'Pas assez de places restantes.'], 400);
    }

    // Mettre à jour le champ `nbr_place_restante`
    $event->nbr_place_restante -= $diff;
    $event->save();

    // Mettre à jour la réservation
    $reservation->nbr_place = $nouvelle_place;
    $reservation->save();

    return response()->json(['message' => 'Nombre de places mis à jour.']);
}


    // Statistiques de participation
    public function reservationStats()
    {
        $user = Auth::user();

        $total = Reservations_events::where('group_id', $user->id)->count();
        $confirmes = Reservations_events::where('group_id', $user->id)->where('status', 'confirmé')->count();
        $en_attente = Reservations_events::where('group_id', $user->id)->where('status', 'en_attente')->count();
        $annules = Reservations_events::where('group_id', $user->id)->where('status', 'annulé')->count();

        return response()->json([
            'total' => $total,
            'confirmés' => $confirmes,
            'en_attente' => $en_attente,
            'annulés' => $annules,
        ]);
    }


    public function updateReservation(Request $request, $reservationId)
{
    $reservation = Reservations_events::findOrFail($reservationId);

    // Vérifier que le groupe connecté est bien propriétaire de la réservation
    if ($reservation->group_id !== Auth::id()) {
        return response()->json(['message' => 'Non autorisé à modifier cette réservation.'], 403);
    }

    // Récupérer les données JSON brutes
    $data = $request->json()->all();

    if (empty($data)) {
        return response()->json(['message' => 'Aucune donnée reçue pour la mise à jour.'], 400);
    }

    // Définir les règles de validation selon les champs modifiables
    $rules = [
        'status' => 'sometimes|in:confirmé,refusé,en_attente,annulé',
        'nbr_place' => 'sometimes|integer|min:1',
        'name' => 'sometimes|string',
        'email' => 'sometimes|email',
        'phone' => 'sometimes|string',
    ];

    $validator = \Validator::make($data, $rules);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors(), 'message' => 'Validation échouée.'], 422);
    }

    // Gestion spécifique pour nbr_place : vérifier la disponibilité des places restantes
    if (isset($data['nbr_place'])) {
        $event = Events::findOrFail($reservation->event_id);
        $diff = $data['nbr_place'] - $reservation->nbr_place;

        if ($diff > 0 && $event->nbr_place_restante < $diff) {
            return response()->json(['message' => 'Pas assez de places disponibles.'], 400);
        }

        // Mettre à jour nbr_place_restante sur l'événement
        $event->nbr_place_restante -= $diff;
        $event->save();
    }

    // Appliquer la mise à jour sur la réservation
    $reservation->fill($data);
    $reservation->save();

    return response()->json([
        'message' => 'Réservation mise à jour avec succès.',
        'reservation' => $reservation->fresh(),
    ]);
}

// Afficher mes réservations passées
public function mesReservationsPassees(Request $request)
{
    $user = Auth::user();
    $today = now()->toDateString();

    $query = Reservations_events::where('group_id', $user->id)
        ->whereHas('event', function ($q) use ($today) {
            $q->whereDate('date_sortie', '<', $today);
        })
        ->with(['event', 'user', 'payment']);

    $reservations = $query->orderBy('created_at', 'desc')->paginate(10);

    return response()->json($reservations);
}



};
