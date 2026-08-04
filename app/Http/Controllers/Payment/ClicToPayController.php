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
                "Réservation {$reservation->payment_reference}"
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

        $reservation = $this->findAny($type, $id);
        if (!$reservation || !$reservation->clictopay_order_id) {
            return redirect()->away("{$frontend}/payment/result?status=error&type={$type}&id={$id}");
        }

        try {
            $statusResponse = $this->gateway->getStatus($reservation->clictopay_order_id);
        } catch (\Throwable $e) {
            Log::error('ClicToPay status check failed', ['type' => $type, 'id' => $id, 'error' => $e->getMessage()]);

            return redirect()->away("{$frontend}/payment/result?status=error&type={$type}&id={$id}");
        }

        $orderStatus = (int) ($statusResponse['orderStatus'] ?? -1);

        $outcome = $orderStatus === 2
            ? ReservationLedgerService::confirmSubmittedPayment($type, $id, null)
            : ReservationLedgerService::rejectSubmittedPayment($type, $id);

        Log::info('ClicToPay callback processed', [
            'type' => $type, 'id' => $id, 'orderStatus' => $orderStatus, 'outcome' => $outcome,
        ]);

        if (!isset($outcome['error'])) {
            $this->notifyUser($outcome['userId'], $outcome['notifTitle'], $outcome['notifBody']);
        }

        $resultStatus = $orderStatus === 2 ? 'success' : 'failed';

        return redirect()->away("{$frontend}/payment/result?status={$resultStatus}&type={$type}&id={$id}");
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
