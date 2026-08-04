<?php

namespace App\Console\Commands;

use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Services\Payments\ClicToPayGateway;
use App\Services\Payments\ReservationLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * ClicToPay's Basic API has no webhook — the returnUrl/failUrl redirect is the
 * ONLY automatic signal (see ClicToPayController::handleCallback()). If the
 * camper's browser never makes it back (closed tab, dropped connection, crash
 * mid-redirect), ClicToPay may have taken the money while the reservation sits
 * unconfirmed forever. This polls getOrderStatusExtended.do for any reservation
 * still awaiting that callback, past a grace period, and runs it through the
 * exact same confirm/reject logic the callback itself uses.
 */
class ReconcileClicToPayPayments extends Command
{
    protected $signature = 'payments:reconcile-clictopay';
    protected $description = 'Poll ClicToPay for reservations whose payment callback never arrived, and confirm/reject them.';

    /** Minutes to wait before assuming the callback is never coming. */
    private const GRACE_MINUTES = 15;

    private array $models = [
        'events' => Reservations_events::class,
        'centres' => Reservations_centre::class,
        'materielles' => Reservations_materielles::class,
    ];

    public function handle(ClicToPayGateway $gateway): int
    {
        $reconciled = 0;
        $cutoff = now()->subMinutes(self::GRACE_MINUTES);

        foreach ($this->models as $type => $model) {
            $pending = $model::whereNotNull('clictopay_order_id')
                ->whereIn('status', ['paiement_soumis', 'solde_soumis'])
                ->where('payment_submitted_at', '<', $cutoff)
                ->get();

            foreach ($pending as $reservation) {
                try {
                    $statusResponse = $gateway->getStatus($reservation->clictopay_order_id);
                } catch (\Throwable $e) {
                    Log::warning('ReconcileClicToPayPayments: status check failed', [
                        'type' => $type, 'id' => $reservation->id, 'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                $orderStatus = (int) ($statusResponse['orderStatus'] ?? -1);

                // Still awaiting payment on ClicToPay's side (orderStatus 0/5) — leave
                // it for the next run rather than prematurely rejecting it.
                if (in_array($orderStatus, [0, 5], true)) {
                    continue;
                }

                $outcome = $orderStatus === 2
                    ? ReservationLedgerService::confirmSubmittedPayment($type, $reservation->id, null)
                    : ReservationLedgerService::rejectSubmittedPayment($type, $reservation->id);

                if (!isset($outcome['error'])) {
                    $reconciled++;
                    $this->line("  [{$type}] #{$reservation->id} reconciled — orderStatus={$orderStatus}");
                }
            }
        }

        $this->info("{$reconciled} ClicToPay payment(s) reconciled.");
        Log::info('payments:reconcile-clictopay', ['reconciled' => $reconciled]);

        return Command::SUCCESS;
    }
}
