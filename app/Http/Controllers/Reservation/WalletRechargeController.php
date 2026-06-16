<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\WalletRechargeRequest;
use App\Services\ManualPaymentService;
use App\Services\PaymentReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletRechargeController extends Controller
{
    /**
     * POST /my/wallet/recharge
     * Creates a pending recharge request and returns the payment reference + Flouci link.
     */
    public function initiate(Request $request): JsonResponse
    {
        if (!ManualPaymentService::isEnabled()) {
            return response()->json(['message' => 'Le paiement par virement n\'est pas disponible.'], 422);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:10000',
        ]);

        $req = WalletRechargeRequest::create([
            'user_id' => Auth::id(),
            'amount'  => round((float) $data['amount'], 2),
            'status'  => 'pending',
        ]);

        $req->payment_reference = PaymentReferenceService::forWalletRecharge(Auth::id());
        $req->save();

        return response()->json([
            'data' => [
                'id'                => $req->id,
                'amount'            => $req->amount,
                'payment_reference' => $req->payment_reference,
                'status'            => $req->status,
                'flouci_link'       => ManualPaymentService::flouciLink(),
            ],
        ], 201);
    }

    /**
     * POST /my/wallet/recharge/bank-transfer
     * One-step direct bank-transfer claim: the camper has already transferred the
     * money to the platform's bank account and posts the amount + their own bank
     * reference. The request is submitted immediately for admin review.
     */
    public function bankTransferClaim(Request $request): JsonResponse
    {
        if (!(bool) \App\Models\PlatformSetting::get('bank_transfer_enabled', false)) {
            return response()->json(['message' => 'Le rechargement par virement bancaire n\'est pas disponible.'], 422);
        }

        $data = $request->validate([
            'amount'             => 'required|numeric|min:1|max:10000',
            'transfer_reference' => 'required|string|max:120',
        ]);

        $req = WalletRechargeRequest::create([
            'user_id'            => Auth::id(),
            'amount'             => round((float) $data['amount'], 2),
            'method'             => 'bank_transfer',
            'transfer_reference' => trim($data['transfer_reference']),
            'status'             => 'paiement_soumis',
            'submitted_at'       => now(),
        ]);

        return response()->json([
            'message' => 'Demande envoyée. L\'admin validera votre virement après réception.',
            'data'    => [
                'id'                 => $req->id,
                'amount'             => $req->amount,
                'transfer_reference' => $req->transfer_reference,
                'status'             => $req->status,
            ],
        ], 201);
    }

    /**
     * POST /my/wallet/recharge/{id}/submit
     * Marks the recharge as submitted (camper claims to have sent the transfer).
     */
    public function submit(int $id): JsonResponse
    {
        $req = WalletRechargeRequest::where('user_id', Auth::id())->find($id);

        if (!$req) {
            return response()->json(['message' => 'Demande introuvable.'], 404);
        }

        if (!in_array($req->status, ['pending', 'rejected'])) {
            return response()->json(['message' => 'Cette demande a déjà été soumise ou confirmée.'], 422);
        }

        $req->status       = 'paiement_soumis';
        $req->submitted_at = now();
        $req->save();

        return response()->json([
            'message' => 'Virement soumis. L\'admin validera sous 24h.',
            'status'  => 'paiement_soumis',
        ]);
    }

    /**
     * GET /my/wallet/recharges
     * Lists all recharge requests for the authenticated user.
     */
    public function list(Request $request): JsonResponse
    {
        $recharges = WalletRechargeRequest::where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        return response()->json($recharges);
    }
}
