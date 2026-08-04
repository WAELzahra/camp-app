<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin HTTP client for ClicToPay's Basic REST API (register.do +
 * getOrderStatusExtended.do — see Manuel_Integration_ClicToPay_v1.0).
 *
 * No business logic here — status interpretation and money movement live in
 * ClicToPayController, mirroring how AdminPaymentReviewController::confirm()
 * already handles manual bank-transfer confirmations.
 */
class ClicToPayGateway
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('services.clictopay.base_url'), '/') . '/';
        $this->username = (string) config('services.clictopay.username');
        $this->password = (string) config('services.clictopay.password');
    }

    /**
     * register.do — creates the payment session, returns ['orderId' => ..., 'formUrl' => ...].
     * Throws RuntimeException on any non-zero errorCode (see manual §6.2).
     */
    public function register(
        string $orderNumber,
        float $amountTnd,
        string $returnUrl,
        string $failUrl,
        ?string $description = null,
    ): array {
        // amount is in millimes (minimal units), no decimal separator — 10 TND = 10000.
        $amountMillimes = (int) round($amountTnd * 1000);

        $response = Http::asForm()->post($this->baseUrl . 'register.do', [
            'userName'    => $this->username,
            'password'    => $this->password,
            'orderNumber' => $orderNumber,
            'amount'      => $amountMillimes,
            'currency'    => '788', // TND
            'returnUrl'   => $returnUrl,
            'failUrl'     => $failUrl,
            'description' => $description,
            'language'    => 'fr',
        ]);

        $data = $response->json() ?? [];

        if (!empty($data['errorCode']) && (int) $data['errorCode'] !== 0) {
            Log::error('ClicToPay register.do failed', ['orderNumber' => $orderNumber, 'response' => $data]);
            throw new RuntimeException($data['errorMessage'] ?? 'ClicToPay register.do error ' . $data['errorCode']);
        }

        if (empty($data['orderId']) || empty($data['formUrl'])) {
            Log::error('ClicToPay register.do returned no orderId/formUrl', ['orderNumber' => $orderNumber, 'response' => $data]);
            throw new RuntimeException('Réponse ClicToPay invalide.');
        }

        return ['orderId' => $data['orderId'], 'formUrl' => $data['formUrl']];
    }

    /**
     * getOrderStatusExtended.do — the ONLY authoritative confirmation of a payment
     * (manual §3, "règle critique" — a returnUrl redirect alone proves nothing).
     * Returns the full decoded response (orderStatus, actionCode, amount, cardAuthInfo, ...).
     */
    public function getStatus(string $orderId): array
    {
        $response = Http::asForm()->post($this->baseUrl . 'getOrderStatusExtended.do', [
            'userName' => $this->username,
            'password' => $this->password,
            'orderId'  => $orderId,
            'language' => 'fr',
        ]);

        $data = $response->json() ?? [];

        if (!empty($data['errorCode']) && (int) $data['errorCode'] !== 0) {
            Log::error('ClicToPay getOrderStatusExtended.do failed', ['orderId' => $orderId, 'response' => $data]);
        }

        return $data;
    }
}
