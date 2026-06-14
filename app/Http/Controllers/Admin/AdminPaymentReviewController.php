<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservations_events;
use App\Models\Reservations_centre;
use App\Models\Reservations_materielles;
use App\Models\WalletRechargeRequest;
use App\Models\Balance;
use App\Models\WalletTransaction;
use App\Models\User;
use App\Notifications\CustomNotification;
use App\Events\NewNotificationCreated;
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
            ->map(fn($r) => $this->formatRow($r, 'events'));

        $centres = Reservations_centre::with(['user'])
            ->whereIn('status', $statuses)
            ->where('payment_method', 'manual')
            ->latest('payment_submitted_at')
            ->get()
            ->map(fn($r) => $this->formatRow($r, 'centres'));

        $materielles = Reservations_materielles::with(['user', 'materielle'])
            ->whereIn('status', $statuses)
            ->where('payment_method', 'manual')
            ->latest('payment_submitted_at')
            ->get()
            ->map(fn($r) => $this->formatRow($r, 'materielles'));

        $walletRecharges = WalletRechargeRequest::with('user')
            ->where('status', 'paiement_soumis')
            ->latest('submitted_at')
            ->get()
            ->map(fn($r) => [
                'id'                   => $r->id,
                'type'                 => 'wallet',
                'payment_reference'    => $r->payment_reference,
                'status'               => $r->status,
                'payment_option'       => 'full',
                'amount_now'           => $r->amount,
                'amount_later'         => 0,
                'balance_due_at'       => null,
                'payment_submitted_at' => $r->submitted_at,
                'camper_name'          => $r->user?->first_name . ' ' . $r->user?->last_name,
                'camper_email'         => $r->user?->email,
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

        $newStatus = $this->resolveConfirmedStatus($reservation, $type);
        $reservation->status              = $newStatus;
        $reservation->payment_confirmed_at= now();
        $reservation->confirmed_by        = Auth::id();
        $reservation->save();

        $isBalance = ($reservation->status === 'entièrement_payée');
        $this->notifyUser(
            $reservation->user_id,
            $isBalance ? 'Solde confirmé ✓' : 'Paiement confirmé ✓',
            $isBalance
                ? "Votre solde restant ({$reservation->payment_reference}) a été validé. Votre réservation est entièrement payée."
                : "Votre virement ({$reservation->payment_reference}) a été validé. Votre réservation est confirmée.",
            'high'
        );

        return response()->json([
            'message' => 'Paiement confirmé.',
            'status'  => $newStatus,
            'id'      => $id,
            'type'    => $type,
        ]);
    }

    /**
     * POST /admin/payments/{type}/{id}/reject
     * Rejects a submitted payment — camper is asked to retry.
     */
    public function reject(string $type, int $id, Request $request): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $reservation = $this->findAny($type, $id);
        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable.'], 404);
        }

        if (!in_array($reservation->status, ['paiement_soumis', 'solde_soumis'])) {
            return response()->json([
                'message' => 'Aucun paiement en attente de validation pour cette réservation.',
            ], 422);
        }

        $reservation->status = 'paiement_invalide';
        $reservation->save();

        $this->notifyUser(
            $reservation->user_id,
            'Virement non trouvé',
            "Nous n'avons pas pu vérifier votre virement ({$reservation->payment_reference}). Veuillez vérifier la référence et soumettre à nouveau.",
            'high'
        );

        return response()->json([
            'message' => 'Paiement rejeté. Le camper est notifié de soumettre à nouveau.',
            'status'  => 'paiement_invalide',
        ]);
    }

    /**
     * POST /admin/payments/wallet/{id}/confirm
     * Confirms a wallet recharge: credits the user's balance and logs the transaction.
     */
    public function confirmWallet(int $id): JsonResponse
    {
        $req = WalletRechargeRequest::find($id);

        if (!$req || $req->status !== 'paiement_soumis') {
            return response()->json(['message' => 'Demande introuvable ou déjà traitée.'], 404);
        }

        $alreadyTreated = false;

        DB::transaction(function () use ($id, &$req, &$alreadyTreated) {
            // Re-fetch under a row lock so two concurrent confirmations can't double-credit.
            $req = WalletRechargeRequest::whereKey($id)->lockForUpdate()->first();
            if (!$req || $req->status !== 'paiement_soumis') {
                $alreadyTreated = true;
                return;
            }

            $balance = Balance::forUser($req->user_id);
            $balance->crediter((float) $req->amount);

            WalletTransaction::logCredit(
                userId:           $req->user_id,
                category:         'deposit',
                amountGross:      (float) $req->amount,
                commissionRate:   0,
                commissionAmount: 0,
                netAmount:        (float) $req->amount,
                referenceType:    'wallet_recharge',
                referenceId:      $req->id,
                description:      "Rechargement wallet — {$req->payment_reference}",
            );

            $req->status       = 'confirmed';
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
            "Votre rechargement de {$req->amount} TND ({$req->payment_reference}) a été validé. Votre solde a été mis à jour.",
            'high'
        );

        return response()->json([
            'message' => 'Rechargement confirmé. Solde mis à jour.',
            'id'      => $id,
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
        if (!$user) return;

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
                    userId:         $user->id,
                    notificationId: $latest->id,
                    title:          $title,
                    content:        $content,
                    type:           'payment',
                    priority:       $priority,
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
        return match($type) {
            'events'     => Reservations_events::find($id),
            'centres'    => Reservations_centre::find($id),
            'materielles'=> Reservations_materielles::find($id),
            default      => null,
        };
    }

    private function formatRow(mixed $r, string $type): array
    {
        return [
            'id'                   => $r->id,
            'type'                 => $type,
            'payment_reference'    => $r->payment_reference,
            'status'               => $r->status,
            'payment_option'       => $r->payment_option,
            'amount_now'           => $r->amount_now,
            'amount_later'         => $r->amount_later,
            'balance_due_at'       => $r->balance_due_at,
            'payment_submitted_at' => $r->payment_submitted_at,
            'camper_name'          => $r->user?->first_name . ' ' . $r->user?->last_name,
            'camper_email'         => $r->user?->email,
        ];
    }

    /**
     * Determine the next status after admin confirms payment.
     *
     * - If it's the initial payment (paiement_soumis):
     *     - Deposit option + amount_later > 0 → confirmée_solde_en_attente
     *     - Full payment → reservation type's "approved/confirmed" status
     * - If it's the balance payment (solde_soumis) → entièrement_payée
     */
    private function resolveConfirmedStatus(mixed $r, string $type): string
    {
        if ($r->status === 'solde_soumis') {
            return 'entièrement_payée';
        }

        // Initial payment submitted
        if ($r->payment_option === 'deposit' && ((float)($r->amount_later ?? 0)) > 0) {
            return 'confirmée_solde_en_attente';
        }

        // Full payment confirmed — advance to the normal "approved" state per type
        return match($type) {
            'events'  => 'confirmée',
            'centres' => 'approved',
            default   => 'confirmed', // materielles
        };
    }
}
