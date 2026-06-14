<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservations_events;
use App\Models\Reservations_centre;
use App\Models\Reservations_materielles;
use App\Models\User;
use App\Notifications\CustomNotification;
use App\Events\NewNotificationCreated;
use Illuminate\Support\Facades\Log;

class CancelOverdueBalanceReservations extends Command
{
    protected $signature   = 'payments:cancel-overdue-balances';
    protected $description = 'Cancel reservations whose balance_due_at has passed without the balance being paid.';

    private array $models = [
        'events'      => Reservations_events::class,
        'centres'     => Reservations_centre::class,
        'materielles' => Reservations_materielles::class,
    ];

    public function handle(): int
    {
        $cancelled = 0;

        foreach ($this->models as $label => $model) {
            $overdue = $model::where('status', 'confirmée_solde_en_attente')
                ->where('balance_due_at', '<', now())
                ->get();

            foreach ($overdue as $reservation) {
                $reservation->status = 'annulée_solde_impayé';
                $reservation->save();

                if ($reservation->user_id) {
                    $this->notifyUser(
                        $reservation->user_id,
                        $reservation->payment_reference ?? ''
                    );
                }

                $cancelled++;
                $this->line("  [{$label}] #{$reservation->id} cancelled (ref: {$reservation->payment_reference})");
            }
        }

        $this->info("{$cancelled} overdue balance reservation(s) cancelled.");
        Log::info('payments:cancel-overdue-balances', ['cancelled' => $cancelled]);

        return Command::SUCCESS;
    }

    private function notifyUser(int $userId, string $ref): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $title   = 'Réservation annulée — solde impayé';
        $content = "Votre solde restant ({$ref}) n'a pas été reçu avant l'échéance. "
                 . "Votre réservation a été annulée. Contactez-nous si vous pensez qu'il s'agit d'une erreur.";

        try {
            $user->notify(new CustomNotification([
                'title'    => $title,
                'content'  => $content,
                'type'     => 'payment',
                'priority' => 'high',
            ], ['in_app']));
        } catch (\Throwable $e) {
            Log::warning('CancelOverdueBalances: notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
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
                Log::warning('CancelOverdueBalances: WebSocket broadcast failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }
}
