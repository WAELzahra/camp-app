<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Produces the ClicToPay "cahier de recettes" error cases that the normal
 * booking flow can never generate — CTP-06 (register.do without amount),
 * CTP-07 (duplicate orderNumber) and CTP-08 (unknown orderId) — because the
 * production code deliberately prevents all three.
 *
 * The cahier rejects results captured with simulation tools such as Postman:
 * they must come from the merchant's own system. Everything here therefore runs
 * with the application's own credentials and HTTP stack, and every raw response
 * is written to the application log as well as returned.
 *
 * Shared by ClicToPayRecetteCases (console) and ClicToPayRecetteController
 * (browser), so both channels emit byte-identical evidence.
 */
class ClicToPayRecetteRunner
{
    public const CASES = ['all', '06', '07', '08'];

    public const DEFAULT_DUP_ORDER_NUMBER = 'ORDER-DUP-TEST';

    public function __construct(private ClicToPayGateway $gateway)
    {
    }

    /**
     * @return list<array{id: string, label: string, response: array}> one entry
     *         per API call — CTP-07 yields two, the duplicate being the point.
     */
    public function run(string $case = 'all', string $dupOrderNumber = self::DEFAULT_DUP_ORDER_NUMBER): array
    {
        $base = rtrim((string) config('services.clictopay.base_url'), '/') . '/';
        $results = [];

        if (in_array($case, ['all', '06'], true)) {
            $results[] = $this->capture('CTP-06', 'register.do sans le champ "amount"', fn () => $this->post($base . 'register.do', [
                'orderNumber' => 'CTP06-' . time(),
                'currency'    => '788',
                'returnUrl'   => $this->returnUrl(),
                // 'amount' deliberately omitted → expect errorCode 4
            ]));
        }

        if (in_array($case, ['all', '07'], true)) {
            // Same orderNumber twice → the second must not silently create a second order.
            $dup = $dupOrderNumber !== '' ? $dupOrderNumber : self::DEFAULT_DUP_ORDER_NUMBER;
            foreach ([1, 2] as $attempt) {
                $results[] = $this->capture(
                    "CTP-07 ({$attempt}/2)",
                    "register.do orderNumber={$dup}",
                    fn () => $this->post($base . 'register.do', [
                        'orderNumber' => $dup,
                        'amount'      => 10000,
                        'currency'    => '788',
                        'returnUrl'   => $this->returnUrl(),
                        'failUrl'     => $this->failUrl(),
                    ])
                );
            }
        }

        if (in_array($case, ['all', '08'], true)) {
            // Goes through the production code path — same client the callback uses.
            $results[] = $this->capture(
                'CTP-08',
                'getOrderStatusExtended.do avec un orderId inexistant',
                fn () => $this->gateway->getStatus('00000000-0000-0000-0000-000000000000')
            );
        }

        return $results;
    }

    /**
     * @param  callable(): array  $call
     * @return array{id: string, label: string, response: array}
     */
    private function capture(string $id, string $label, callable $call): array
    {
        try {
            $response = $call();
        } catch (\Throwable $e) {
            // Never abort the run: a failed case still has to be reported and
            // pasted into the cahier as-is.
            $response = ['_exception' => $e->getMessage()];
        }

        Log::info('clictopay:recette', ['case' => $id, 'label' => $label, 'response' => $response]);

        return ['id' => $id, 'label' => $label, 'response' => $response];
    }

    private function post(string $url, array $payload): array
    {
        $response = Http::asForm()->post($url, array_merge([
            'userName' => (string) config('services.clictopay.username'),
            'password' => (string) config('services.clictopay.password'),
            'language' => 'fr',
        ], $payload));

        return $response->json() ?? ['_raw' => $response->body(), '_http' => $response->status()];
    }

    private function returnUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/clictopay/return';
    }

    private function failUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/clictopay/fail';
    }
}
