<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\Payments;
use App\Models\RefundRequest;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPaymentController extends Controller
{
    private const ADMIN_ACTION_PASSWORD = '50734671';

    private function checkPassword(Request $request): bool
    {
        return $request->password === self::ADMIN_ACTION_PASSWORD;
    }

    /* ══════════════════════════════════════════════════════════════
     *  PAYMENTS
     * ══════════════════════════════════════════════════════════════ */

    public function index(Request $request)
    {
        $query = Payments::with([
            'user:id,first_name,last_name,email,avatar',
            'event:id,title,price',
            'reservation:id,payment_id,status,nbr_place',
        ])->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                  ->orWhere('konnect_payment_id', 'like', "%{$s}%")
                  ->orWhereHas('user', fn($u) => $u
                      ->where('first_name', 'like', "%{$s}%")
                      ->orWhere('last_name',  'like', "%{$s}%")
                      ->orWhere('email',       'like', "%{$s}%"))
                  ->orWhereHas('event', fn($e) => $e->where('title', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function show($id)
    {
        $payment = Payments::with([
            'user:id,first_name,last_name,email,avatar,phone_number',
            'event:id,title,price,start_date,description',
            'reservation:id,payment_id,status,nbr_place',
        ])->findOrFail($id);

        $refund = RefundRequest::where('payment_id', $id)->first();

        return response()->json([
            'success' => true,
            'data'    => array_merge($payment->toArray(), ['refund_request' => $refund]),
        ]);
    }

    public function stats()
    {
        $totalRevenue    = Payments::where('status', 'paid')->sum('montant');
        $totalNet        = Payments::where('status', 'paid')->sum('net_revenue');
        $totalCommission = Payments::where('status', 'paid')->sum('commission');
        $pending         = Payments::where('status', 'pending')->count();
        $paid            = Payments::where('status', 'paid')->count();
        $failed          = Payments::where('status', 'failed')->count();
        $refunded        = Payments::whereIn('status', ['refunded_partial', 'refunded_total'])->count();
        $total           = Payments::count();

        $pendingRefunds     = RefundRequest::where('status', 'en_attente')->count();
        $pendingWithdrawals = WithdrawalRequest::where('status', 'en_attente')->count();

        $totalBalances  = Balance::sum('solde_disponible');
        $totalWithdrawn = Balance::sum('total_retire');

        // ── Wallet commission stats (real revenue) ─────────────────────────
        $walletCredits = WalletTransaction::where('type', 'credit')
            ->selectRaw('
                SUM(amount_gross)      as total_volume,
                SUM(commission_amount) as total_commission,
                SUM(net_amount)        as total_net_paid,
                COUNT(*)               as total_transactions
            ')
            ->first();

        $walletBySource = WalletTransaction::where('type', 'credit')
            ->selectRaw('reference_type, SUM(commission_amount) as commission, SUM(amount_gross) as volume, COUNT(*) as count')
            ->groupBy('reference_type')
            ->get()
            ->keyBy('reference_type');

        $walletStats = [
            'total_volume'        => round((float) ($walletCredits->total_volume      ?? 0), 2),
            'total_commission'    => round((float) ($walletCredits->total_commission  ?? 0), 2),
            'total_net_paid'      => round((float) ($walletCredits->total_net_paid    ?? 0), 2),
            'total_transactions'  => (int) ($walletCredits->total_transactions         ?? 0),
            'by_source'           => [
                'centre_reservation'   => [
                    'commission' => round((float) ($walletBySource['centre_reservation']?->commission   ?? 0), 2),
                    'volume'     => round((float) ($walletBySource['centre_reservation']?->volume       ?? 0), 2),
                    'count'      => (int) ($walletBySource['centre_reservation']?->count                ?? 0),
                ],
                'event_reservation'    => [
                    'commission' => round((float) ($walletBySource['event_reservation']?->commission    ?? 0), 2),
                    'volume'     => round((float) ($walletBySource['event_reservation']?->volume        ?? 0), 2),
                    'count'      => (int) ($walletBySource['event_reservation']?->count                 ?? 0),
                ],
                'materiel_reservation' => [
                    'commission' => round((float) ($walletBySource['materiel_reservation']?->commission ?? 0), 2),
                    'volume'     => round((float) ($walletBySource['materiel_reservation']?->volume     ?? 0), 2),
                    'count'      => (int) ($walletBySource['materiel_reservation']?->count              ?? 0),
                ],
            ],
            'pending_withdrawals' => $pendingWithdrawals,
            'pending_refunds'     => $pendingRefunds,
            'total_balances'      => round((float) $totalBalances,  2),
            'total_withdrawn'     => round((float) $totalWithdrawn, 2),
        ];

        return response()->json([
            'success' => true,
            'data'    => array_merge(
                compact('totalRevenue', 'totalNet', 'totalCommission',
                        'pending', 'paid', 'failed', 'refunded', 'total',
                        'pendingRefunds', 'pendingWithdrawals',
                        'totalBalances', 'totalWithdrawn'),
                ['wallet' => $walletStats]
            ),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'   => 'required|in:pending,paid,failed,refunded_partial,refunded_total',
            'password' => 'required|string',
        ]);

        if (! $this->checkPassword($request)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $payment = Payments::findOrFail($id);
        $oldStatus = $payment->status;
        $payment->update(['status' => $request->status]);

        // Si paiement passé à "paid", créditer la balance de l'organisateur
        if ($request->status === 'paid' && $oldStatus !== 'paid') {
            $this->creditOrganizerBalance($payment);
            // Confirmer la réservation liée
            Reservations_events::where('payment_id', $payment->id)
                ->whereIn('status', ['en_attente_paiement', 'pending'])
                ->update(['status' => 'confirmée']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour.',
            'data'    => $payment->fresh(['user:id,first_name,last_name,email', 'event:id,title,price']),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
     *  REFUNDS
     * ══════════════════════════════════════════════════════════════ */

    public function refunds(Request $request)
    {
        $query = RefundRequest::with([
            'payment.user:id,first_name,last_name,email',
            'payment.event:id,title,price',
            'reservationEvent:id,status,nbr_place',
            'reservationCentre.user:id,first_name,last_name,email',
            'reservationCentre.centre:id,first_name,last_name,email',
        ])->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $refunds = $query->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $refunds]);
    }

    public function approveRefund(Request $request, $id)
    {
        $request->validate(['password' => 'required|string']);

        if (! $this->checkPassword($request)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $refund = RefundRequest::findOrFail($id);

        if ($refund->status !== 'en_attente') {
            return response()->json(['success' => false, 'message' => 'Cette demande a déjà été traitée.'], 400);
        }

        DB::transaction(function () use ($refund) {
            $refund->update(['status' => 'accepté']);

            // ── Wallet refund for centre reservation ──────────────────────────
            if ($refund->payment_channel === 'wallet' && $refund->reservation_centre_id) {
                $reservation = Reservations_centre::find($refund->reservation_centre_id);
                if ($reservation) {
                    // Debit only what the centre actually received (net after commission)
                    $netToDebit = $refund->net_amount ?? $refund->montant_rembourse;
                    $centreBalance = Balance::forUser($reservation->centre_id);
                    if ($centreBalance->solde_disponible >= $netToDebit) {
                        $centreBalance->debiter($netToDebit);
                    }
                    WalletTransaction::logDebit(
                        $reservation->centre_id, 'refund_out', $netToDebit,
                        'centre_reservation', $reservation->id,
                        "Remboursement déduit — réservation #{$reservation->id}",
                        $reservation->user_id
                    );

                    // Credit the camper the full gross amount they originally paid
                    $gross = (float) $refund->montant_rembourse;
                    $camperBalance = Balance::forUser($reservation->user_id);
                    $camperBalance->crediter($gross);
                    $camperBalance->increment('total_rembourse', $gross);
                    WalletTransaction::logCredit(
                        $reservation->user_id, 'refund_in',
                        $gross, 0, 0, $gross,
                        'centre_reservation', $reservation->id,
                        "Remboursement reçu — réservation #{$reservation->id}",
                        $reservation->centre_id
                    );
                }
                return; // nothing more to do for wallet-centre refunds
            }

            // ── Standard Konnect payment refund ───────────────────────────────
            if ($refund->payment_id) {
                $payment = Payments::find($refund->payment_id);
                if ($payment) {
                    $isTotal = $refund->montant_rembourse >= $payment->montant;
                    $payment->update([
                        'status' => $isTotal ? 'refunded_total' : 'refunded_partial',
                    ]);

                    // Créditer la balance du CAMPER (remboursement)
                    $camperBalance = Balance::forUser($payment->user_id);
                    $camperBalance->crediter($refund->montant_rembourse);
                    $camperBalance->increment('total_rembourse', $refund->montant_rembourse);

                    // Débiter la balance de l'ORGANISATEUR si paiement était paid
                    $reservation = Reservations_events::where('payment_id', $payment->id)->first();
                    if ($reservation && $reservation->group_id) {
                        $orgBalance = Balance::forUser($reservation->group_id);
                        if ($orgBalance->solde_disponible >= $refund->montant_rembourse) {
                            $orgBalance->debiter($refund->montant_rembourse);
                        }
                    }
                }
            }

            // Mettre à jour le statut de la réservation
            if ($refund->reservation_event_id) {
                $reservation = Reservations_events::find($refund->reservation_event_id);
                if ($reservation) {
                    $statusMap = [
                        'refunded_total'   => 'remboursée_totale',
                        'refunded_partial' => 'remboursée_partielle',
                    ];
                    $payment = $refund->payment;
                    if ($payment) {
                        $reservation->update([
                            'status' => $statusMap[$payment->status] ?? 'remboursée_totale',
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Remboursement approuvé. Balance camper créditée.',
            'data'    => $refund->fresh(['payment.user:id,first_name,last_name,email', 'payment.event:id,title,price']),
        ]);
    }

    public function rejectRefund(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
            'reason'   => 'nullable|string|max:500',
        ]);

        if (! $this->checkPassword($request)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $refund = RefundRequest::findOrFail($id);

        if ($refund->status !== 'en_attente') {
            return response()->json(['success' => false, 'message' => 'Cette demande a déjà été traitée.'], 400);
        }

        $refund->update(['status' => 'refusé']);

        return response()->json([
            'success' => true,
            'message' => 'Remboursement refusé.',
            'data'    => $refund->fresh(['payment.user:id,first_name,last_name,email', 'payment.event:id,title,price']),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
     *  BALANCES
     * ══════════════════════════════════════════════════════════════ */

    public function balances(Request $request)
    {
        $query = Balance::with('user:id,first_name,last_name,email,avatar,role_id')
            ->orderByDesc('solde_disponible');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('user', fn($u) => $u
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name',  'like', "%{$s}%")
                ->orWhere('email',       'like', "%{$s}%"));
        }

        if ($request->filled('min_solde')) {
            $query->where('solde_disponible', '>=', $request->min_solde);
        }

        $balances = $query->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $balances]);
    }

    public function adjustBalance(Request $request, $userId)
    {
        $request->validate([
            'montant'   => 'required|numeric',
            'type'      => 'required|in:credit,debit',
            'note'      => 'nullable|string|max:500',
            'password'  => 'required|string',
        ]);

        if (! $this->checkPassword($request)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $balance = Balance::forUser($userId);

        if ($request->type === 'credit') {
            $balance->crediter($request->montant);
        } else {
            if ($balance->solde_disponible < $request->montant) {
                return response()->json(['success' => false, 'message' => 'Solde insuffisant.'], 400);
            }
            $balance->debiter($request->montant);
        }

        return response()->json([
            'success' => true,
            'message' => 'Balance ajustée.',
            'data'    => $balance->fresh(['user:id,first_name,last_name,email']),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
     *  WITHDRAWALS
     * ══════════════════════════════════════════════════════════════ */

    public function withdrawals(Request $request)
    {
        $query = WithdrawalRequest::with('user:id,first_name,last_name,email,avatar')
            ->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('user', fn($u) => $u
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name',  'like', "%{$s}%")
                ->orWhere('email',       'like', "%{$s}%"));
        }

        $withdrawals = $query->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $withdrawals]);
    }

    public function approveWithdrawal(Request $request, $id)
    {
        $request->validate([
            'password'   => 'required|string',
            'admin_note' => 'nullable|string|max:500',
        ]);

        if (! $this->checkPassword($request)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $withdrawal = WithdrawalRequest::findOrFail($id);

        if (! in_array($withdrawal->status, ['en_attente', 'en_cours'])) {
            return response()->json(['success' => false, 'message' => 'Cette demande a déjà été traitée.'], 400);
        }

        DB::transaction(function () use ($withdrawal, $request) {
            // Débiter la balance
            $balance = Balance::forUser($withdrawal->user_id);

            if ($balance->solde_disponible < $withdrawal->montant) {
                abort(422, 'Solde insuffisant pour effectuer ce retrait.');
            }

            $balance->debiter($withdrawal->montant);
            $balance->increment('total_retire', $withdrawal->montant);

            $withdrawal->update([
                'status'       => 'approuvé',
                'admin_note'   => $request->admin_note,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Retrait approuvé. Balance débitée.',
            'data'    => $withdrawal->fresh('user:id,first_name,last_name,email'),
        ]);
    }

    public function completeWithdrawal(Request $request, $id)
    {
        $request->validate([
            'password'   => 'required|string',
            'admin_note' => 'nullable|string|max:500',
        ]);

        if (! $this->checkPassword($request)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $withdrawal = WithdrawalRequest::findOrFail($id);

        if ($withdrawal->status !== 'approuvé') {
            return response()->json(['success' => false, 'message' => 'Le retrait doit d\'abord être approuvé.'], 400);
        }

        $withdrawal->update([
            'status'       => 'complété',
            'admin_note'   => $request->admin_note ?? $withdrawal->admin_note,
            'processed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Retrait marqué comme complété.',
            'data'    => $withdrawal->fresh('user:id,first_name,last_name,email'),
        ]);
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $request->validate([
            'password'   => 'required|string',
            'admin_note' => 'nullable|string|max:500',
        ]);

        if (! $this->checkPassword($request)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $withdrawal = WithdrawalRequest::findOrFail($id);

        if (! in_array($withdrawal->status, ['en_attente', 'en_cours'])) {
            return response()->json(['success' => false, 'message' => 'Cette demande a déjà été traitée.'], 400);
        }

        // Re-créditer la balance (la demande est annulée)
        $balance = Balance::forUser($withdrawal->user_id);
        // Le montant n'a pas encore été débité (pas encore approuvé), donc rien à rembourser.

        $withdrawal->update([
            'status'       => 'rejeté',
            'admin_note'   => $request->admin_note,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de retrait refusée.',
            'data'    => $withdrawal->fresh('user:id,first_name,last_name,email'),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
     *  WALLET PAYMENTS
     * ══════════════════════════════════════════════════════════════ */

    public function walletPayments(Request $request)
    {
        $query = WalletTransaction::with([
                'user:id,first_name,last_name,email,avatar',
                'relatedUser:id,first_name,last_name,email,avatar',
            ])
            ->where('type', 'credit')
            ->latest();

        if ($request->filled('reference_type') && $request->reference_type !== 'all') {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('user', fn($u) => $u
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name',  'like', "%{$s}%")
                ->orWhere('email',       'like', "%{$s}%"));
        }

        $txns = $query->paginate($request->get('per_page', 15));

        // Map beneficiary = recipient (user), payer = counterpart (relatedUser)
        // For older records without relatedUser, fall back to reservation lookup
        $col = $txns->getCollection();

        $centreIds   = $col->where('reference_type', 'centre_reservation')->whereNull('related_user_id')->pluck('reference_id')->filter()->unique()->values();
        $eventIds    = $col->where('reference_type', 'event_reservation')->whereNull('related_user_id')->pluck('reference_id')->filter()->unique()->values();
        $materielIds = $col->where('reference_type', 'materiel_reservation')->whereNull('related_user_id')->pluck('reference_id')->filter()->unique()->values();

        $centreRes   = $centreIds->isNotEmpty()   ? Reservations_centre::whereIn('id', $centreIds)->get(['id','user_id'])->keyBy('id')               : collect();
        $eventRes    = $eventIds->isNotEmpty()    ? \App\Models\Reservations_events::whereIn('id', $eventIds)->get(['id','user_id'])->keyBy('id')     : collect();
        $materielRes = $materielIds->isNotEmpty() ? \App\Models\Reservations_materielles::whereIn('id', $materielIds)->get(['id','user_id'])->keyBy('id') : collect();

        $fallbackIds = collect();
        $fallbackMap = [];
        foreach ($col as $txn) {
            if ($txn->related_user_id) continue;
            $rid = $txn->reference_id;
            $payerId = null;
            if ($txn->reference_type === 'centre_reservation' && $rid && isset($centreRes[$rid])) {
                $payerId = $centreRes[$rid]->user_id;
            } elseif ($txn->reference_type === 'event_reservation' && $rid && isset($eventRes[$rid])) {
                $payerId = $eventRes[$rid]->user_id;
            } elseif ($txn->reference_type === 'materiel_reservation' && $rid && isset($materielRes[$rid])) {
                $payerId = $materielRes[$rid]->user_id;
            }
            if ($payerId) {
                $fallbackMap[$txn->id] = $payerId;
                $fallbackIds->push($payerId);
            }
        }

        $fallbackUsers = $fallbackIds->isNotEmpty()
            ? \App\Models\User::whereIn('id', $fallbackIds->unique())->get(['id','first_name','last_name','email'])->keyBy('id')
            : collect();

        $col->transform(function ($txn) use ($fallbackMap, $fallbackUsers) {
            $txn->beneficiary = $txn->user;
            $payer = $txn->related_user;
            if (!$payer && isset($fallbackMap[$txn->id])) {
                $payer = $fallbackUsers[$fallbackMap[$txn->id]] ?? null;
            }
            $txn->payer = $payer;
            return $txn;
        });

        $txns->setCollection($col);

        return response()->json(['success' => true, 'data' => $txns]);
    }

    /* ══════════════════════════════════════════════════════════════
     *  Private helpers
     * ══════════════════════════════════════════════════════════════ */

    private function creditOrganizerBalance(Payments $payment): void
    {
        // Chercher l'organisateur via la réservation liée
        $reservation = Reservations_events::where('payment_id', $payment->id)->first();
        if ($reservation && $reservation->group_id) {
            $orgBalance = Balance::forUser($reservation->group_id);
            $orgBalance->crediter($payment->net_revenue);
        }
    }
}
