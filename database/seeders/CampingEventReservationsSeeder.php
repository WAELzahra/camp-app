<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CampingEventReservationsSeeder
 *
 * Creates realistic reservations (reservations_events) AND the associated
 * payments for the 20 events inserted by CampingEventsSeeder.
 *
 * Design choices:
 *  - Each event has a declarative spec: how many confirmed / pending /
 *    cancelled-by-user / cancelled-by-organiser / refunded reservations.
 *  - Campeurs are selected deterministically (offset by event ID) so the
 *    seeder is reproducible.  The same campeur can hold reservations across
 *    multiple different events, but never twice for the same event.
 *  - Payments are inserted with insertGetId() so the FK in reservations_events
 *    points to a real payment row.
 *  - Platform commission: 10 % of the total amount paid.
 *  - Free events (price = 0) produce no payment record.
 *
 * Edge cases covered:
 *  - Fully-booked finished events (Ksar Ghilane, Hammamet, Dorsale)
 *  - Almost-full scheduled event (Jebel Chambi: 3 spots remaining)
 *  - Events with zero participants (Orbata, Nebhana)
 *  - Cancelled-by-organiser event (Zouaraa Annulée)
 *  - Refunded reservations (partial & total)
 *  - Last-minute pending reservations on popular upcoming events
 */
class CampingEventReservationsSeeder extends Seeder
{
    /** Platform commission rate applied on every paid reservation */
    private const COMMISSION_RATE = 0.10;

    public function run(): void
    {
        $now    = now()->toDateTimeString();
        $titles = array_keys($this->reservationSpecs());

        // ── Guard: events must exist ───────────────────────────────────────
        $events = DB::table('events')
            ->whereIn('title', $titles)
            ->get()
            ->keyBy('title');

        if ($events->isEmpty()) {
            $this->command?->warn(
                '⚠️  No campaign events found — run CampingEventsSeeder first.'
            );
            return;
        }

        // ── Idempotency guard ──────────────────────────────────────────────
        $eventIds = $events->pluck('id');
        if (DB::table('reservations_events')->whereIn('event_id', $eventIds)->exists()) {
            $this->command?->warn('⚠️  CampingEventReservationsSeeder already run — skipping.');
            return;
        }

        // ── Load campeurs with archetype ───────────────────────────────────
        $campeurRoleId = DB::table('roles')->where('name', 'campeur')->value('id');
        $campeurs      = DB::table('users')
            ->where('role_id', $campeurRoleId)
            ->select('id', 'first_name', 'last_name', 'email', 'preferences')
            ->get()
            ->map(function ($u) {
                $u->archetype = json_decode($u->preferences, true)['archetype'] ?? 'C';
                return $u;
            });

        // ── Process each event ─────────────────────────────────────────────
        $totalReservations = 0;
        $totalPayments     = 0;

        foreach ($this->reservationSpecs() as $title => $spec) {
            $event = $events[$title] ?? null;
            if (! $event) continue;

            [$resCount, $payCount] = $this->processEvent(
                $event, $spec, $campeurs, $now
            );
            $totalReservations += $resCount;
            $totalPayments     += $payCount;
        }

        $this->command?->info(
            "✅ {$totalReservations} reservations + {$totalPayments} payments inserted "
            . 'by CampingEventReservationsSeeder.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Core processing
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create all reservations (and payments) for a single event.
     * Returns [reservation_count, payment_count].
     */
    private function processEvent(
        object $event,
        array  $spec,
        $campeurs,
        string $now
    ): array {
        // Sort campeurs: preferred archetypes first, then fall back to rest
        $preferred = $spec['preferred_archetypes'] ?? ['A', 'B', 'C', 'D', 'E', 'F'];
        $sorted    = $campeurs->sortBy(function ($c) use ($preferred) {
            $pos = array_search($c->archetype, $preferred);
            return $pos === false ? 999 : $pos;
        })->values();

        // Offset starting index per-event so different events draw from
        // different parts of the campeur list (avoids always picking #1)
        $slotIndex = (int) $event->id % $sorted->count();
        $seen      = [];  // prevents duplicate (user, event) pairs

        // Build the ordered list of statuses to create
        $statusList = $this->buildStatusList($spec);

        $nbr        = $spec['nbr_place'] ?? 1;
        $price      = (float) $event->price;
        $resCount   = 0;
        $payCount   = 0;

        foreach ($statusList as $statusDef) {
            // Find the next campeur not yet reserved for this event
            $campeur = null;
            $tries   = 0;
            while ($tries < $sorted->count()) {
                $candidate = $sorted->get($slotIndex % $sorted->count());
                $slotIndex++;
                $tries++;
                if (! isset($seen[$candidate->id])) {
                    $seen[$candidate->id] = true;
                    $campeur = $candidate;
                    break;
                }
            }
            if (! $campeur) break; // all campeurs exhausted for this event

            $paymentId = null;
            $status    = $statusDef['status'];
            $payStatus = $statusDef['payment_status'] ?? null;

            // Create a payment row for paid events that have a billable status
            if ($price > 0 && $payStatus !== null) {
                $montant    = $price * $nbr;
                $commission = round($montant * self::COMMISSION_RATE, 2);

                $paymentId = DB::table('payments')->insertGetId([
                    'user_id'     => $campeur->id,
                    'event_id'    => $event->id,
                    'montant'     => $montant,
                    'description' => "Réservation : {$event->title}",
                    'status'      => $payStatus,
                    'commission'  => $commission,
                    'net_revenue' => round($montant - $commission, 2),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                $payCount++;
            }

            $arch = $campeur->archetype;

            // Determine payment method: card for luxury / expert events; otherwise wallet
            $usesCard = ($arch === 'E' || $price >= 100 || $event->difficulty === 'expert');

            DB::table('reservations_events')->insert([
                'user_id'              => $campeur->id,
                'event_id'             => $event->id,
                'group_id'             => $event->group_id,
                'name'                 => trim($campeur->first_name . ' ' . $campeur->last_name),
                'email'                => $campeur->email,
                'phone'                => null,
                'group_skill_level'    => $this->skillLevel($arch),
                'trip_purpose'         => $this->tripPurpose($arch),
                'nbr_place'            => $nbr,
                'payment_id'           => $paymentId,
                'status'               => $status,
                'created_by'           => null,
                'promo_code_id'        => null,
                'discount_amount'      => 0.00,
                'payment_method'       => $usesCard ? 'card' : 'wallet',
                'platform_fee_amount'  => $paymentId ? round($price * $nbr * self::COMMISSION_RATE, 2) : null,
                'platform_fee_rate'    => $paymentId ? (self::COMMISSION_RATE * 100) : null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            $resCount++;
        }

        return [$resCount, $payCount];
    }

    /**
     * Convert the declarative spec into a flat ordered list of
     * ['status' => ..., 'payment_status' => ...] entries.
     */
    private function buildStatusList(array $spec): array
    {
        $list = [];

        // Confirmed & paid
        for ($i = 0; $i < ($spec['confirmed'] ?? 0); $i++) {
            $list[] = ['status' => 'confirmée', 'payment_status' => 'paid'];
        }
        // Awaiting payment (user started checkout but hasn't paid yet)
        for ($i = 0; $i < ($spec['en_attente_paiement'] ?? 0); $i++) {
            $list[] = ['status' => 'en_attente_paiement', 'payment_status' => 'pending'];
        }
        // Awaiting organiser validation (no payment yet for free or manual events)
        for ($i = 0; $i < ($spec['en_attente_validation'] ?? 0); $i++) {
            $list[] = ['status' => 'en_attente_validation', 'payment_status' => null];
        }
        // Cancelled by user (last-minute cancellations)
        for ($i = 0; $i < ($spec['cancelled_user'] ?? 0); $i++) {
            $list[] = ['status' => 'annulée_par_utilisateur', 'payment_status' => null];
        }
        // Cancelled by organiser
        for ($i = 0; $i < ($spec['cancelled_org'] ?? 0); $i++) {
            $list[] = ['status' => 'annulée_par_organisateur', 'payment_status' => null];
        }
        // Partially refunded (user cancelled after partial refund window)
        for ($i = 0; $i < ($spec['refunded_partial'] ?? 0); $i++) {
            $list[] = ['status' => 'remboursée_partielle', 'payment_status' => 'refunded_partial'];
        }
        // Fully refunded
        for ($i = 0; $i < ($spec['refunded_total'] ?? 0); $i++) {
            $list[] = ['status' => 'remboursée_totale', 'payment_status' => 'refunded_total'];
        }

        return $list;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Archetype helpers  (mirrors ReservationsSeeder logic for consistency)
    // ──────────────────────────────────────────────────────────────────────────

    private function skillLevel(string $arch): string
    {
        return match ($arch) {
            'A', 'D' => ['advanced', 'mixed'][rand(0, 1)],
            'B', 'E' => 'beginner',
            'C'      => ['intermediate', 'mixed'][rand(0, 1)],
            'F'      => ['beginner', 'intermediate'][rand(0, 1)],
            default  => 'mixed',
        };
    }

    private function tripPurpose(string $arch): string
    {
        return match ($arch) {
            'A' => ['Randonnée solo', 'Trek aventure', 'Bivouac nature'][rand(0, 2)],
            'B' => ['Vacances famille', 'Weekend familial', 'Camping en famille'][rand(0, 2)],
            'C' => ['Weekend exploration', 'Randonnée nature', 'Découverte région'][rand(0, 2)],
            'D' => ['Expédition désert', 'Bivouac saharien', 'Trek désertique'][rand(0, 2)],
            'E' => ['Séjour romantique', 'Weekend glamping', 'Détente luxe nature'][rand(0, 2)],
            'F' => ['Sortie étudiante', 'Trip amis', 'Camping budget groupe'][rand(0, 2)],
            default => 'Camping loisirs',
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reservation specs
    //
    // Keys must exactly match the event titles in CampingEventsSeeder.
    // nbr_place: seats per reservation (families = 4, couples = 2, solo = 1).
    // preferred_archetypes: campeur archetypes most likely to attend this event.
    // ──────────────────────────────────────────────────────────────────────────
    private function reservationSpecs(): array
    {
        return [

            // ── FINISHED EVENTS ──────────────────────────────────────────────

            /**
             * Bivouac Douz — luxury, 6 confirmed (12 seats at nbr_place=2 = cap 12),
             * plus 2 last-minute cancellations.
             */
            'Bivouac Étoilé Douz — Nuit Saharienne' => [
                'confirmed'            => 6,
                'cancelled_user'       => 2,
                'nbr_place'            => 2,   // couples
                'preferred_archetypes' => ['D', 'E', 'A'],
            ],

            /**
             * Aïn Draham forest hike — 4 confirmed (easy, family-friendly),
             * 2 last-minute cancellations.
             */
            'Randonnée Forêt d\'Aïn Draham — Chênes-Liège' => [
                'confirmed'            => 4,
                'cancelled_user'       => 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['A', 'C', 'F'],
            ],

            /**
             * Ksar Ghilane — sold-out luxury oasis.
             * 8 confirmed (8 seats) + 2 refunded (refunded_total) = cap 10.
             * One participant asked for a partial refund after partial use.
             */
            'Nuit Étoilée à Ksar Ghilane — Oasis & Dunes' => [
                'confirmed'            => 8,
                'refunded_partial'     => 1,
                'refunded_total'       => 1,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['E', 'D'],
            ],

            /**
             * Cap Serrat coastal hike — 4 confirmed, 2 cancelled by user.
             */
            'Randonnée Littorale Cap Serrat' => [
                'confirmed'            => 4,
                'cancelled_user'       => 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['A', 'C', 'B'],
            ],

            /**
             * Jebel Zaghouan — 4 confirmed, 2 cancelled.
             */
            'Trek Jebel Zaghouan — Temple des Eaux' => [
                'confirmed'            => 4,
                'cancelled_user'       => 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['A', 'C'],
            ],

            /**
             * Oued Zitoun gorges — 3 confirmed, 1 cancelled.
             */
            'Trek Oued Zitoun & Gorges du Kef' => [
                'confirmed'            => 3,
                'cancelled_user'       => 1,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['A', 'D', 'C'],
            ],

            /**
             * Hammamet Family — FULLY BOOKED.
             * 10 confirmed × nbr_place=4 = 40 seats (= capacity).
             * 3 cancelled by user + 2 cancelled by organiser (overbooking edge-case).
             */
            'Grand Camping Familial Hammamet — Spring Edition' => [
                'confirmed'            => 10,
                'cancelled_user'       => 3,
                'cancelled_org'        => 2,
                'nbr_place'            => 4,   // full families
                'preferred_archetypes' => ['B', 'E', 'C'],
            ],

            /**
             * Tamerza oasis — 4 confirmed, 1 cancelled by user, 1 fully refunded
             * (participant cancelled the day before due to illness).
             */
            'Camping Oasis de Tamerza — Canyon & Cascade' => [
                'confirmed'            => 4,
                'cancelled_user'       => 1,
                'refunded_total'       => 1,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['D', 'E', 'A'],
            ],

            /**
             * Sidi Bou Said cultural hike — 4 confirmed, 2 cancelled.
             */
            'Randonnée Côtière Sidi Bou Said — Carthage' => [
                'confirmed'            => 4,
                'cancelled_user'       => 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['C', 'B', 'F'],
            ],

            // ── SCHEDULED EVENTS ─────────────────────────────────────────────

            /**
             * Zouaraa beach — 3 confirmed, 3 awaiting payment, 2 awaiting validation.
             * Typical mix for a newly-opened event.
             */
            'Camping Plage de Zouaraa — Été 2026' => [
                'confirmed'            => 3,
                'en_attente_paiement'  => 3,
                'en_attente_validation'=> 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['B', 'C', 'E'],
            ],

            /**
             * Jebel Chambi — ALMOST FULL edge case.
             * 17 confirmed (cap=20, remaining=3 as defined in the event).
             */
            'Ascension Jebel Chambi — Toit de Tunisie' => [
                'confirmed'            => 17,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['A', 'D'],
            ],

            /**
             * Gorges de Selja — 2 confirmed + 2 last-minute pending.
             */
            'Trek Gorges de Selja — Canyon Rouge' => [
                'confirmed'            => 2,
                'en_attente_paiement'  => 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['D', 'A'],
            ],

            /**
             * Beni Mtir — FREE event: no payment rows created.
             * 3 confirmed + 1 awaiting validation (organisers check newcomers).
             */
            'Camping Gratuit Beni Mtir — Lac & Forêt' => [
                'confirmed'            => 3,
                'en_attente_validation'=> 1,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['F', 'C', 'A'],
            ],

            /**
             * Tabarka diving camp — 2 confirmed + 2 pending.
             */
            'Camping & Plongée Tabarka — Récifs Coralliens' => [
                'confirmed'            => 2,
                'en_attente_paiement'  => 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['C', 'E', 'A'],
            ],

            /**
             * Djerba voyage — 3 confirmed, 4 awaiting payment (last-minute rush),
             * 2 awaiting validation (new user accounts, organiser reviewing).
             */
            'Voyage Découverte Djerba — Île des Rêves' => [
                'confirmed'            => 3,
                'en_attente_paiement'  => 4,
                'en_attente_validation'=> 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['B', 'E', 'C'],
            ],

            /**
             * Bou Kornine night hike — 3 confirmed + 2 pending.
             */
            'Randonnée Nocturne Jebel Bou Kornine' => [
                'confirmed'            => 3,
                'en_attente_paiement'  => 2,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['A', 'C', 'F'],
            ],

            // ── FULL EVENT ───────────────────────────────────────────────────

            /**
             * Traversée Dorsale — SOLD OUT expert expedition.
             * 15 confirmed × 1 seat = 15 (= capacity, remaining=0).
             */
            'Traversée de la Dorsale Tunisienne — 5 Jours' => [
                'confirmed'            => 15,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['A', 'D'],
            ],

            // ── CANCELED EVENT ───────────────────────────────────────────────

            /**
             * Camping Zouaraa Annulée — organiser cancelled after bad weather alert.
             * 5 reservations that were all cancelled by the organiser.
             * Event is FREE so no payment rows.
             */
            'Camping Sauvage Zouaraa — Édition Annulée' => [
                'cancelled_org'        => 5,
                'nbr_place'            => 1,
                'preferred_archetypes' => ['F', 'C', 'B'],
            ],

            // ── EMPTY EVENTS (no specs = no reservations seeded) ─────────────
            // 'Bivouac Jebel Orbata — Coucher de Soleil sur les Chotts' => []
            // 'Weekend Lac de Nebhana — Camping Libre'                  => []
        ];
    }
}
