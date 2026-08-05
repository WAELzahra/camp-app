<?php

namespace App\Console\Commands;

use App\Services\Payments\ClicToPayGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Runs the ClicToPay "cahier de recettes" error cases that the normal booking
 * flow can never produce (CTP-06 missing amount, CTP-07 duplicate orderNumber,
 * CTP-08 unknown orderId).
 *
 * The cahier explicitly rejects results captured with simulation tools such as
 * Postman — they must come from the merchant's own system. This command issues
 * the calls from the application itself, with the application's own credentials
 * and HTTP stack, and prints the raw JSON to paste into column G.
 *
 * The happy-path cases (CTP-01 to CTP-05) are NOT here: they must be produced by
 * a real booking on the site, and their JSON is already written to the log by
 * ClicToPayGateway on every call.
 */
class ClicToPayRecetteCases extends Command
{
    protected $signature = 'clictopay:recette
                            {--case=all : all|06|07|08}
                            {--order-number=ORDER-DUP-TEST : orderNumber used by CTP-07 — change it to re-run, a used one errors on both calls}';
    protected $description = 'Run the ClicToPay cahier-de-recettes error cases (CTP-06/07/08) and print the raw JSON responses.';

    public function handle(ClicToPayGateway $gateway): int
    {
        $case = (string) $this->option('case');
        $base = rtrim((string) config('services.clictopay.base_url'), '/') . '/';

        $this->line("Base URL: {$base}");
        $this->newLine();

        if (in_array($case, ['all', '06'], true)) {
            $this->runCase('CTP-06 — register.do sans le champ "amount"', fn () => $this->post($base . 'register.do', [
                'orderNumber' => 'CTP06-' . time(),
                'currency'    => '788',
                'returnUrl'   => $this->returnUrl(),
                // 'amount' deliberately omitted → expect errorCode 4
            ]));
        }

        if (in_array($case, ['all', '07'], true)) {
            // Same orderNumber twice → the second must not silently create a second order.
            $dup = (string) $this->option('order-number');
            $this->runCase("CTP-07 (1/2) — register.do orderNumber={$dup}", fn () => $this->post($base . 'register.do', [
                'orderNumber' => $dup,
                'amount'      => 10000,
                'currency'    => '788',
                'returnUrl'   => $this->returnUrl(),
                'failUrl'     => $this->failUrl(),
            ]));
            $this->runCase("CTP-07 (2/2) — même orderNumber={$dup}", fn () => $this->post($base . 'register.do', [
                'orderNumber' => $dup,
                'amount'      => 10000,
                'currency'    => '788',
                'returnUrl'   => $this->returnUrl(),
                'failUrl'     => $this->failUrl(),
            ]));
        }

        if (in_array($case, ['all', '08'], true)) {
            // Goes through the production code path — same client the callback uses.
            $this->runCase('CTP-08 — getOrderStatusExtended.do avec un orderId inexistant', fn () => $gateway->getStatus(
                '00000000-0000-0000-0000-000000000000'
            ));
        }

        $this->newLine();
        $this->info('Terminé. Les mêmes réponses figurent aussi dans storage/logs/laravel.log.');

        return Command::SUCCESS;
    }

    /** @param callable(): array $call */
    private function runCase(string $label, callable $call): void
    {
        $this->comment("── {$label}");

        try {
            $data = $call();
        } catch (\Throwable $e) {
            $this->error('  Exception: ' . $e->getMessage());

            return;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->line($json);
        Log::info('clictopay:recette', ['case' => $label, 'response' => $data]);
        $this->newLine();
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
