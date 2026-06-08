<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CampingEventNotificationsSeeder
 *
 * Generates realistic in-app notifications for the events seeded by the
 * CampingEventsSeeder / CampingEventReservationsSeeder suite.
 *
 * Notification types produced:
 *  1. reservation_confirmed   — sent to a campeur when their reservation is confirmed
 *  2. reservation_cancelled   — sent to campeurs whose reservation was cancelled by organiser
 *  3. event_reminder          — sent 3 days before a scheduled event to confirmed participants
 *  4. payment_confirmation    — sent after a successful payment on a paid event
 *
 * Technical notes:
 *  - Primary key is UUID (Laravel morphs pattern).
 *  - notifiable_type = 'App\Models\User'  (standard Laravel notification target).
 *  - data column is a JSON object with human-readable fields used by the front-end.
 *  - sender_id = the organiser's user ID for event-related notifications.
 *  - Notifications for the CANCELLED event have priority = 'high'.
 *  - Event reminders have channels = ["database","mail"].
 */
class CampingEventNotificationsSeeder extends Seeder
{
    private const NOTIFIABLE_TYPE = 'App\\Models\\User';

    public function run(): void
    {
        $now = now()->toDateTimeString();

        // ── Load campaign events ──────────────────────────────────────────
        $eventsData = DB::table('events')
            ->whereIn('title', $this->watchedTitles())
            ->select('id', 'title', 'start_date', 'group_id', 'price', 'status')
            ->get()
            ->keyBy('title');

        if ($eventsData->isEmpty()) {
            $this->command?->warn(
                '⚠️  No campaign events found — run CampingEventsSeeder first.'
            );
            return;
        }

        // ── Idempotency guard ──────────────────────────────────────────────
        $firstEventId = $eventsData->first()->id;
        $alreadySeeded = DB::table('notifications')
            ->whereJsonContains('data->event_id', $firstEventId)
            ->exists();

        if ($alreadySeeded) {
            $this->command?->warn('⚠️  CampingEventNotificationsSeeder already run — skipping.');
            return;
        }

        // ── Load reservations for campaign events ─────────────────────────
        $eventIds     = $eventsData->pluck('id');
        $reservations = DB::table('reservations_events')
            ->whereIn('event_id', $eventIds)
            ->select('id', 'user_id', 'event_id', 'status', 'payment_id', 'nbr_place')
            ->get();

        if ($reservations->isEmpty()) {
            $this->command?->warn(
                '⚠️  No reservations found — run CampingEventReservationsSeeder first.'
            );
            return;
        }

        // ── Index events by ID for quick lookup ───────────────────────────
        $eventsById = $eventsData->keyBy('id');

        $rows = [];

        foreach ($reservations as $res) {
            $event = $eventsById[$res->event_id] ?? null;
            if (! $event) continue;

            $senderId = $event->group_id;

            // 1. reservation_confirmed
            if ($res->status === 'confirmée') {
                $rows[] = $this->makeNotification(
                    type         : 'reservation_confirmed',
                    userId       : $res->user_id,
                    senderId     : $senderId,
                    priority     : 'medium',
                    channels     : ['database'],
                    data         : [
                        'event_id'       => $event->id,
                        'event_title'    => $event->title,
                        'start_date'     => $event->start_date,
                        'nbr_place'      => $res->nbr_place,
                        'reservation_id' => $res->id,
                        'title'          => 'Réservation confirmée !',
                        'message'        => "Votre réservation pour \"{$event->title}\" "
                            . "({$res->nbr_place} place(s)) a été confirmée. "
                            . "Rendez-vous le {$event->start_date} !",
                    ],
                    now: $now
                );

                // 2. payment_confirmation  (only for paid events)
                if ((float) $event->price > 0 && $res->payment_id) {
                    $montant = number_format((float) $event->price * $res->nbr_place, 2);
                    $rows[]  = $this->makeNotification(
                        type     : 'payment_confirmation',
                        userId   : $res->user_id,
                        senderId : null,   // system notification
                        priority : 'low',
                        channels : ['database'],
                        data     : [
                            'event_id'    => $event->id,
                            'event_title' => $event->title,
                            'payment_id'  => $res->payment_id,
                            'montant'     => $montant,
                            'title'       => 'Paiement confirmé',
                            'message'     => "Votre paiement de {$montant} TND pour "
                                . "\"{$event->title}\" a bien été reçu.",
                        ],
                        now: $now
                    );
                }

                // 3. event_reminder  (only for scheduled/full events, not finished)
                if (in_array($event->status, ['scheduled', 'full'])) {
                    $rows[] = $this->makeNotification(
                        type        : 'event_reminder',
                        userId      : $res->user_id,
                        senderId    : $senderId,
                        priority    : 'medium',
                        channels    : ['database', 'mail'],
                        data        : [
                            'event_id'    => $event->id,
                            'event_title' => $event->title,
                            'start_date'  => $event->start_date,
                            'title'       => 'Rappel — événement dans 3 jours',
                            'message'     => "N\'oubliez pas : \"{$event->title}\" "
                                . "commence le {$event->start_date}. "
                                . "Préparez votre équipement !",
                        ],
                        scheduledAt : date('Y-m-d H:i:s', strtotime($event->start_date . ' -3 days')),
                        now         : $now
                    );
                }
            }

            // 4. reservation_cancelled  (cancelled by organiser)
            if ($res->status === 'annulée_par_organisateur') {
                $rows[] = $this->makeNotification(
                    type     : 'reservation_cancelled',
                    userId   : $res->user_id,
                    senderId : $senderId,
                    priority : 'high',
                    channels : ['database', 'mail'],
                    data     : [
                        'event_id'       => $event->id,
                        'event_title'    => $event->title,
                        'reservation_id' => $res->id,
                        'title'          => 'Réservation annulée par l\'organisateur',
                        'message'        => "Votre réservation pour \"{$event->title}\" "
                            . "a été annulée par l\'organisateur. "
                            . "Si vous avez effectué un paiement, un remboursement intégral "
                            . "sera traité sous 5 à 7 jours ouvrés.",
                    ],
                    now: $now
                );
            }
        }

        // ── Batch insert ──────────────────────────────────────────────────
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }

        $this->command?->info(
            '✅ ' . count($rows) . ' notifications inserted by CampingEventNotificationsSeeder.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build a single notification row ready for DB insert.
     */
    private function makeNotification(
        string      $type,
        int         $userId,
        ?int        $senderId,
        string      $priority,
        array       $channels,
        array       $data,
        string      $now,
        ?string     $scheduledAt = null
    ): array {
        return [
            'id'               => (string) Str::uuid(),
            'type'             => $type,
            'notifiable_id'    => $userId,
            'notifiable_type'  => self::NOTIFIABLE_TYPE,
            'data'             => json_encode($data, JSON_UNESCAPED_UNICODE),
            'priority'         => $priority,
            'channels'         => json_encode($channels),
            'sender_id'        => $senderId,
            'read_at'          => null,
            'archived_at'      => null,
            'scheduled_at'     => $scheduledAt,
            'expires_at'       => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    /**
     * The event titles we care about for notification generation.
     * Must match CampingEventsSeeder exactly.
     */
    private function watchedTitles(): array
    {
        return [
            // Finished events
            'Bivouac Étoilé Douz — Nuit Saharienne',
            'Randonnée Forêt d\'Aïn Draham — Chênes-Liège',
            'Nuit Étoilée à Ksar Ghilane — Oasis & Dunes',
            'Randonnée Littorale Cap Serrat',
            'Trek Jebel Zaghouan — Temple des Eaux',
            'Trek Oued Zitoun & Gorges du Kef',
            'Grand Camping Familial Hammamet — Spring Edition',
            'Camping Oasis de Tamerza — Canyon & Cascade',
            'Randonnée Côtière Sidi Bou Said — Carthage',
            // Scheduled
            'Camping Plage de Zouaraa — Été 2026',
            'Ascension Jebel Chambi — Toit de Tunisie',
            'Trek Gorges de Selja — Canyon Rouge',
            'Camping Gratuit Beni Mtir — Lac & Forêt',
            'Camping & Plongée Tabarka — Récifs Coralliens',
            'Voyage Découverte Djerba — Île des Rêves',
            'Randonnée Nocturne Jebel Bou Kornine',
            // Full
            'Traversée de la Dorsale Tunisienne — 5 Jours',
            // Canceled
            'Camping Sauvage Zouaraa — Édition Annulée',
        ];
    }
}
