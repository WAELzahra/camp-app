<?php

namespace App\Http\Controllers\Admin;

use App\Events\NewNotificationCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ConfirmWalletRechargeRequest;
use App\Http\Requests\Payment\RejectPaymentRequest;
use App\Models\Balance;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Models\User;
use App\Models\WalletRechargeRequest;
use App\Models\WalletTransaction;
use App\Notifications\CustomNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin endpoints for reviewing and confirming/rejecting manual bank transfers.
 *
 * All 3 reservation types are handled through a single {type}/{id} pattern:
 *   type: events | centres | materielles
 */
class AdminPaymentReviewController extends Controller
{
    /**
     * GET /admin/payments/pending
     * Returns all reservations with status paiement_soumis or solde_soumis,
     * across all 3 types.
     */
    public function pending(): JsonResponse
    {
        $statuses = ['paiement_soumis', 'solde_soumis'];

        $events = Reservations_events::with(['user', 'group'])
            ->whereIn('status', $statuses)
            ->where('payment_method', 'manual')
            ->latest('payment_submitted_at')
            ->get()
            ->map(fn ($r) => $this->formatRow($r, 'events'));

        $centres = Reservations_centre::with(['user'])
            ->whereIn('status', $statuses)
            ->where('payment_method', 'manual')
            ->latest('payment_submitted_at')
            ->get()
            ->map(fn ($r) => $this->formatRow($r, 'centres'));

        $materielles = Reservations_materielles::with(['user', 'materielle'])
            ->whereIn('status', $statuses)
            ->where('payment_method', 'manual')
            ->latest('payment_submitted_at')
            ->get()
            ->map(fn ($r) => $this->formatRow($r, 'materielles'));

        $walletRecharges = WalletRechargeRequest::with('user')
            ->where('status', 'paiement_soumis')
            ->latest('submitted_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'wallet',
                'method' => $r->method,
                'payment_reference' => $r->method === 'bank_transfer'
                                            ? $r->transfer_reference
                                            : $r->payment_reference,
                'transfer_reference' => $r->transfer_reference,
                'status' => $r->status,
                'payment_option' => 'full',
                'amount_now' => $r->amount,
                'amount_later' => 0,
                'balance_due_at' => null,
                'payment_submitted_at' => $r->submitted_at,
                'camper_name' => $r->user?->first_name.' '.$r->user?->last_name,
                'camper_email' => $r->user?->email,
            ]);

        return response()->json([
            'data' => $events->concat($centres)->concat($materielles)->concat($walletRecharges)
                ->sortByDesc('payment_submitted_at')->values(),
        ]);
    }

    /**
     * POST /admin/payments/{type}/{id}/confirm
     * Marks a submitted manual payment as confirmed, advancing the reservation status.
     */
    public function confirm(string $type, int $id): JsonResponse
    {
        $reservation = $this->findAny($type, $id);
        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable.'], 404);
        }

        if (!in_array($reservation->status, ['paiement_soumis', 'solde_soumis'])) {
            return response()->json([
                'message' => 'Aucun paiement en attente de validation pour cette réservation.',
            ], 422);
        }

        $wasBalance = ($reservation->status === 'solde_soumis');
        $newStatus = $this->resolveConfirmedStatus($reservation, $type);

        $reservation->status = $newStatus;
        $reservation->payment_confirmed_at = now();
        $reservation->confirmed_by = Auth::id();
        if ($wasBalance) {
            $reservation->amount_later = 0; // balance settled in full
        }
        $reservation->save();

        $this->notifyUser(
            $reservation->user_id,
            $wasBalance ? 'Solde confirmé ✓' : 'Paiement confirmé ✓',
            $wasBalance
                ? "Votre solde restant ({$reservation->payment_reference}) a été validé. Votre réservation est entièrement payée."
                : "Votre paiement ({$reservation->payment_reference}) a été validé. Votre réservation est en attente de confirmation par le prestataire.",
            'high'
        );

        return response()->json([
            'message' => 'Paiement confirmé.',
            'status' => $newStatus,
            'id' => $id,
            'type' => $type,
        ]);
    }

    /**
     * POST /admin/payments/{type}/{id}/reject
     * Rejects a submitted payment — camper is asked to retry.
     */
    public function reject(string $type, int $id, RejectPaymentRequest $request): JsonResponse
    {

        $reservation = $this->findAny($type, $id);
        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable.'], 404);
        }

        if (!in_array($reservation->status, ['paiement_soumis', 'solde_soumis'])) {
            return response()->json([
                'message' => 'Aucun paiement en attente de validation pour cette réservation.',
            ], 422);
        }

        // A rejected balance reverts to the host-accepted state (balance still owed) so
        // the camper can resubmit; a rejected initial payment becomes paiement_invalide.
        $wasBalance = ($reservation->status === 'solde_soumis');
        $reservation->status = $wasBalance
            ? match ($type) {
                'events' => 'confirmée', 'centres' => 'approved', default => 'confirmed'
            }
        : 'paiement_invalide';
        $reservation->save();

        $this->notifyUser(
            $reservation->user_id,
            $wasBalance ? 'Solde non validé' : 'Virement non trouvé',
            $wasBalance
                ? "Nous n'avons pas pu vérifier votre virement de solde ({$reservation->payment_reference}). Veuillez vérifier et soumettre à nouveau."
                : "Nous n'avons pas pu vérifier votre virement ({$reservation->payment_reference}). Veuillez vérifier la référence et soumettre à nouveau.",
            'high'
        );

        return response()->json([
            'message' => 'Paiement rejeté. Le camper est notifié de soumettre à nouveau.',
            'status' => $reservation->status,
        ]);
    }

    /**
     * POST /admin/payments/wallet/{id}/confirm
     * Confirms a wallet recharge: credits the user's balance and logs the transaction.
     */
    public function confirmWallet(ConfirmWalletRechargeRequest $request, int $id): JsonResponse
    {
        // The admin may correct the credited amount to the amount actually received
        // (e.g. the camper claimed 100 but only 80 arrived). Defaults to the claim.
        $validated = $request->validated();

        $req = WalletRechargeRequest::find($id);

        if (!$req || $req->status !== 'paiement_soumis') {
            return response()->json(['message' => 'Demande introuvable ou déjà traitée.'], 404);
        }

        $alreadyTreated = false;
        $credited = 0.0;
        $reference = '';

        DB::transaction(function () use ($id, $validated, &$req, &$alreadyTreated, &$credited, &$reference) {
            // Re-fetch under a row lock so two concurrent confirmations can't double-credit.
            $req = WalletRechargeRequest::whereKey($id)->lockForUpdate()->first();
            if (!$req || $req->status !== 'paiement_soumis') {
                $alreadyTreated = true;

                return;
            }

            $credited = isset($validated['amount']) && $validated['amount'] !== null
                ? round((float) $validated['amount'], 2)
                : (float) $req->amount;
            $reference = $req->method === 'bank_transfer'
                ? ($req->transfer_reference ?? '')
                : ($req->payment_reference ?? '');

            $balance = Balance::forUser($req->user_id);
            $balance->crediter($credited);

            WalletTransaction::logCredit(
                userId: $req->user_id,
                category: 'deposit',
                amountGross: $credited,
                commissionRate: 0,
                commissionAmount: 0,
                netAmount: $credited,
                referenceType: 'wallet_recharge',
                referenceId: $req->id,
                description: "Rechargement wallet — {$reference}",
            );

            $req->status = 'confirmed';
            $req->credited_amount = $credited;
            $req->confirmed_at = now();
            $req->confirmed_by = Auth::id();
            $req->save();
        });

        if ($alreadyTreated) {
            return response()->json(['message' => 'Demande introuvable ou déjà traitée.'], 404);
        }

        $this->notifyUser(
            $req->user_id,
            'Wallet rechargé ✓',
            "Votre rechargement de {$credited} TND ({$reference}) a été validé. Votre solde a été mis à jour.",
            'high'
        );

        return response()->json([
            'message' => 'Rechargement confirmé. Solde mis à jour.',
            'id' => $id,
            'credited_amount' => $credited,
        ]);
    }

    /**
     * POST /admin/payments/wallet/{id}/reject
     * Rejects a wallet recharge request.
     */
    public function rejectWallet(int $id): JsonResponse
    {
        $req = WalletRechargeRequest::find($id);

        if (!$req || $req->status !== 'paiement_soumis') {
            return response()->json(['message' => 'Demande introuvable ou déjà traitée.'], 404);
        }

        $req->status = 'rejected';
        $req->save();

        $this->notifyUser(
            $req->user_id,
            'Rechargement non validé',
            "Nous n'avons pas pu vérifier votre virement de rechargement ({$req->payment_reference}). Veuillez vérifier et soumettre à nouveau.",
            'high'
        );

        return response()->json(['message' => 'Rechargement rejeté.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function notifyUser(int $userId, string $title, string $content, string $priority = 'medium'): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $data = ['title' => $title, 'content' => $content, 'type' => 'payment', 'priority' => $priority];

        try {
            $user->notify(new CustomNotification($data, ['in_app']));
        } catch (\Throwable $e) {
            \Log::warning('AdminPaymentReview: in-app notification failed', [
                'user_id' => $userId, 'error' => $e->getMessage(),
            ]);

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
                    priority: $priority,
                ));
            } catch (\Throwable $e) {
                // WebSocket server unavailable — notification is already saved in DB, real-time push is best-effort
                \Log::warning('AdminPaymentReview: WebSocket broadcast failed', [
                    'user_id' => $userId, 'error' => $e->getMessage(),
                ]);
            }
        }
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

    private function formatRow(mixed $r, string $type): array
    {
        return [
            'id' => $r->id,
            'type' => $type,
            'payment_reference' => $r->payment_reference,
            'status' => $r->status,
            'payment_option' => $r->payment_option,
            'amount_now' => $r->amount_now,
            'amount_later' => $r->amount_later,
            'balance_due_at' => $r->balance_due_at,
            'payment_submitted_at' => $r->payment_submitted_at,
            'camper_name' => $r->user?->first_name.' '.$r->user?->last_name,
            'camper_email' => $r->user?->email,
        ];
    }

    /**
     * Determine the next status after the admin confirms a manual payment.
     *
     * - Balance payment (solde_soumis): the host already accepted, so restore the
     *   host-accepted state — now fully paid (amount_later is zeroed by the caller).
     * - Initial payment (paiement_soumis), full OR deposit: the reservation enters
     *   the host's review queue. If it was a deposit, amount_later stays > 0 and the
     *   balance is collected after the host accepts.
     */
    private function resolveConfirmedStatus(mixed $r, string $type): string
    {
        if ($r->status === 'solde_soumis') {
            return match ($type) {
                'events' => 'confirmée',
                'centres' => 'approved',
                default => 'confirmed', // materielles
            };
        }

        // Initial payment confirmed → host review queue.
        return match ($type) {
            'events' => 'en_attente_validation',
            'centres' => 'pending',
            default => 'pending', // materielles
        };
    }
}
