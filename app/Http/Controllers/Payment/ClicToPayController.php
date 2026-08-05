<?php

namespace App\Http\Controllers\Payment;

use App\Events\NewNotificationCreated;
use App\Http\Controllers\Controller;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Models\User;
use App\Notifications\CustomNotification;
use App\Services\Payments\ClicToPayGateway;
use App\Services\Payments\ReservationLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Real ClicToPay gateway integration (register.do + getOrderStatusExtended.do —
 * Manuel_Integration_ClicToPay_v1.0). Reservations keep payment_method='manual'
 * (ClicToPay is a sub-channel of it, same as bank transfer) — see
 * ReservationLedgerService::confirmSubmittedPayment()/rejectSubmittedPayment(),
 * which this controller shares with AdminPaymentReviewController so the money
 * logic has exactly one implementation.
 */
class ClicToPayController extends Controller
{
    public function __construct(private ClicToPayGateway $gateway)
    {
    }

    /**
     * POST /my/reservations/{type}/{id}/clictopay/init
     * Registers the payment with ClicToPay, returns the formUrl the frontend
     * must do a full-page redirect to (manual §3, steps 2-4).
     */
    public function initiate(Request $request, string $type, int $id): JsonResponse
    {
        $reservation = $this->findOwn($type, $id);
        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable.'], 404);
        }

        if ($reservation->payment_method !== 'manual') {
            return response()->json(['message' => "Cette réservation n'utilise pas le paiement manuel."], 422);
        }

        // Same two entry points as ManualPaymentController::paymentInfo()/submitProof().
        $isBalanceStep = (float) ($reservation->amount_later ?? 0) > 0
            && in_array($reservation->status, ['confirmée_solde_en_attente', 'approved', 'confirmée', 'confirmed']);
        $isInitial = is_null($reservation->payment_confirmed_at)
            && in_array($reservation->status, ['pending_payment', 'pending', 'en_attente_validation', 'paiement_invalide']);

        if (!$isBalanceStep && !$isInitial) {
            return response()->json(['message' => 'Le paiement ne peut pas être initié pour ce statut.'], 422);
        }

        $amount = $isBalanceStep ? (float) $reservation->amount_later : (float) $reservation->amount_now;
        if ($amount <= 0) {
            return response()->json(['message' => 'Montant invalide.'], 422);
        }

        // Fresh orderNumber per attempt (retries reuse the same payment_reference,
        // and ClicToPay rejects a duplicate orderNumber — errorCode 1).
        $orderNumber = ($reservation->payment_reference ?? ('RES-' . $reservation->id)) . '-' . time();
        $returnUrl = rtrim(config('app.url'), '/') . "/api/clictopay/return?type={$type}&id={$id}";
        $failUrl = rtrim(config('app.url'), '/') . "/api/clictopay/fail?type={$type}&id={$id}";

        try {
            $result = $this->gateway->register(
                $orderNumber, $amount, $returnUrl, $failUrl,
                "Réservation {$reservation->payment_reference}",
                in_array($request->header('X-Locale'), ['fr', 'en', 'ar'], true) ? $request->header('X-Locale') : 'fr',
                $this->isMobile($request) ? 'MOBILE' : 'DESKTOP',
            );
        } catch (\Throwable $e) {
            Log::error('ClicToPay initiate failed', ['type' => $type, 'id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Impossible de contacter ClicToPay. Réessayez plus tard.'], 502);
        }

        // Mirrors ManualPaymentController::submitProof() — the camper is now
        // considered "submitted", except the proof here is ClicToPay's own hosted
        // page rather than a self-reported transfer reference.
        $reservation->clictopay_order_id = $result['orderId'];
        $reservation->status = $isBalanceStep ? 'solde_soumis' : 'paiement_soumis';
        $reservation->payment_submitted_at = now();
        $reservation->save();

        return response()->json(['form_url' => $result['formUrl']]);
    }

    /**
     * GET /clictopay/return — ClicToPay's redirect target on a successful payment.
     * GET /clictopay/fail   — ClicToPay's redirect target on a declined/cancelled one.
     * Manual §3 "règle critique": arrival here is NEVER trusted alone — both call
     * getOrderStatusExtended.do and decide purely from orderStatus.
     */
    public function returnFromGateway(Request $request): RedirectResponse
    {
        return $this->handleCallback($request);
    }

    public function failFromGateway(Request $request): RedirectResponse
    {
        return $this->handleCallback($request);
    }

    private function handleCallback(Request $request): RedirectResponse
    {
        $type = (string) $request->query('type');
        $id = (int) $request->query('id');
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $redirect = fn (string $status) => redirect()->away(
            "{$frontend}/payment/result?status={$status}&type={$type}&id={$id}"
        );

        $reservation = $this->findAny($type, $id);
        if (!$reservation || !$reservation->clictopay_order_id) {
            return $redirect('error');
        }

        // These routes are necessarily public (ClicToPay drives the browser here),
        // so type/id alone are guessable. ClicToPay appends its own orderId to the
        // return/fail URL — when present it must match the order we registered for
        // this reservation, otherwise anyone could drive someone else's payment.
        $callbackOrderId = (string) $request->query('orderId', '');
        if ($callbackOrderId !== '' && !hash_equals((string) $reservation->clictopay_order_id, $callbackOrderId)) {
            Log::warning('ClicToPay callback orderId mismatch', [
                'type' => $type, 'id' => $id,
                'expected' => $reservation->clictopay_order_id, 'received' => $callbackOrderId,
            ]);

            return $redirect('error');
        }

        try {
            $statusResponse = $this->gateway->getStatus($reservation->clictopay_order_id);
        } catch (\Throwable $e) {
            Log::error('ClicToPay status check failed', ['type' => $type, 'id' => $id, 'error' => $e->getMessage()]);

            return $redirect('error');
        }

        $orderStatus = (int) ($statusResponse['orderStatus'] ?? -1);

        // orderStatus 0 = order registered, not yet paid; 5 = 3DS authentication
        // still in flight (manual §6.1). Neither is a decline — landing on returnUrl
        // without having actually paid produces exactly this — so the reservation
        // must stay awaiting payment instead of being marked invalid. The hourly
        // payments:reconcile-clictopay command settles it once it reaches a
        // terminal status.
        if (in_array($orderStatus, [0, 5], true)) {
            Log::info('ClicToPay callback: order not settled yet, left pending', [
                'type' => $type, 'id' => $id, 'orderStatus' => $orderStatus,
            ]);

            return $redirect('pending');
        }

        // Never confirm on orderStatus alone: the debited amount and currency must
        // match what we registered, so a stale or tampered order can't settle a
        // reservation for the wrong sum.
        if ($orderStatus === 2 && !$this->amountMatches($reservation, $statusResponse)) {
            Log::error('ClicToPay amount/currency mismatch — payment NOT confirmed', [
                'type' => $type, 'id' => $id,
                'orderId' => $reservation->clictopay_order_id,
                'reported' => ['amount' => $statusResponse['amount'] ?? null, 'currency' => $statusResponse['currency'] ?? null],
                'expected_millimes' => $this->expectedMillimes($reservation),
            ]);

            return $redirect('error');
        }

        $outcome = $orderStatus === 2
            ? ReservationLedgerService::confirmSubmittedPayment($type, $id, null)
            : ReservationLedgerService::rejectSubmittedPayment($type, $id);

        Log::info('ClicToPay callback processed', [
            'type' => $type, 'id' => $id, 'orderStatus' => $orderStatus, 'outcome' => $outcome,
        ]);

        if (!isset($outcome['error'])) {
            $this->notifyUser($outcome['userId'], $outcome['notifTitle'], $outcome['notifBody']);
        }

        return $redirect($orderStatus === 2 ? 'success' : 'failed');
    }

    /** Millimes we registered for the tranche currently awaiting settlement. */
    private function expectedMillimes(mixed $reservation): int
    {
        $tnd = (float) ($reservation->status === 'solde_soumis'
            ? ($reservation->amount_later ?? 0)
            : ($reservation->amount_now ?? 0));

        return (int) round($tnd * 1000);
    }

    private function amountMatches(mixed $reservation, array $statusResponse): bool
    {
        $expected = $this->expectedMillimes($reservation);
        if ($expected <= 0) {
            return false;
        }

        $currency = (string) ($statusResponse['currency'] ?? '788');

        return (int) ($statusResponse['amount'] ?? 0) === $expected && $currency === '788';
    }

    private function isMobile(Request $request): bool
    {
        return (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', (string) $request->userAgent());
    }

    private function findOwn(string $type, int $id): mixed
    {
        $userId = Auth::id();

        return match ($type) {
            'events' => Reservations_events::where('id', $id)->where('user_id', $userId)->first(),
            'centres' => Reservations_centre::where('id', $id)->where('user_id', $userId)->first(),
            'materielles' => Reservations_materielles::where('id', $id)->where('user_id', $userId)->first(),
            default => null,
        };
    }

    private function findAny(string $type, int $id): mixed
    {
        return match ($type) {
            'events' => Reservations_events::find($id),
            'centres' => Reservations_centre::find($id),
            'materielles' => Reservations_materielles::find($id),
            default => null,
        };
    }

    private function notifyUser(int $userId, string $title, string $content): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $data = ['title' => $title, 'content' => $content, 'type' => 'payment', 'priority' => 'high'];

        try {
            $user->notify(new CustomNotification($data, ['in_app']));
        } catch (\Throwable $e) {
            Log::warning('ClicToPayController: in-app notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return;
        }

        $latest = $user->notifications()->latest()->first();
        if ($latest) {
            try {
                event(new NewNotificationCreated(
                    userId: $user->id,
                    notificationId: $latest->id,
                    title: $title,
                    content: $content,
                    type: 'payment',
                    priority: 'high',
                ));
            } catch (\Throwable $e) {
                Log::warning('ClicToPayController: WebSocket broadcast failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }
}
