<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReservationsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('reservations_materielles')->truncate();
        DB::table('reservations_events')->truncate();
        DB::table('reservations_centres')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Load role IDs ─────────────────────────────────────────────────────
        $roles         = DB::table('roles')->pluck('id', 'name');
        $campeurRoleId = $roles['campeur'];
        $centreRoleId  = $roles['centre'];
        $groupeRoleId  = $roles['groupe'];

        // ── Load campeurs with archetype ──────────────────────────────────────
        $campeurs = DB::table('users')
            ->where('role_id', $campeurRoleId)
            ->select('id', 'first_name', 'last_name', 'email', 'preferences')
            ->get()
            ->map(function ($u) {
                $u->archetype = json_decode($u->preferences, true)['archetype'] ?? 'C';
                return $u;
            });

        // ── Load centre users keyed by email ──────────────────────────────────
        $centreUsers = DB::table('users')
            ->where('role_id', $centreRoleId)
            ->pluck('id', 'email')
            ->all();

        // ── Load groupe users keyed by email ──────────────────────────────────
        $groupeUsers = DB::table('users')
            ->where('role_id', $groupeRoleId)
            ->pluck('id', 'email')
            ->all();

        // ─────────────────────────────────────────────────────────────────────
        // 1. RESERVATIONS CENTRES
        // ─────────────────────────────────────────────────────────────────────
        $centreRows = [];
        $this->buildCentreReservations($campeurs, $centreUsers, $now, $centreRows);

        foreach (array_chunk($centreRows, 50) as $chunk) {
            DB::table('reservations_centres')->insert($chunk);
        }
        $this->command?->info('✅ ' . count($centreRows) . ' reservations_centres inserted.');

        // ─────────────────────────────────────────────────────────────────────
        // 2. RESERVATIONS EVENTS
        // ─────────────────────────────────────────────────────────────────────
        $events = DB::table('events')
            ->whereIn('status', ['finished', 'scheduled'])
            ->select('id', 'group_id', 'difficulty', 'event_type', 'status', 'price', 'capacity')
            ->get();

        $eventRows = [];
        $this->buildEventReservations($campeurs, $events, $now, $eventRows);

        foreach (array_chunk($eventRows, 50) as $chunk) {
            DB::table('reservations_events')->insert($chunk);
        }
        $this->command?->info('✅ ' . count($eventRows) . ' reservations_events inserted.');

        // ─────────────────────────────────────────────────────────────────────
        // 3. RESERVATIONS MATERIELLES
        // ─────────────────────────────────────────────────────────────────────
        $materielles = DB::table('materielles as m')
            ->join('materielles_categories as mc', 'mc.id', '=', 'm.category_id')
            ->where('m.status', 'up')
            ->where('m.is_rentable', true)
            ->select('m.id', 'm.fournisseur_id', 'm.tarif_nuit', 'm.quantite_dispo', 'mc.nom as cat_nom')
            ->get();

        $materielleRows = [];
        $this->buildMaterielleReservations($campeurs, $materielles, $now, $materielleRows);

        foreach (array_chunk($materielleRows, 50) as $chunk) {
            DB::table('reservations_materielles')->insert($chunk);
        }
        $this->command?->info('✅ ' . count($materielleRows) . ' reservations_materielles inserted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Archetype helpers
    // ─────────────────────────────────────────────────────────────────────────
    private function archetypeCentres(): array
    {
        return [
            'A' => ['ecocamp.aindraham@gmail.com', 'camp.nature.beja@gmail.com', 'camp.tabarka.foret@gmail.com', 'centre.trekking.zaghouan@gmail.com'],
            'B' => ['centre.capbon@gmail.com', 'camping.plage.hammamet@gmail.com', 'camp.sousse.plage@gmail.com', 'centre.camping.kairouan@gmail.com'],
            'C' => ['camping.hammam.bourguiba@gmail.com', 'centre.ichkeul@gmail.com', 'camping.lac.ichkeul@gmail.com', 'camp.nature.beja@gmail.com', 'camp.tabarka.foret@gmail.com'],
            'D' => ['camp.desert.douz@gmail.com', 'camping.oasis.nefta@gmail.com', 'centre.ksar.ghilane@gmail.com'],
            'E' => ['centre.ksar.ghilane@gmail.com', 'camp.sousse.plage@gmail.com', 'camping.plage.hammamet@gmail.com', 'centre.capbon@gmail.com'],
            'F' => ['centre.capbon@gmail.com', 'camp.sfax.nature@gmail.com', 'camping.hammam.bourguiba@gmail.com', 'centre.camping.kairouan@gmail.com'],
        ];
    }

    private function archetypeSkillLevel(string $arch): string
    {
        return match ($arch) {
            'A', 'D' => ['advanced', 'mixed'][rand(0, 1)],
            'B', 'E' => 'beginner',
            'C'      => ['intermediate', 'mixed'][rand(0, 1)],
            'F'      => ['beginner', 'intermediate'][rand(0, 1)],
            default  => 'mixed',
        };
    }

    private function archetypeTripPurpose(string $arch): string
    {
        return match ($arch) {
            'A' => ['Randonnée solo', 'Trek aventure', 'Bivouac nature'][rand(0, 2)],
            'B' => ['Vacances famille', 'Weekend familial', 'Camping plage famille'][rand(0, 2)],
            'C' => ['Weekend exploration', 'Randonnée nature', 'Découverte région'][rand(0, 2)],
            'D' => ['Expédition désert', 'Bivouac saharien', 'Trek désertique'][rand(0, 2)],
            'E' => ['Séjour romantique', 'Weekend glamping', 'Détente luxe nature'][rand(0, 2)],
            'F' => ['Sortie étudiante', 'Trip amis', 'Camping budget groupe'][rand(0, 2)],
            default => 'Camping loisirs',
        };
    }

    private function archetypeNightsAndPrice(string $arch): array
    {
        return match ($arch) {
            'A'     => [rand(2, 4),  rand(25, 45)],
            'B'     => [rand(3, 5),  rand(40, 60)],
            'C'     => [rand(2, 3),  rand(30, 55)],
            'D'     => [rand(3, 6),  rand(35, 55)],
            'E'     => [rand(3, 5),  rand(70, 100)],
            'F'     => [rand(1, 2),  rand(20, 35)],
            default => [2,           40],
        };
    }

    private function archetypeMatCategories(): array
    {
        return [
            'A' => ['Tentes', 'Sacs de couchage', 'Transport & stockage', 'Éclairage'],
            'B' => ['Tentes', 'Cuisine outdoor', 'Éclairage', 'Transport & stockage'],
            'C' => ['Tentes', 'Sacs de couchage', 'Éclairage', 'Transport & stockage'],
            'D' => ['Navigation', 'Sécurité', 'Tentes', 'Transport & stockage'],
            'E' => ['Tentes', 'Cuisine outdoor', 'Éclairage', 'Transport & stockage'],
            'F' => ['Sacs de couchage', 'Tentes', 'Éclairage'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build reservations_centres
    // ─────────────────────────────────────────────────────────────────────────
    private function buildCentreReservations(
        \Illuminate\Support\Collection $campeurs,
        array $centreUsers,
        string $now,
        array &$rows
    ): void {
        $archetypeCentres = $this->archetypeCentres();

        // Take a representative subset — skip F mostly (they book fewer centres)
        $targetCount = 50;
        $added       = 0;

        // Past date options (last 18 months)
        $pastStarts = [
            '2024-11-15', '2024-12-20', '2025-01-10', '2025-02-08',
            '2025-03-15', '2025-04-12', '2025-05-03', '2025-06-20',
            '2025-07-05', '2025-08-10', '2025-09-06', '2025-10-18',
            '2025-11-22', '2025-12-07', '2026-01-11', '2026-02-14',
            '2026-03-08', '2026-04-05',
        ];
        $futureStarts = ['2026-06-10', '2026-06-25', '2026-07-15', '2026-08-02'];

        $statusPool = [
            'approved', 'approved', 'approved', 'approved', 'approved', 'approved',
            'pending', 'pending',
            'canceled', 'rejected',
        ];

        foreach ($campeurs as $i => $c) {
            if ($added >= $targetCount) break;

            $arch    = $c->archetype;
            $centres = $archetypeCentres[$arch] ?? $archetypeCentres['C'];

            // Each campeur gets 1 or 2 reservations
            $numRes = ($i % 3 === 0) ? 2 : 1;

            for ($r = 0; $r < $numRes && $added < $targetCount; $r++) {
                $centreEmail = $centres[($c->id + $r) % count($centres)];
                $centreId    = $centreUsers[$centreEmail] ?? null;
                if (! $centreId) continue;

                // Determine if past or future
                $isFuture = ($added % 7 === 0); // ~14% future
                if ($isFuture) {
                    $startDate = $futureStarts[$added % count($futureStarts)];
                    $status    = ($added % 3 === 0) ? 'pending' : 'approved';
                } else {
                    $startDate = $pastStarts[($c->id + $r + $i) % count($pastStarts)];
                    $status    = $statusPool[($c->id + $r) % count($statusPool)];
                }

                [$nights, $pricePerNight] = $this->archetypeNightsAndPrice($arch);
                $endDate    = date('Y-m-d', strtotime($startDate . " +{$nights} days"));
                $totalPrice = $nights * $pricePerNight;

                $rows[] = [
                    'user_id'          => $c->id,
                    'centre_id'        => $centreId,
                    'date_debut'       => $startDate,
                    'date_fin'         => $endDate,
                    'nbr_place'        => rand(2, 6),
                    'nights'           => $nights,
                    'note'             => null,
                    'group_skill_level'=> $this->archetypeSkillLevel($arch),
                    'trip_purpose'     => $this->archetypeTripPurpose($arch),
                    'type'             => null,
                    'status'           => $status,
                    'payments_id'      => null,
                    'total_price'      => $totalPrice,
                    'payment_method'   => ($arch === 'E') ? 'card' : (($i % 2 === 0) ? 'card' : 'wallet'),
                    'service_count'    => 0,
                    'discount_amount'  => 0.00,
                    'platform_fee_rate'=> null,
                    'platform_fee_amount' => null,
                    'canceled_by'      => $status === 'canceled' ? 'user' : null,
                    'canceled_at'      => $status === 'canceled' ? $now : null,
                    'cancellation_reason' => null,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
                $added++;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build reservations_events
    // ─────────────────────────────────────────────────────────────────────────
    private function buildEventReservations(
        \Illuminate\Support\Collection $campeurs,
        \Illuminate\Support\Collection $events,
        string $now,
        array &$rows
    ): void {
        $finishedEvents   = $events->where('status', 'finished')->values();
        $scheduledEvents  = $events->where('status', 'scheduled')->values();

        if ($finishedEvents->isEmpty() && $scheduledEvents->isEmpty()) return;

        // Archetype → allowed difficulties
        $archDiff = [
            'A' => ['easy', 'moderate', 'difficult'],
            'B' => ['easy'],
            'C' => ['easy', 'moderate'],
            'D' => ['moderate', 'difficult', 'expert'],
            'E' => ['easy'],
            'F' => ['easy', 'moderate'],
        ];

        $targetCount = 65;
        $added       = 0;

        foreach ($campeurs as $i => $c) {
            if ($added >= $targetCount) break;

            $arch     = $c->archetype;
            $allowed  = $archDiff[$arch] ?? ['easy', 'moderate'];

            // Try past events first
            $compatible = $finishedEvents->filter(fn($e) =>
                in_array($e->difficulty, $allowed) || $e->difficulty === null
            );

            if ($compatible->isEmpty()) {
                $compatible = $finishedEvents;
            }

            if ($compatible->isEmpty()) continue;

            $event  = $compatible->values()->get($c->id % $compatible->count());
            if (! $event) continue;

            $status = 'confirmée';
            $rows[] = $this->makeEventRow($c, $event, $status, $now);
            $added++;

            // Some campeurs also have a future reservation
            if ($added < $targetCount && $i % 4 === 0 && $scheduledEvents->isNotEmpty()) {
                $futureCompatible = $scheduledEvents->filter(fn($e) =>
                    in_array($e->difficulty, $allowed) || $e->difficulty === null
                );
                if ($futureCompatible->isNotEmpty()) {
                    $futureEvent = $futureCompatible->values()->get($c->id % $futureCompatible->count());
                    if ($futureEvent && $futureEvent->id !== $event->id) {
                        $rows[] = $this->makeEventRow($c, $futureEvent, 'en_attente_validation', $now);
                        $added++;
                    }
                }
            }
        }
    }

    private function makeEventRow(object $campeur, object $event, string $status, string $now): array
    {
        $arch = $campeur->archetype;
        return [
            'user_id'          => $campeur->id,
            'event_id'         => $event->id,
            'group_id'         => $event->group_id,
            'name'             => $campeur->first_name . ' ' . $campeur->last_name,
            'email'            => $campeur->email,
            'phone'            => null,
            'group_skill_level'=> $this->archetypeSkillLevel($arch),
            'trip_purpose'     => $this->archetypeTripPurpose($arch),
            'nbr_place'        => rand(1, 3),
            'payment_id'       => null,
            'status'           => $status,
            'created_by'       => null,
            'promo_code_id'    => null,
            'discount_amount'  => 0.00,
            'payment_method'   => 'wallet',
            'platform_fee_amount' => null,
            'platform_fee_rate'   => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build reservations_materielles
    // ─────────────────────────────────────────────────────────────────────────
    private function buildMaterielleReservations(
        \Illuminate\Support\Collection $campeurs,
        \Illuminate\Support\Collection $materielles,
        string $now,
        array &$rows
    ): void {
        if ($materielles->isEmpty()) return;

        $catMap        = $this->archetypeMatCategories();
        $targetCount   = 60;
        $added         = 0;

        $pastDatePairs = [
            ['2025-01-05', '2025-01-08'], ['2025-02-14', '2025-02-17'],
            ['2025-03-20', '2025-03-23'], ['2025-04-10', '2025-04-13'],
            ['2025-05-01', '2025-05-04'], ['2025-06-15', '2025-06-18'],
            ['2025-07-20', '2025-07-25'], ['2025-08-05', '2025-08-09'],
            ['2025-09-12', '2025-09-15'], ['2025-10-03', '2025-10-07'],
            ['2025-11-08', '2025-11-11'], ['2025-12-19', '2025-12-22'],
            ['2026-01-10', '2026-01-13'], ['2026-02-21', '2026-02-24'],
            ['2026-03-14', '2026-03-17'], ['2026-04-04', '2026-04-07'],
        ];
        $futureDatePairs = [
            ['2026-06-01', '2026-06-04'], ['2026-06-20', '2026-06-24'],
            ['2026-07-10', '2026-07-15'], ['2026-08-01', '2026-08-05'],
        ];

        $livraisons = ['pickup', 'pickup', 'pickup', 'delivery', 'delivery'];

        foreach ($campeurs as $i => $c) {
            if ($added >= $targetCount) break;

            $arch        = $c->archetype;
            $preferred   = $catMap[$arch] ?? ['Tentes', 'Éclairage'];

            // Find matching materials
            $matching = $materielles->filter(fn($m) =>
                in_array($m->cat_nom, $preferred)
            );
            if ($matching->isEmpty()) {
                $matching = $materielles;
            }

            $mat = $matching->values()->get($c->id % $matching->count());
            if (! $mat) continue;

            $isFuture = ($i % 5 === 0);
            $datePair = $isFuture
                ? $futureDatePairs[$i % count($futureDatePairs)]
                : $pastDatePairs[($c->id + $i) % count($pastDatePairs)];

            $status       = $isFuture ? 'confirmed' : 'returned';
            $livraison    = $livraisons[$c->id % count($livraisons)];
            $quantite     = 1;
            $nights       = (strtotime($datePair[1]) - strtotime($datePair[0])) / 86400;
            $montant      = round(($mat->tarif_nuit ?? 10) * $nights, 2);

            $rows[] = [
                'materielle_id'       => $mat->id,
                'user_id'             => $c->id,
                'fournisseur_id'      => $mat->fournisseur_id,
                'type_reservation'    => 'location',
                'date_debut'          => $datePair[0],
                'date_fin'            => $datePair[1],
                'quantite'            => $quantite,
                'montant_total'       => $montant,
                'mode_livraison'      => $livraison,
                'adresse_livraison'   => $livraison === 'delivery' ? 'Adresse de livraison, Tunisie' : null,
                'frais_livraison'     => $livraison === 'delivery' ? 7.00 : 0,
                'cin_camper'          => null,
                'status'              => $status,
                'pin_code'            => null,
                'pin_used_at'         => null,
                'payment_id'          => null,
                'confirmed_at'        => $status !== 'pending' ? $now : null,
                'retrieved_at'        => $status === 'returned' ? $now : null,
                'returned_at'         => $status === 'returned' ? $datePair[1] . ' 18:00:00' : null,
                'discount_amount'     => 0.00,
                'payment_method'      => 'wallet',
                'platform_fee_amount' => null,
                'platform_fee_rate'   => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
            $added++;
        }
    }
}
