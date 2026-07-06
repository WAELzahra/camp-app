<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Task A-04 — payment reconciliation.
 * Admin sees every transaction; providers/users see only their own.
 * Same filters and CSV export for both.
 */
class PaymentTransactionController extends Controller
{
    /** GET /admin/payment-transactions (all users) */
    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->filtered($request)
                ->withSum('refunds as refunded_total', 'amount')
                ->paginate($request->integer('per_page', 25)),
        ]);
    }

    /** GET /my/payment-transactions (own only) */
    public function myIndex(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->filtered($request, $request->user()->id)->paginate($request->integer('per_page', 25)),
        ]);
    }

    /** GET /admin/payment-transactions/export | /my/payment-transactions/export */
    public function export(Request $request, bool $adminScope = false): StreamedResponse
    {
        $query = $this->filtered($request, $adminScope ? null : $request->user()->id);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Gateway', 'Type', 'Amount', 'Currency', 'Status', 'Gateway Reference', 'Reservation ID', 'Reservation Type', 'User ID']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $t) {
                    fputcsv($out, [
                        $t->created_at?->toDateTimeString(), $t->gateway, $t->payment_type,
                        $t->amount, $t->currency, $t->status, $t->gateway_reference,
                        $t->reservation_id, $t->reservation_type, $t->user_id,
                    ]);
                }
            });
            fclose($out);
        }, 'payment-transactions-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function adminExport(Request $request): StreamedResponse
    {
        return $this->export($request, true);
    }

    public function myExport(Request $request): StreamedResponse
    {
        return $this->export($request, false);
    }

    /**
     * POST /admin/payment-transactions/{transaction}/refund
     * Records a manual refund as a NEW linked row — the original is never
     * modified (Task A-04 §4D). Partial refunds allowed up to the original.
     */
    public function refund(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        abort_if($transaction->payment_type === 'refund', 422, 'Impossible de rembourser un remboursement.');
        abort_if($transaction->status !== 'completed', 422, 'Seules les transactions complétées sont remboursables.');

        $alreadyRefunded = (float) $transaction->refunds()->sum('amount');
        $remaining = round((float) $transaction->amount - $alreadyRefunded, 2);
        abort_if($remaining <= 0, 422, 'Transaction déjà intégralement remboursée.');

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:' . $remaining,
            'note'   => 'nullable|string|max:255',
        ]);
        $amount = round((float) ($validated['amount'] ?? $remaining), 2);

        $refund = $transaction->createRefund($amount, array_filter([
            'recorded_by' => $request->user()->id,
            'note'        => $validated['note'] ?? null,
        ]));

        $transaction->user?->notify(new \App\Notifications\CustomNotification([
            'title'   => 'Remboursement enregistré',
            'content' => "Un remboursement de {$amount} TND a été enregistré pour votre paiement" . ($transaction->reservation_id ? " (réservation #{$transaction->reservation_id})" : '') . '.',
            'type'    => 'payment',
            'priority' => 'high',
        ]));

        return response()->json([
            'success' => true,
            'message' => "Remboursement de {$amount} TND enregistré.",
            'data'    => $refund,
        ]);
    }

    /* ───────────── Gateway console (admin) ───────────── */

    /** GET /admin/payment-gateways */
    public function gateways(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PaymentGateway::orderBy('id')->get()]);
    }

    /** GET /payment-gateways/active — public: only active gateways shown to users. */
    public function activeGateways(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PaymentGateway::active()->orderBy('id')->get(['key', 'label'])]);
    }

    /**
     * PATCH /admin/payment-gateways/{gateway}/toggle
     * At least one gateway must always remain active.
     */
    public function toggleGateway(PaymentGateway $gateway): JsonResponse
    {
        if ($gateway->is_active && PaymentGateway::active()->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Au moins une passerelle de paiement doit rester active.',
            ], 422);
        }

        $gateway->update(['is_active' => !$gateway->is_active]);

        return response()->json(['success' => true, 'data' => $gateway->fresh()]);
    }

    /* ───────────── shared filtering ───────────── */

    private function filtered(Request $request, ?int $userId = null)
    {
        $query = PaymentTransaction::query()->latest();

        if ($userId !== null)                 $query->where('user_id', $userId);
        if ($request->filled('gateway'))      $query->where('gateway', $request->input('gateway'));
        if ($request->filled('status'))       $query->where('status', $request->input('status'));
        if ($request->filled('payment_type')) $query->where('payment_type', $request->input('payment_type'));
        if ($request->filled('date_from'))    $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to'))      $query->whereDate('created_at', '<=', $request->input('date_to'));

        return $query;
    }
}
