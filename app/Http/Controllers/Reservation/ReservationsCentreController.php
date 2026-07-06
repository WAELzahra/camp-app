<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\CentreModifyReservationRequest;
use App\Http\Requests\Reservation\PartialAcceptReservationRequest;
use App\Http\Requests\Reservation\StoreCentreReservationRequest;
use App\Http\Requests\Reservation\UpdateCentreReservationRequest;
use App\Mail\NewReservationNotification;
use App\Mail\ReservationCanceledByCenter;
use App\Mail\ReservationCanceledByUser;
use App\Mail\ReservationCreatedToCamper;
use App\Mail\ReservationModifiedByCenter;
use App\Mail\ReservationRejected;
use App\Mail\ReservationUpdatedToCentre;
use App\Models\Balance;
use App\Models\Profile;
use App\Models\ProfileCentre;
use App\Models\PromoCode;
use App\Models\Reservations_centre;
use App\Models\ReservationServiceItem;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;
use App\Services\ManualPaymentService;
use App\Services\PaymentReferenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
            'reservations' => $reservations,
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
            'reservations' => $reservations,
        ], 200);
    }

    /**
     * Display a specific reservation by its ID with service items.
     */
    public function show($idReservation)
    {
        $idReservation = (int) $idReservation;

        $reservation = Reservations_centre::with([
            'serviceItems',
            'serviceItems.service',
            'serviceItems.service.category',
            'user',
            'centre',
        ])->find($idReservation);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found.'], 404);
        }

        return response()->json([
            'message' => 'Reservation retrieved successfully.',
            'reservation' => $reservation,
        ], 200);
    }

    /**
     * Store a new reservation with multiple service items.
     */
    public function store(StoreCentreReservationRequest $request)
    {
        $validated = $request->validated();

        $userId = Auth::id();

        // Verify the centre is valid
        $centreUser = User::where('id', $request->centre_id)
            ->where('role_id', 3)
            ->first();

        if (!$centreUser) {
            return response()->json([
                'message' => 'The selected centre_id does not belong to a user with the role "centre".',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Compute number of nights from dates
            $nights = max(1, Carbon::parse($request->date_debut)->diffInDays(Carbon::parse($request->date_fin)));

            // Calculate base total price (multiply per-night services by nights)
            $totalPrice = 0;
            foreach ($request->service_items as $item) {
                $isPerNight = preg_match('/night|nuit/i', $item['unit'] ?? '');
                $subtotal = $item['unit_price'] * $item['quantity'] * ($isPerNight ? $nights : 1);
                $totalPrice += $subtotal;
            }

            // Apply promo code if provided
            $promoCodeId = null;
            $discountAmount = 0;
            if (!empty($request->promo_code)) {
                $promo = PromoCode::where('code', strtoupper(trim($request->promo_code)))->first();
                if (!$promo) {
                    DB::rollBack();

                    return response()->json(['message' => 'Invalid promo code.'], 422);
                }
                $check = $promo->isValid('centre', $totalPrice);
                if (!$check['valid']) {
                    DB::rollBack();

                    return response()->json(['message' => $check['reason']], 422);
                }
                $discountAmount = $promo->calculateDiscount($totalPrice);
                $totalPrice = max(0, round($totalPrice - $discountAmount, 2));
                $promoCodeId = $promo->id;
            }

            // Apply platform service fee (charged to camper on top of base price)
            $feeData = CommissionService::addServiceFee($totalPrice);
            $totalWithFee = $feeData['total'];
            $platformFeeAmt = $feeData['fee_amount'];
            $platformFeeRate = round($feeData['fee_rate'] * 100, 2);

            // Get service types for the reservation type field
            $serviceTypes = [];
            foreach ($request->service_items as $item) {
                $service = \App\Models\ProfileCenterService::find($item['profile_center_service_id']);
                if ($service && $service->category) {
                    $serviceTypes[] = $service->category->name;
                }
            }
            $type = !empty($serviceTypes) ? implode(', ', array_unique($serviceTypes)) : 'Multiple Services';

            // Escrow: check and lock camper funds (wallet only; manual skips wallet entirely)
            $paymentMethod = $request->input('payment_method', 'wallet');

            if ($paymentMethod === 'manual' && !ManualPaymentService::isEnabled()) {
                DB::rollBack();

                return response()->json(['message' => 'Le paiement manuel n\'est pas disponible pour le moment.'], 422);
            }

            if ($paymentMethod === 'wallet' && $totalWithFee > 0) {
                $camperBal = Balance::forUser($userId);
                if ($camperBal->solde_disponible < $totalWithFee) {
                    DB::rollBack();

                    return response()->json([
                        'message' => "Solde wallet insuffisant. Disponible : {$camperBal->solde_disponible} TND, requis : {$totalWithFee} TND.",
                    ], 422);
                }
            }

            $manualAmounts = [];
            if ($paymentMethod === 'manual') {
                $requestedOption = $request->input('payment_option');
                $computed = ManualPaymentService::computeAmounts((int) $request->centre_id, $totalWithFee);
                if ($requestedOption === 'full' && $computed['payment_option'] === 'deposit') {
                    $computed = ['payment_option' => 'full', 'amount_now' => $totalWithFee, 'amount_later' => 0.0, 'deposit_pct' => null];
                } elseif ($requestedOption && $requestedOption !== $computed['payment_option']) {
                    $err = ManualPaymentService::validateOption($requestedOption, (int) $request->centre_id, $totalWithFee);
                    if ($err) {
                        DB::rollBack();

                        return response()->json(['message' => $err], 422);
                    }
                }
                $manualAmounts = $computed;
            }

            $balanceDueAt = null;
            if ($paymentMethod === 'manual' && ($manualAmounts['payment_option'] ?? 'full') === 'deposit') {
                // 7 days before check-in, but never in the past — a near-term booking
                // still gets a 48h window to pay the balance before auto-cancellation.
                $due = Carbon::parse($request->date_debut)->subDays(7);
                $balanceDueAt = $due->isPast() ? now()->addDays(2)->toDateString() : $due->toDateString();
            }

            // Create the main reservation (total_price includes camper platform fee)
            $reservationCentre = Reservations_centre::create([
                'user_id' => $userId,
                'centre_id' => $request->centre_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'note' => $request->note,
                'group_skill_level' => $request->input('group_skill_level'),
                'trip_purpose' => $request->input('trip_purpose'),
                'type' => $type,
                'nbr_place' => $request->nbr_place,
                'nights' => $nights,
                // Manual payments stay hidden from the centre until the admin confirms
                // the transfer; only then does the reservation enter the review queue.
                'status' => $paymentMethod === 'manual' ? 'pending_payment' : 'pending',
                'total_price' => $totalWithFee,
                'payment_method' => $paymentMethod,
                'service_count' => count($request->service_items),
                'promo_code_id' => $promoCodeId,
                'discount_amount' => $discountAmount,
                'platform_fee_rate' => $platformFeeRate,
                'platform_fee_amount' => $platformFeeAmt,
                'payment_option' => $paymentMethod === 'manual' ? ($manualAmounts['payment_option'] ?? 'full') : null,
                'amount_now' => $paymentMethod === 'manual' ? ($manualAmounts['amount_now'] ?? $totalWithFee) : null,
                'amount_later' => $paymentMethod === 'manual' ? ($manualAmounts['amount_later'] ?? 0) : null,
                'balance_due_at' => $balanceDueAt,
            ]);

            if ($paymentMethod === 'manual') {
                $reservationCentre->payment_reference = PaymentReferenceService::forReservation($reservationCentre->id);
                $reservationCentre->save();
            }

            // Lock funds in escrow immediately (wallet only)
            if ($paymentMethod === 'wallet' && $totalWithFee > 0) {
                Balance::forUser($userId)->lockFunds($totalWithFee);
                WalletTransaction::logDebit(
                    $userId, 'reservation_payment', $totalWithFee,
                    'centre_reservation', $reservationCentre->id,
                    "Paiement réservation centre #{$reservationCentre->id} (en attente d'approbation)",
                    (int) $request->centre_id,
                    $platformFeeRate,
                    $platformFeeAmt
                );
            }

            // Create service items (subtotal reflects nights multiplier)
            foreach ($request->service_items as $item) {
                $isPerNight = preg_match('/night|nuit/i', $item['unit'] ?? '');
                $subtotal = $item['unit_price'] * $item['quantity'] * ($isPerNight ? $nights : 1);

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
                    'status' => 'pending',
                ]);
            }

            // Increment promo code usage
            if ($promoCodeId) {
                PromoCode::find($promoCodeId)?->incrementUsage();
            }

            DB::commit();

            // Send notification email to centre
            $user = Auth::user();
            if ($centreUser) {
                $reservationCentre->load(['serviceItems', 'user']);
                Mail::to($centreUser->email)->send(new NewReservationNotification($centreUser, $user, $reservationCentre));
            }

            // Send confirmation email to camper
            try {
                Mail::to($user->email)->send(new ReservationCreatedToCamper($reservationCentre));
            } catch (\Exception $e) {
                \Log::error('Failed to send reservation confirmation to camper: '.$e->getMessage());
            }

            $resp = [
                'message' => 'Reservation created successfully.',
                'reservation' => $reservationCentre->load('serviceItems'),
            ];
            if ($paymentMethod === 'manual') {
                $resp['payment_info'] = [
                    'reference' => $reservationCentre->payment_reference,
                    'option' => $reservationCentre->payment_option,
                    'amount_now' => $reservationCentre->amount_now,
                    'amount_later' => $reservationCentre->amount_later,
                    'balance_due_at' => $reservationCentre->balance_due_at,
                    'flouci_link' => ManualPaymentService::flouciLink(),
                ];
            }

            return response()->json($resp, 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Database error: '.$e->getMessage(),
            ], 500);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a reservation and its service items.
     */
    public function update(UpdateCentreReservationRequest $request, int $id)
    {
        $reservation = Reservations_centre::findOrFail($id);

        $userId = Auth::id();
        if ($reservation->user_id != $userId && $reservation->centre_id != $userId) {
            return response()->json([
                'message' => 'Unauthorized to update this reservation.',
            ], 403);
        }

        $validated = $request->validated();

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
                    $hasTempId = isset($itemData['id']) && is_string($itemData['id']) && str_starts_with($itemData['id'], 'new-');

                    if ($isNewItem || $hasTempId || !isset($itemData['id']) || $itemData['id'] === null) {
                        $newServiceItem = ReservationServiceItem::create([
                            'reservation_id' => $reservation->id,
                            'profile_center_service_id' => $itemData['service_id'] ?? $itemData['id'],
                            'service_name' => $itemData['service_name'] ?? 'Service',
                            'service_description' => $itemData['service_description'] ?? null,
                            'unit_price' => $itemData['unit_price'] ?? 0,
                            'unit' => $itemData['unit'] ?? 'item',
                            'quantity' => $itemData['quantity'] ?? 1,
                            'notes' => $itemData['notes'] ?? null,
                            'subtotal' => ($itemData['unit_price'] ?? 0) * ($itemData['quantity'] ?? 1),
                            'status' => 'pending',
                            'service_date_debut' => $reservation->date_debut,
                            'service_date_fin' => $reservation->date_fin,
                        ]);

                        $totalPrice += $newServiceItem->subtotal;
                        $activeServiceCount++;

                        continue;
                    }

                    // ✅ Handle existing items - SIMPLIFIED AND FIXED
                    if (isset($itemData['id']) && is_numeric($itemData['id'])) {
                        $serviceItem = ReservationServiceItem::where('id', $itemData['id'])
                            ->where('reservation_id', $reservation->id)
                            ->first();

                        if (!$serviceItem) {
                            \Log::warning('Service item not found:', ['id' => $itemData['id']]);

                            continue;
                        }

                        // ✅ Always update these fields
                        if (isset($itemData['quantity'])) {
                            $serviceItem->quantity = (int) $itemData['quantity'];
                        }
                        if (isset($itemData['unit_price'])) {
                            $serviceItem->unit_price = $itemData['unit_price'];
                        }
                        if (isset($itemData['unit'])) {
                            $serviceItem->unit = $itemData['unit'];
                        }
                        if (isset($itemData['service_name'])) {
                            $serviceItem->service_name = $itemData['service_name'];
                        }
                        if (isset($itemData['notes'])) {
                            $serviceItem->notes = $itemData['notes'];
                        }

                        // ✅ Always recalculate subtotal
                        $serviceItem->subtotal = $serviceItem->unit_price * $serviceItem->quantity;

                        // ✅ Always save
                        $serviceItem->save();

                        $totalPrice += $serviceItem->subtotal;
                        $activeServiceCount++;
                    }
                }

                $reservation->service_count = $activeServiceCount;
                if ($totalPrice > 0) {
                    $reservation->total_price = $totalPrice;
                }
            }

            if ($reservation->status === 'approved' && $reservation->user_id == $userId) {
                $reservation->status = 'modified';
                $reservation->last_modified_by = 'user';
                $reservation->last_modified_at = now();
            }

            $reservation->save();
            DB::commit();

            // Notify centre
            if ($reservation->user_id == $userId) {
                try {
                    $reservation->load(['serviceItems', 'user', 'centre']);
                    $centreUser = \App\Models\User::find($reservation->centre_id);
                    if ($centreUser) {
                        Mail::to($centreUser->email)->send(new ReservationUpdatedToCentre($reservation));
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to send reservation update notification: '.$e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Reservation updated successfully.',
                'reservation' => $reservation->load('serviceItems'),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error updating reservation: '.$e->getMessage(),
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
                'message' => 'Unauthorized to cancel this reservation.',
            ], 403);
        }

        // Determine who is canceling
        $canceledBy = ($userId == $reservation->user_id) ? 'user' : 'center';
        $cancellationDate = now();
        $originalStatus = $reservation->status;

        $refundCreated = false;

        // For centre-cancels-approved: wrap status + wallet in one transaction so
        // the status is never stuck as 'canceled' if the wallet operation fails.
        if ($canceledBy === 'center'
            && $reservation->payment_method === 'wallet'
            && $reservation->total_price > 0
            && $originalStatus === 'approved') {

            $gross = (float) $reservation->total_price;
            $platformFee = (float) ($reservation->platform_fee_amount ?? 0);
            $base = max(0, round($gross - $platformFee, 2));
            // Use stored net_amount from the original credit transaction to guarantee
            // the clawback matches exactly what was credited, even if the commission rate changed.
            $originalCreditTx = WalletTransaction::where('user_id', $reservation->centre_id)
                ->where('type', 'credit')
                ->where('category', 'reservation_income')
                ->where('reference_type', 'centre_reservation')
                ->where('reference_id', $reservation->id)
                ->first();
            $netToClawback = $originalCreditTx
                ? (float) $originalCreditTx->net_amount
                : CommissionService::calculateForUser('center', $base, $reservation->centre_id)['net_revenue'];

            DB::beginTransaction();
            try {
                // Update status inside the transaction
                $reservation->status = 'canceled';
                $reservation->canceled_by = $canceledBy;
                $reservation->canceled_at = $cancellationDate;
                $reservation->save();

                ReservationServiceItem::where('reservation_id', $reservation->id)
                    ->update(['status' => 'canceled', 'rejected_by' => $canceledBy, 'rejected_at' => $cancellationDate]);

                // Clawback what the centre received (exact amount from original transaction)
                Balance::forUser($reservation->centre_id)->debiter($netToClawback);
                WalletTransaction::logDebit(
                    $reservation->centre_id, 'refund_out', $netToClawback,
                    'centre_reservation', $reservation->id,
                    "Remboursement annulation centre #{$reservation->id}",
                    $reservation->user_id
                );

                // Platform cancellation fee charged to centre (additional debit)
                $platformCancFee = \App\Models\PlatformCancellationFee::feeAmount('centre', $gross);
                if ($platformCancFee > 0) {
                    Balance::forUser($reservation->centre_id)->debiter($platformCancFee);
                    WalletTransaction::logDebit(
                        $reservation->centre_id, 'refund_out', $platformCancFee,
                        'centre_reservation', $reservation->id,
                        "Frais annulation plateforme #{$reservation->id}",
                        $reservation->user_id
                    );
                    \App\Models\AdminWalletTransaction::log(
                        'platform_cancellation_fee', $platformCancFee,
                        'centre_reservation', $reservation->id,
                        "Platform cancellation fee (centre) — #{$reservation->id}",
                        $reservation->centre_id
                    );
                }

                // Full refund to camper
                Balance::forUser($reservation->user_id)->crediter($gross);
                WalletTransaction::logCredit(
                    $reservation->user_id, 'refund_in',
                    $gross, 0, 0, $gross,
                    'centre_reservation', $reservation->id,
                    "Remboursement annulation centre #{$reservation->id}",
                    $reservation->centre_id
                );

                DB::commit();
                $refundCreated = true;
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Centre cancellation wallet refund failed: '.$e->getMessage());

                return response()->json(['success' => false, 'message' => 'Erreur lors du remboursement.'], 500);
            }
        } elseif ($canceledBy === 'user'
            && $reservation->payment_method === 'wallet'
            && $reservation->total_price > 0
            && $originalStatus === 'approved') {

            // Camper cancels an approved reservation → automatic refund using cancellation policy
            $gross = (float) $reservation->total_price;
            $platformFee = (float) ($reservation->platform_fee_amount ?? 0);
            $base = max(0, round($gross - $platformFee, 2));
            // Clawback must match what was originally credited to the centre.
            $originalCreditTx = WalletTransaction::where('user_id', $reservation->centre_id)
                ->where('type', 'credit')
                ->where('category', 'reservation_income')
                ->where('reference_type', 'centre_reservation')
                ->where('reference_id', $reservation->id)
                ->first();
            $netToClawback = $originalCreditTx
                ? (float) $originalCreditTx->net_amount
                : CommissionService::calculateForUser('center', $base, $reservation->centre_id)['net_revenue'];

            $startDate = Carbon::parse($reservation->date_debut);
            $feeCalc = CancellationPolicyService::preview('centre', $startDate, $gross, (int) $reservation->centre_id, Carbon::parse($reservation->created_at));
            $refundAmt = $feeCalc ? $feeCalc['refund_amount'] : $gross;
            $feeDesc = $feeCalc ? $feeCalc['tier_label'] : 'Full refund';

            // Platform cancellation fee charged to camper (reduces their refund)
            $platformCancFee = \App\Models\PlatformCancellationFee::feeAmount('camper', $gross);
            $actualRefund = max(0, round($refundAmt - $platformCancFee, 2));

            DB::beginTransaction();
            try {
                $reservation->status = 'canceled';
                $reservation->canceled_by = $canceledBy;
                $reservation->canceled_at = $cancellationDate;
                $reservation->save();

                ReservationServiceItem::where('reservation_id', $reservation->id)
                    ->update(['status' => 'canceled', 'rejected_by' => $canceledBy, 'rejected_at' => $cancellationDate]);

                // Claw back from centre exactly what they received at approval
                Balance::forUser($reservation->centre_id)->debiter($netToClawback);
                WalletTransaction::logDebit(
                    $reservation->centre_id, 'refund_out', $netToClawback,
                    'centre_reservation', $reservation->id,
                    "Remboursement annulation camper — {$feeDesc} — #{$reservation->id}",
                    $reservation->user_id
                );

                // Camper receives refund minus policy fee and platform cancellation fee
                Balance::forUser($reservation->user_id)->crediter($actualRefund);
                WalletTransaction::logCredit(
                    $reservation->user_id, 'refund_in',
                    $actualRefund, 0, 0, $actualRefund,
                    'centre_reservation', $reservation->id,
                    "Remboursement annulation — {$feeDesc} — #{$reservation->id}",
                    $reservation->centre_id
                );

                // Centre retains their cancellation fee portion
                $cancellationFee = round($gross - $refundAmt, 2);
                if ($cancellationFee > 0) {
                    Balance::forUser($reservation->centre_id)->crediter($cancellationFee);
                    WalletTransaction::logCredit(
                        $reservation->centre_id, 'reservation_income',
                        $cancellationFee, 0, 0, $cancellationFee,
                        'centre_reservation', $reservation->id,
                        "Frais d'annulation — {$feeDesc} — #{$reservation->id}",
                        $reservation->user_id
                    );
                }

                // Log platform cancellation fee income
                if ($platformCancFee > 0) {
                    \App\Models\AdminWalletTransaction::log(
                        'platform_cancellation_fee', $platformCancFee,
                        'centre_reservation', $reservation->id,
                        "Platform cancellation fee (camper) — {$feeDesc} — #{$reservation->id}",
                        $reservation->user_id
                    );
                }

                // Log platform fee charged to centre for cancelling
                if (isset($platformCancFee) === false) {
                } // only in centre-cancels block above

                DB::commit();
                $refundCreated = true;
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Centre camper-cancel wallet refund failed: '.$e->getMessage());

                return response()->json(['success' => false, 'message' => 'Erreur lors du remboursement.'], 500);
            }
        } else {
            // pending / modified / no-wallet cancellation
            $needsEscrowRefund = in_array($originalStatus, ['pending', 'modified'])
                && $reservation->payment_method === 'wallet'
                && $reservation->total_price > 0;

            DB::beginTransaction();
            try {
                $reservation->status = 'canceled';
                $reservation->canceled_by = $canceledBy;
                $reservation->canceled_at = $cancellationDate;
                $reservation->save();

                ReservationServiceItem::where('reservation_id', $reservation->id)
                    ->update(['status' => 'canceled', 'rejected_by' => $canceledBy, 'rejected_at' => $cancellationDate]);

                if ($needsEscrowRefund) {
                    // Recover exact escrowed amount from the original debit transaction
                    $escrowTx = WalletTransaction::where('user_id', $reservation->user_id)
                        ->where('type', 'debit')
                        ->where('category', 'reservation_payment')
                        ->where('reference_type', 'centre_reservation')
                        ->where('reference_id', $reservation->id)
                        ->first();
                    $escrowAmt = $escrowTx ? (float) $escrowTx->amount_gross : (float) $reservation->total_price;

                    Balance::forUser($reservation->user_id)->refundEscrow($escrowAmt);
                    WalletTransaction::logCredit(
                        $reservation->user_id, 'refund_in',
                        $escrowAmt, 0, 0, $escrowAmt,
                        'centre_reservation', $reservation->id,
                        "Remboursement annulation (avant approbation) — #{$reservation->id}",
                        $reservation->centre_id
                    );
                    $refundCreated = true;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Centre pending cancellation failed: '.$e->getMessage());

                return response()->json(['success' => false, 'message' => 'Erreur lors de l\'annulation.'], 500);
            }
        }

        // Load relationships for email content
        $reservation->load(['user', 'centre', 'serviceItems']);

        // Send notification emails based on who canceled
        if ($reservation->centre && $reservation->user) {
            if ($canceledBy === 'user') {
                // User canceled - notify center
                Mail::to($reservation->centre->email)->send(new ReservationCanceledByUser(
                    $reservation->centre,
                    $reservation->user,
                    $reservation
                ));

                // Also notify user about their own cancellation
                Mail::to($reservation->user->email)->send(new \App\Mail\UserReservationCancellation(
                    $reservation->user,
                    $reservation
                ));
            } else {
                // Center canceled - notify user
                Mail::to($reservation->user->email)->send(new ReservationCanceledByCenter(
                    $reservation->user,
                    $reservation->centre,
                    $reservation
                ));

                // Also notify center about their own cancellation
                Mail::to($reservation->centre->email)->send(new \App\Mail\CenterReservationCancellation(
                    $reservation->centre,
                    $reservation
                ));
            }
        }

        return response()->json([
            'message' => $refundCreated
                ? 'Reservation cancelled. Refund processed automatically.'
                : 'Reservation cancelled successfully.',
            'canceled_by' => $canceledBy,
            'canceled_at' => $cancellationDate->format('Y-m-d H:i:s'),
            'refunded' => $refundCreated,
            'reservation' => $reservation,
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
                'message' => 'Unauthorized to view this user\'s history.',
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
                        ? $r->serviceItems->map(fn ($item) => $item->service_name)->join(', ')
                        : "Reservation #{$r->id}"),
                    'date_debut' => $r->date_debut,
                    'date_fin' => $r->date_fin,
                    'status' => $r->status,
                    'total_price' => $r->total_price,
                    'service_count' => $r->serviceItems->count(),
                ];
            });

        return response()->json([
            'message' => 'User reservation history retrieved successfully.',
            'history' => $userReservations,
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

        // Wallet payment: release escrow and credit centre (funds were locked at reservation creation)
        if ($reservation->payment_method === 'wallet' && $reservation->total_price > 0) {
            $gross = (float) $reservation->total_price;
            $platformFee = (float) ($reservation->platform_fee_amount ?? 0);
            $base = max(0, round($gross - $platformFee, 2));
            $calc = CommissionService::calculateForUser('center', $base, $reservation->centre_id);
            $commAmt = $calc['commission'];
            $net = $calc['net_revenue'];
            $commPct = round($calc['rate'] * 100, 2);

            // Release camper's escrowed funds (debit already happened at store())
            Balance::forUser($reservation->user_id)->releaseEscrow($gross);

            $centreBalance = Balance::forUser($reservation->centre_id);
            $centreBalance->crediter($net);
            WalletTransaction::logCredit(
                $reservation->centre_id, 'reservation_income',
                $gross, $commPct, $commAmt, $net,
                'centre_reservation', $reservation->id,
                "Revenu réservation #{$reservation->id} — commission {$commPct}% ({$commAmt} TND déduits)",
                $reservation->user_id
            );

            // Log to admin wallet history
            if ($platformFee > 0) {
                \App\Models\AdminWalletTransaction::log(
                    'platform_fee', $platformFee,
                    'centre_reservation', $reservation->id,
                    "Platform fee — réservation centre #{$reservation->id}",
                    $reservation->user_id
                );
            }
            if ($commAmt > 0) {
                \App\Models\AdminWalletTransaction::log(
                    'commission', $commAmt,
                    'centre_reservation', $reservation->id,
                    "Commission centre — réservation #{$reservation->id}",
                    $reservation->centre_id
                );
            }

            // Unified payment trace (admin "Transactions" tab)
            \App\Services\Payments\ReservationLedgerService::recordGatewayPayment(
                $reservation->user_id, $reservation->id, 'centre',
                $gross, 'reservation_credit', $reservation->payment_reference ?? null
            );
        } elseif ($reservation->payment_method === 'manual'
            && $reservation->payment_confirmed_at
            && $reservation->total_price > 0) {
            // MANUAL payment accepted by the host: settle the paid tranche now.
            // Deposits settle amount_now here; the balance settles when the
            // admin confirms the solde (AdminPaymentReviewController).
            $gross   = (float) $reservation->total_price;
            $fee     = (float) ($reservation->platform_fee_amount ?? 0);
            $tranche = $reservation->payment_option === 'deposit'
                ? (float) ($reservation->amount_now ?? $gross)
                : $gross;

            DB::transaction(fn () => \App\Services\Payments\ReservationLedgerService::creditManualTranche(
                'centre_reservation', $reservation->id,
                (int) $reservation->centre_id, 'center', (int) $reservation->user_id,
                $gross, $fee, $tranche, true,
                "Revenu réservation #{$reservation->id} (paiement manuel)"
            ));
        }

        // Send email
        if ($reservation->user && $reservation->user->email) {
            \Mail::to($reservation->user->email)->send(new \App\Mail\CentreReservationConfirmed($reservation));
        }

        return response()->json([
            'message' => 'Reservation approved and email sent',
            'reservation' => $reservation,
        ], 200);
    }

    /**
     * Center modifies a pending reservation (dates, capacity, services).
     * Sets status to 'modified' with last_modified_by = 'center'.
     * Camper must accept before it becomes approved.
     */
    public function centerModify(CentreModifyReservationRequest $request, int $id)
    {
        $reservation = Reservations_centre::with(['serviceItems', 'user'])->findOrFail($id);

        if ($reservation->centre_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Only pending reservations can be modified by the center.'], 400);
        }

        $validated = $request->validated();

        DB::beginTransaction();
        try {
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

            if ($request->has('service_items')) {
                foreach ($request->service_items as $itemData) {
                    if (isset($itemData['is_removed']) && $itemData['is_removed'] === true) {
                        if (isset($itemData['id']) && is_numeric($itemData['id'])) {
                            ReservationServiceItem::where('id', $itemData['id'])
                                ->where('reservation_id', $reservation->id)
                                ->delete();
                        }

                        continue;
                    }

                    $isNew = (isset($itemData['is_new']) && $itemData['is_new'] === true)
                                || !isset($itemData['id'])
                                || (isset($itemData['id']) && str_starts_with((string) $itemData['id'], 'new-'));

                    if ($isNew) {
                        $newItem = ReservationServiceItem::create([
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
                            'service_date_fin' => $reservation->date_fin,
                        ]);
                        $totalPrice += $newItem->subtotal;
                        $activeServiceCount++;

                        continue;
                    }

                    if (isset($itemData['id']) && is_numeric($itemData['id'])) {
                        $serviceItem = ReservationServiceItem::where('id', $itemData['id'])
                            ->where('reservation_id', $reservation->id)
                            ->first();
                        if (!$serviceItem) {
                            continue;
                        }

                        if (isset($itemData['quantity'])) {
                            $serviceItem->quantity = $itemData['quantity'];
                        }
                        if (isset($itemData['unit_price'])) {
                            $serviceItem->unit_price = $itemData['unit_price'];
                        }
                        if (isset($itemData['notes'])) {
                            $serviceItem->notes = $itemData['notes'];
                        }
                        $serviceItem->subtotal = $serviceItem->unit_price * $serviceItem->quantity;
                        $serviceItem->save();

                        $totalPrice += $serviceItem->subtotal;
                        $activeServiceCount++;
                    }
                }

                $reservation->service_count = $activeServiceCount;
                if ($totalPrice > 0) {
                    $reservation->total_price = $totalPrice;
                }
            }

            $reservation->status = 'modified';
            $reservation->last_modified_by = 'center';
            $reservation->last_modified_at = now();
            $reservation->save();

            DB::commit();

            // Notify camper
            try {
                $reservation->load(['serviceItems', 'user', 'centre']);
                if ($reservation->user && $reservation->user->email) {
                    $modificationData = [
                        'general_reason' => $request->input('modification_reason', ''),
                    ];
                    Mail::to($reservation->user->email)->send(
                        new \App\Mail\ReservationModifiedByCenter($reservation, $modificationData)
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send center modification email: '.$e->getMessage());
            }

            return response()->json([
                'message' => 'Reservation modified. Camper has been notified and must accept the changes.',
                'reservation' => $reservation->load('serviceItems'),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /**
     * Camper rejects a modification made by the center.
     * Reverts status back to 'pending' so the center can review again.
     */
    public function rejectModification(int $id)
    {
        $reservation = Reservations_centre::with(['user', 'centre'])->findOrFail($id);

        if ($reservation->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($reservation->status !== 'modified' || $reservation->last_modified_by !== 'center') {
            return response()->json(['message' => 'No center modification to reject.'], 400);
        }

        $reservation->status = 'pending';
        $reservation->last_modified_by = null;
        $reservation->last_modified_at = null;
        $reservation->save();

        // Notify center
        try {
            if ($reservation->centre && $reservation->centre->email) {
                Mail::to($reservation->centre->email)->send(
                    new \App\Mail\CamperRejectedModification($reservation)
                );
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send rejection notification to center: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Modification declined. Reservation is back to pending.',
            'reservation' => $reservation,
        ], 200);
    }

    // approve-modified
    public function approveModified(int $id)
    {
        $reservation = Reservations_centre::with(['user', 'serviceItems', 'centre'])->findOrFail($id);

        $callerIsCenter = $reservation->centre_id == Auth::id();
        $callerIsCamper = $reservation->user_id == Auth::id();

        if (!$callerIsCenter && !$callerIsCamper) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $lastModifiedBy = $reservation->last_modified_by;

        // Wallet payment for camper accepting center's modification (pending → approved path)
        if ($callerIsCamper && $lastModifiedBy === 'center'
            && $reservation->payment_method === 'wallet'
            && $reservation->total_price > 0) {

            $newTotal = (float) $reservation->total_price;
            $platformFee = (float) ($reservation->platform_fee_amount ?? 0);
            $base = max(0, round($newTotal - $platformFee, 2));
            $calc = CommissionService::calculateForUser('center', $base, $reservation->centre_id);

            // Find original escrowed amount
            $escrowTx = WalletTransaction::where('user_id', $reservation->user_id)
                ->where('type', 'debit')
                ->where('category', 'reservation_payment')
                ->where('reference_type', 'centre_reservation')
                ->where('reference_id', $reservation->id)
                ->first();
            $originalEscrowed = $escrowTx ? (float) $escrowTx->amount_gross : 0;
            $delta = round($newTotal - $originalEscrowed, 2);

            DB::beginTransaction();
            try {
                $camperBal = Balance::forUser($reservation->user_id);

                if ($delta > 0) {
                    // Centre increased price — charge additional amount
                    if ($camperBal->solde_disponible < $delta) {
                        return response()->json([
                            'message' => "Solde wallet insuffisant pour la modification de prix. Requis : {$delta} TND supplémentaires.",
                        ], 422);
                    }
                    $camperBal->lockFunds($delta);
                    WalletTransaction::logDebit(
                        $reservation->user_id, 'reservation_payment', $delta,
                        'centre_reservation', $reservation->id,
                        "Complément paiement — modification centre #{$reservation->id}",
                        $reservation->centre_id
                    );
                } elseif ($delta < 0) {
                    // Centre reduced price — refund the difference
                    $camperBal->refundEscrow(abs($delta));
                    WalletTransaction::logCredit(
                        $reservation->user_id, 'refund_in',
                        abs($delta), 0, 0, abs($delta),
                        'centre_reservation', $reservation->id,
                        "Remboursement partiel — réduction prix modification #{$reservation->id}",
                        $reservation->centre_id
                    );
                }

                // Release escrow (now equals newTotal after delta adjustment)
                $camperBal->releaseEscrow($newTotal);

                // Credit centre net of commission
                Balance::forUser($reservation->centre_id)->crediter($calc['net_revenue']);
                WalletTransaction::logCredit(
                    $reservation->centre_id, 'reservation_income',
                    $base, round($calc['rate'] * 100, 2), $calc['commission'], $calc['net_revenue'],
                    'centre_reservation', $reservation->id,
                    "Revenu réservation #{$reservation->id} — modification approuvée",
                    $reservation->user_id
                );

                if ($platformFee > 0) {
                    \App\Models\AdminWalletTransaction::log(
                        'platform_fee', $platformFee,
                        'centre_reservation', $reservation->id,
                        "Platform fee — réservation centre #{$reservation->id}",
                        $reservation->user_id
                    );
                }
                if ($calc['commission'] > 0) {
                    \App\Models\AdminWalletTransaction::log(
                        'commission', $calc['commission'],
                        'centre_reservation', $reservation->id,
                        "Commission centre — réservation #{$reservation->id}",
                        $reservation->centre_id
                    );
                }

                $reservation->status = 'approved';
                $reservation->last_modified_by = null;
                $reservation->last_modified_at = null;
                $reservation->save();
                ReservationServiceItem::where('reservation_id', $reservation->id)->update(['status' => 'approved']);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('approveModified wallet movement failed: '.$e->getMessage());

                return response()->json(['message' => 'Erreur lors du paiement.'], 500);
            }
        } else {
            $reservation->status = 'approved';
            $reservation->last_modified_by = null;
            $reservation->last_modified_at = null;
            $reservation->save();
            ReservationServiceItem::where('reservation_id', $reservation->id)->update(['status' => 'approved']);
        }

        // (block below kept for email sending)
        $reservation->refresh();

        try {
            // Camper approving center's modification → notify center + confirm to camper
            if ($callerIsCamper && $lastModifiedBy === 'center') {
                if ($reservation->user && $reservation->user->email) {
                    \Mail::to($reservation->user->email)->send(new \App\Mail\CentreReservationConfirmed($reservation));
                }
                if ($reservation->centre && $reservation->centre->email) {
                    \Mail::to($reservation->centre->email)->send(new \App\Mail\ReservationUpdatedToCentre($reservation));
                }
            } else {
                // Center approving camper's modification → notify camper
                if ($reservation->user && $reservation->user->email) {
                    \Mail::to($reservation->user->email)->send(new \App\Mail\CentreReservationConfirmed($reservation));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send approveModified emails: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Reservation approved.',
            'reservation' => $reservation,
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
                'message' => 'Only the center can reject reservations.',
            ], 403);
        }

        $reason = $request->input('reason', 'No reason provided');

        DB::beginTransaction();
        try {
            $reservation->status = 'rejected';
            $reservation->save();

            ReservationServiceItem::where('reservation_id', $reservation->id)
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => 'center',
                    'rejection_reason' => $reason,
                    'rejected_at' => now(),
                ]);

            // Refund escrowed funds to camper (locked at store())
            if ($reservation->payment_method === 'wallet' && $reservation->total_price > 0) {
                $escrowAmt = (float) $reservation->total_price;
                Balance::forUser($reservation->user_id)->refundEscrow($escrowAmt);
                WalletTransaction::logCredit(
                    $reservation->user_id, 'refund_in',
                    $escrowAmt, 0, 0, $escrowAmt,
                    'centre_reservation', $reservation->id,
                    "Remboursement — réservation centre rejetée #{$reservation->id}",
                    $reservation->centre_id
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Centre reservation rejection failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to reject reservation.'], 500);
        }

        $user = $reservation->user;
        if ($user) {
            Mail::to($user->email)->send(new ReservationRejected($user, $reason));
        }

        return response()->json([
            'message' => 'Reservation rejected successfully.',
            'reservation' => $reservation,
        ], 200);
    }

    /**
     * NEW: Partial acceptance - center rejects some services, accepts others
     */
    public function partialAccept(PartialAcceptReservationRequest $request, int $id)
    {
        $reservation = Reservations_centre::with(['serviceItems', 'user'])
            ->findOrFail($id);

        // Only centers can modify
        if ($reservation->centre_id != Auth::id()) {
            return response()->json([
                'message' => 'Only the center can modify reservations.',
            ], 403);
        }

        $validated = $request->validated();

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
                    'rejected_at' => null,
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
                    'reservation' => $reservation->fresh(['serviceItems']),
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
                'message' => 'Reservation partially accepted. '.$acceptedCount.' service(s) approved, '.$rejectedCount.' service(s) rejected.',
                'rejected_count' => $rejectedCount,
                'accepted_count' => $acceptedCount,
                'new_total_price' => $newTotal,
                'modification_info' => $reservation->getModificationInfo(),
                'reservation' => $reservation->fresh(['serviceItems']),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to process partial acceptance: '.$e->getMessage(),
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
                'vacant_places' => 0,
            ]);
        } else {
            $vacant = $capacity_total - $activeReservations;

            return response()->json([
                'message' => 'Centre still has available spots.',
                'vacant_places' => $vacant,
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
                ->get(),
        ];

        return response()->json([
            'message' => 'Statistics retrieved successfully.',
            'statistics' => $stats,
        ], 200);
    }
}
