<?php

namespace App\Console\Commands;

use App\Events\NewNotificationCreated;
use App\Models\Events;
use App\Models\Materielles;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Models\User;
use App\Notifications\CustomNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * A camper who picks bank transfer gets a reservation created immediately
 * (status 'pending_payment', spot/stock already held) and is then shown a
 * separate screen asking them to submit their bank transfer reference once
 * they've completed the transfer. If they never come back to that step —
 * e.g. they close the tab — the reservation was sitting in 'pending_payment'
 * forever with the held spot never released, since nothing revisits rows
 * that never got a payment_submitted_at at all (CancelOverdueBalanceReservations
 * only handles the *later* balance-due-date case for deposits already paid).
 */
class CancelUnsubmittedManualPayments extends Command
{
    protected $signature = 'payments:cancel-unsubmitted-manual';
    protected $description = 'Cancel manual-payment reservations whose camper never submitted a transfer reference within the grace period, releasing the held spot/stock.';

    /** Hours a camper has to submit their transfer reference before the hold is released. */
    private const GRACE_HOURS = 48;

    private array $models = [
        'events'      => Reservations_events::class,
        'centres'     => Reservations_centre::class,
        'materielles' => Reservations_materielles::class,
    ];

    /** Existing enum value reused per type — see status ENUM definitions in
     *  the reservations_* migrations. No new enum value needed. */
    private array $cancelStatus = [
        'events'      => 'annulée_par_utilisateur',
        'centres'     => 'canceled',
        'materielles' => 'cancelled_by_camper',
    ];

    public function handle(): int
    {
        $cancelled = 0;

        foreach ($this->models as $label => $model) {
            $stale = $model::where('status', 'pending_payment')
                ->where('payment_method', 'manual')
                ->whereNull('payment_submitted_at')
                ->where('created_at', '<', now()->subHours(self::GRACE_HOURS))
                ->get();

            foreach ($stale as $reservation) {
                $reservation->status = $this->cancelStatus[$label];
                $reservation->save();

                $this->releaseHold($label, $reservation);

                if ($reservation->user_id) {
                    $this->notifyUser($reservation->user_id, $reservation->payment_reference ?? '');
                }

                $cancelled++;
                $this->line("  [{$label}] #{$reservation->id} cancelled — no payment reference submitted within " . self::GRACE_HOURS . 'h');
            }
        }

        $this->info("{$cancelled} unsubmitted manual-payment reservation(s) cancelled.");
        Log::info('payments:cancel-unsubmitted-manual', ['cancelled' => $cancelled]);

        return Command::SUCCESS;
    }

    /**
     * Release whatever capacity this reservation was holding. Centres have
     * no counter to release — availability there is computed from active
     * reservations' date ranges, so flipping the status is enough to free
     * those dates back up.
     */
    private function releaseHold(string $label, $reservation): void
    {
        if ($label === 'events') {
            $event = Events::find($reservation->event_id);
            if ($event && $event->remaining_spots !== null) {
                $event->increment('remaining_spots', $reservation->nbr_place);
            }
        } elseif ($label === 'materielles') {
            Materielles::find($reservation->materielle_id)?->increment('quantite_dispo', $reservation->quantite);
        }
    }

    private function notifyUser(int $userId, string $ref): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $title = 'Réservation annulée — paiement non soumis';
        $content = 'Votre réservation'
            . ($ref ? " ({$ref})" : '')
            . " a été annulée car aucune référence de virement n'a été soumise dans les "
            . self::GRACE_HOURS . ' heures suivant la réservation. Vous pouvez réserver à nouveau si vous le souhaitez.';

        try {
            $user->notify(new CustomNotification([
                'title'    => $title,
                'content'  => $content,
                'type'     => 'payment',
                'priority' => 'high',
            ], ['in_app']));
        } catch (\Throwable $e) {
            Log::warning('CancelUnsubmittedManualPayments: notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
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
                    priority:       'high',
                ));
            } catch (\Throwable $e) {
                Log::warning('CancelUnsubmittedManualPayments: WebSocket broadcast failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }
}
