<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Fills the remaining tables that have no dedicated seeder so the database
 * is fully populated for testing:
 *
 * promo_codes, popups, user_popup_states, platform_cancellation_fees,
 * custom_commission_rules(+users), badges, albums, photos, interested_events,
 * followers_groupes, groupe_co_owners, organizer_supplier_links,
 * supplier_invitations, event_services, event_reservation_services,
 * event_reservation_materials, reservation_guides, centre_claims,
 * zone_polygons, wallet_transactions, admin_wallet_transactions,
 * email_verifications, message_attachments.
 */
class RichDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $this->seedPromoCodes($now);
        $this->seedPopups($now);
        $this->seedPlatformCancellationFees($now);
        $this->seedCustomCommissionRules($now);
        $this->seedBadges($now);
        $this->seedAlbumsAndPhotos($now);
        $this->seedInterestedEvents($now);
        $this->seedGroupSocial($now);
        $this->seedOrganizerSupplierLinks($now);
        $this->seedSupplierInvitations($now);
        $this->seedEventServices($now);
        $this->seedEventReservationMaterials($now);
        $this->seedReservationGuides($now);
        $this->seedCentreClaims($now);
        $this->seedZonePolygons($now);
        $this->seedWalletTransactions($now);
        $this->seedEmailVerifications($now);
        $this->seedMessageAttachments($now);

        $this->command->info('✅ RichDataSeeder completed.');
    }

    private function seedPromoCodes(Carbon $now): void
    {
        DB::table('promo_codes')->insert([
            ['code' => 'CAMP20',    'discount_type' => 'percentage', 'discount_value' => 20.00, 'applicable_to' => 'all',      'min_price' => 50.00,  'max_uses' => 100,  'used_count' => 12, 'is_active' => 1, 'expires_at' => $now->copy()->addMonths(2), 'created_at' => $now->copy()->subMonth(), 'updated_at' => $now],
            ['code' => 'SUMMER10',  'discount_type' => 'percentage', 'discount_value' => 10.00, 'applicable_to' => 'event',    'min_price' => null,   'max_uses' => null, 'used_count' => 45, 'is_active' => 1, 'expires_at' => $now->copy()->addMonths(3), 'created_at' => $now->copy()->subWeeks(2), 'updated_at' => $now],
            ['code' => 'CENTRE15',  'discount_type' => 'fixed',      'discount_value' => 15.00, 'applicable_to' => 'centre',   'min_price' => 100.00, 'max_uses' => 50,   'used_count' => 8,  'is_active' => 1, 'expires_at' => $now->copy()->addMonth(),   'created_at' => $now->copy()->subWeeks(3), 'updated_at' => $now],
            ['code' => 'GEARDEAL',  'discount_type' => 'fixed',      'discount_value' => 5.00,  'applicable_to' => 'materiel', 'min_price' => 30.00,  'max_uses' => 200,  'used_count' => 30, 'is_active' => 1, 'expires_at' => $now->copy()->addWeeks(6),  'created_at' => $now->copy()->subMonth(),  'updated_at' => $now],
            ['code' => 'EXPIRED5',  'discount_type' => 'percentage', 'discount_value' => 5.00,  'applicable_to' => 'all',      'min_price' => null,   'max_uses' => 10,   'used_count' => 10, 'is_active' => 0, 'expires_at' => $now->copy()->subDays(10),  'created_at' => $now->copy()->subMonths(3), 'updated_at' => $now],
        ]);
        $this->command->info('  promo_codes: 5');
    }

    private function seedPopups(Carbon $now): void
    {
        DB::table('popups')->insert([
            ['title' => 'Bienvenue sur TunisiaCamp !', 'content' => 'Découvrez les plus beaux spots de camping en Tunisie. Complétez votre profil pour commencer.', 'type' => 'info',      'popup_kind' => 'welcome',    'target_roles' => json_encode(['campeur']), 'icon' => '🏕️', 'cta_label' => 'Compléter mon profil', 'cta_url' => '/profile', 'is_active' => 1, 'created_at' => $now->copy()->subMonth(), 'updated_at' => $now],
            ['title' => 'Promo été : -20%',            'content' => 'Utilisez le code CAMP20 pour bénéficier de 20% de réduction sur votre prochaine réservation.', 'type' => 'promotion', 'popup_kind' => 'engagement', 'target_roles' => null,                      'icon' => '🎉', 'cta_label' => 'Réserver',             'cta_url' => '/events',  'is_active' => 1, 'created_at' => $now->copy()->subWeeks(2), 'updated_at' => $now],
            ['title' => 'Maintenance planifiée',       'content' => 'La plateforme sera indisponible dimanche de 02h à 04h pour maintenance.', 'type' => 'warning',  'popup_kind' => 'engagement', 'target_roles' => null,                      'icon' => '⚠️', 'cta_label' => null,                   'cta_url' => null,       'is_active' => 0, 'created_at' => $now->copy()->subDays(5), 'updated_at' => $now],
        ]);

        $popupIds = DB::table('popups')->pluck('id')->toArray();
        $userIds  = DB::table('users')->pluck('id')->toArray();
        $states   = [];
        foreach (array_slice($userIds, 0, 6) as $uid) {
            $states[] = [
                'user_id'      => $uid,
                'popup_id'     => $popupIds[array_rand($popupIds)],
                'is_dismissed' => rand(0, 1),
                'created_at'   => $now->copy()->subDays(rand(1, 20)),
                'updated_at'   => $now,
            ];
        }
        // dedupe on unique (user_id, popup_id)
        $states = collect($states)->unique(fn ($s) => $s['user_id'] . '-' . $s['popup_id'])->values()->all();
        DB::table('user_popup_states')->insert($states);
        $this->command->info('  popups: 3, user_popup_states: ' . count($states));
    }

    private function seedPlatformCancellationFees(Carbon $now): void
    {
        DB::table('platform_cancellation_fees')->insert([
            ['actor_type' => 'camper',   'fee_percentage' => 5.00,  'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['actor_type' => 'centre',   'fee_percentage' => 10.00, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['actor_type' => 'group',    'fee_percentage' => 8.00,  'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['actor_type' => 'supplier', 'fee_percentage' => 7.50,  'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->command->info('  platform_cancellation_fees: 4');
    }

    private function seedCustomCommissionRules(Carbon $now): void
    {
        $ruleId = DB::table('custom_commission_rules')->insertGetId([
            'name'            => 'Partenaires premium',
            'description'     => 'Taux réduit pour les organisateurs partenaires de longue date.',
            'commission_rate' => 3.50,
            'is_active'       => 1,
            'created_at'      => $now->copy()->subMonth(),
            'updated_at'      => $now,
        ]);
        $ruleId2 = DB::table('custom_commission_rules')->insertGetId([
            'name'            => 'Nouveaux fournisseurs',
            'description'     => 'Commission allégée pendant les 3 premiers mois.',
            'commission_rate' => 2.00,
            'is_active'       => 1,
            'created_at'      => $now->copy()->subWeeks(2),
            'updated_at'      => $now,
        ]);

        DB::table('custom_commission_rule_users')->insert([
            ['rule_id' => $ruleId,  'user_id' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['rule_id' => $ruleId,  'user_id' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['rule_id' => $ruleId2, 'user_id' => 7, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->command->info('  custom_commission_rules: 2 (+3 users)');
    }

    private function seedBadges(Carbon $now): void
    {
        // guide_id = 8 (Ahmed Guide); providers = group organisers / centre
        DB::table('badges')->insert([
            ['guide_id' => 8, 'provider_id' => 4, 'creation_date' => $now->copy()->subMonths(2)->toDateString(), 'titre' => 'Guide certifié montagne', 'decription' => 'Certification randonnée montagne délivrée après 10 sorties encadrées.', 'type' => 'certification', 'icon' => 'mountain', 'created_at' => $now, 'updated_at' => $now],
            ['guide_id' => 8, 'provider_id' => 5, 'creation_date' => $now->copy()->subMonth()->toDateString(),  'titre' => 'Guide fiable',            'decription' => 'Réputation excellente sur 15 événements organisés ensemble.',        'type' => 'reputation',    'icon' => 'star',     'created_at' => $now, 'updated_at' => $now],
            ['guide_id' => 8, 'provider_id' => 6, 'creation_date' => $now->copy()->subWeeks(2)->toDateString(), 'titre' => 'Expert désert',           'decription' => 'Certification bivouac saharien et orientation en milieu désertique.', 'type' => 'certification', 'icon' => 'sun',      'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->command->info('  badges: 3');
    }

    private function seedAlbumsAndPhotos(Carbon $now): void
    {
        $albums = [
            ['user_id' => 3, 'titre' => 'Mes aventures camping',   'description' => 'Souvenirs de mes sorties camping en Tunisie.', 'path_to_img' => 'https://picsum.photos/id/110/400/300'],
            ['user_id' => 9, 'titre' => 'Randonnées 2026',         'description' => 'Photos de randonnées au nord-ouest.',          'path_to_img' => 'https://picsum.photos/id/120/400/300'],
            ['user_id' => 4, 'titre' => 'Événements du groupe',    'description' => 'Galerie officielle des événements organisés.', 'path_to_img' => 'https://picsum.photos/id/130/400/300'],
        ];
        foreach ($albums as &$a) {
            $a['created_at'] = $now->copy()->subDays(rand(5, 60));
            $a['updated_at'] = $now;
        }
        unset($a);
        DB::table('albums')->insert($albums);

        $albumIds  = DB::table('albums')->pluck('id')->toArray();
        $zoneIds   = DB::table('camping_zones')->limit(8)->pluck('id')->toArray();
        $centreIds = DB::table('camping_centres')->limit(6)->pluck('id')->toArray();
        $matIds    = DB::table('materielles')->pluck('id')->toArray();
        $eventIds  = DB::table('events')->pluck('id')->toArray();

        $photos = [];
        $img = 200;

        foreach ($zoneIds as $zid) {
            $photos[] = ['path_to_img' => 'https://picsum.photos/id/' . $img++ . '/800/600', 'camping_zone_id' => $zid, 'is_cover' => 1, 'order' => 0, 'created_at' => $now, 'updated_at' => $now];
            $photos[] = ['path_to_img' => 'https://picsum.photos/id/' . $img++ . '/800/600', 'camping_zone_id' => $zid, 'is_cover' => 0, 'order' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($centreIds as $cid) {
            $photos[] = ['path_to_img' => 'https://picsum.photos/id/' . $img++ . '/800/600', 'camping_centre_id' => $cid, 'is_cover' => 1, 'order' => 0, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($matIds as $mid) {
            $photos[] = ['path_to_img' => 'https://picsum.photos/id/' . $img++ . '/800/600', 'materielle_id' => $mid, 'is_cover' => 1, 'order' => 0, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($eventIds as $eid) {
            $photos[] = ['path_to_img' => 'https://picsum.photos/id/' . $img++ . '/800/600', 'event_id' => $eid, 'is_cover' => 1, 'order' => 0, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($albumIds as $aid) {
            $photos[] = ['path_to_img' => 'https://picsum.photos/id/' . $img++ . '/800/600', 'album_id' => $aid, 'user_id' => 3, 'is_cover' => 0, 'order' => 0, 'created_at' => $now, 'updated_at' => $now];
        }

        // Normalize keys so every row has the same column set for bulk insert
        $columns = ['path_to_img', 'user_id', 'annonce_id', 'camping_zone_id', 'camping_centre_id', 'materielle_id', 'event_id', 'album_id', 'is_cover', 'order', 'created_at', 'updated_at'];
        $photos = array_map(function ($p) use ($columns) {
            return array_merge(array_fill_keys($columns, null), $p);
        }, $photos);

        DB::table('photos')->insert($photos);
        $this->command->info('  albums: 3, photos: ' . count($photos));
    }

    private function seedInterestedEvents(Carbon $now): void
    {
        $eventIds = DB::table('events')->pluck('id')->toArray();
        $userIds  = [2, 3, 7, 8, 9, 10];
        $rows = [];
        foreach ($userIds as $uid) {
            foreach ((array) array_rand(array_flip($eventIds), min(2, count($eventIds))) as $eid) {
                $rows[] = ['user_id' => $uid, 'event_id' => $eid, 'created_at' => $now->copy()->subDays(rand(1, 20)), 'updated_at' => $now];
            }
        }
        $rows = collect($rows)->unique(fn ($r) => $r['user_id'] . '-' . $r['event_id'])->values()->all();
        DB::table('interested_events')->insert($rows);
        $this->command->info('  interested_events: ' . count($rows));
    }

    private function seedGroupSocial(Carbon $now): void
    {
        // followers_groupes.groupe_id -> profile_groupes.id
        $groupProfileIds = DB::table('profile_groupes')->pluck('id')->toArray();
        if (empty($groupProfileIds)) {
            return;
        }

        $followers = [];
        foreach ([3, 7, 8, 9, 10] as $uid) {
            foreach ($groupProfileIds as $gid) {
                if (rand(0, 1)) {
                    $followers[] = ['user_id' => $uid, 'groupe_id' => $gid, 'created_at' => $now->copy()->subDays(rand(1, 45)), 'updated_at' => $now];
                }
            }
        }
        DB::table('followers_groupes')->insert($followers);

        // co-owners: campers helping manage the groups
        $coOwners = [
            ['profile_groupe_id' => $groupProfileIds[0], 'user_id' => 3, 'created_at' => $now, 'updated_at' => $now],
        ];
        if (isset($groupProfileIds[1])) {
            $coOwners[] = ['profile_groupe_id' => $groupProfileIds[1], 'user_id' => 9, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('groupe_co_owners')->insert($coOwners);

        $this->command->info('  followers_groupes: ' . count($followers) . ', groupe_co_owners: ' . count($coOwners));
    }

    private function seedOrganizerSupplierLinks(Carbon $now): void
    {
        DB::table('organizer_supplier_links')->insert([
            ['organizer_id' => 4, 'supplier_id' => 7, 'status' => 'accepted', 'message' => 'Partenariat pour la fourniture de tentes sur nos événements.', 'responded_at' => $now->copy()->subDays(10), 'created_at' => $now->copy()->subDays(12), 'updated_at' => $now->copy()->subDays(10)],
            ['organizer_id' => 5, 'supplier_id' => 7, 'status' => 'pending',  'message' => 'Nous cherchons un fournisseur de matériel de randonnée.',     'responded_at' => null,                       'created_at' => $now->copy()->subDays(3),  'updated_at' => $now->copy()->subDays(3)],
            ['organizer_id' => 6, 'supplier_id' => 7, 'status' => 'rejected', 'message' => 'Demande de partenariat équipement nautique.',                 'responded_at' => $now->copy()->subDays(5),  'created_at' => $now->copy()->subDays(8),  'updated_at' => $now->copy()->subDays(5)],
        ]);
        $this->command->info('  organizer_supplier_links: 3');
    }

    private function seedSupplierInvitations(Carbon $now): void
    {
        DB::table('supplier_invitations')->insert([
            ['organizer_id' => 4, 'email' => 'newsupplier1@example.com', 'status' => 'pending',    'token' => Str::random(64), 'expires_at' => $now->copy()->addDays(7),  'registered_at' => null,                      'supplier_id' => null, 'created_at' => $now->copy()->subDays(2),  'updated_at' => $now],
            ['organizer_id' => 4, 'email' => 'equipment@example.com',    'status' => 'registered', 'token' => Str::random(64), 'expires_at' => $now->copy()->subDays(10), 'registered_at' => $now->copy()->subDays(15), 'supplier_id' => 7,    'created_at' => $now->copy()->subDays(20), 'updated_at' => $now],
            ['organizer_id' => 5, 'email' => 'oldinvite@example.com',    'status' => 'expired',    'token' => Str::random(64), 'expires_at' => $now->copy()->subDays(5),  'registered_at' => null,                      'supplier_id' => null, 'created_at' => $now->copy()->subDays(15), 'updated_at' => $now],
        ]);
        $this->command->info('  supplier_invitations: 3');
    }

    private function seedEventServices(Carbon $now): void
    {
        $eventIds = DB::table('events')->pluck('id')->toArray();
        $catalog = [
            ['name' => 'Transport aller-retour', 'description' => 'Transport en bus climatisé depuis la ville de départ.', 'price' => 35.00, 'pricing_unit' => 'person',       'max_quantity' => 4],
            ['name' => 'Location tente',         'description' => 'Tente 2-3 places montée à votre arrivée.',              'price' => 20.00, 'pricing_unit' => 'unit/night',   'max_quantity' => 2],
            ['name' => 'Repas complet',          'description' => 'Trois repas par jour préparés sur place.',              'price' => 25.00, 'pricing_unit' => 'person/day',   'max_quantity' => null],
        ];

        $services = [];
        foreach ($eventIds as $eid) {
            foreach ($catalog as $svc) {
                $services[] = array_merge($svc, [
                    'event_id'   => $eid,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        DB::table('event_services')->insert($services);

        // Attach services to existing event reservations
        $reservations = DB::table('reservations_events')->get(['id', 'event_id', 'nbr_place']);
        $rows = [];
        foreach ($reservations as $res) {
            $eventServices = DB::table('event_services')->where('event_id', $res->event_id)->get();
            if ($eventServices->isEmpty() || rand(0, 2) === 0) {
                continue; // some reservations have no extra services
            }
            $svc = $eventServices->random();
            $qty = max(1, min((int) $res->nbr_place, $svc->max_quantity ?? 99));
            $rows[] = [
                'event_reservation_id'  => $res->id,
                'event_service_id'      => $svc->id,
                'quantity'              => $qty,
                'notes'                 => null,
                'price_snapshot'        => $svc->price,
                'pricing_unit_snapshot' => $svc->pricing_unit,
                'subtotal'              => round($svc->price * $qty, 2),
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }
        if ($rows) {
            DB::table('event_reservation_services')->insert($rows);
        }
        $this->command->info('  event_services: ' . count($services) . ', event_reservation_services: ' . count($rows));
    }

    private function seedEventReservationMaterials(Carbon $now): void
    {
        $reservationIds = DB::table('reservations_events')->limit(5)->pluck('id')->toArray();
        $materials = DB::table('materielles')->where('is_rentable', 1)->limit(3)->get(['id', 'tarif_nuit']);
        if (empty($reservationIds) || $materials->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($reservationIds as $i => $rid) {
            $mat = $materials[$i % count($materials)];
            $qty = rand(1, 3);
            $unit = $mat->tarif_nuit ?? 10.00;
            $total = round($unit * $qty, 2);
            $feeRate = 5.00;
            $fee = round($total * $feeRate / 100, 2);
            $rows[] = [
                'event_reservation_id' => $rid,
                'materielle_id'        => $mat->id,
                'supplier_id'          => 7,
                'quantite'             => $qty,
                'prix_unitaire'        => $unit,
                'montant_total'        => $total,
                'platform_fee_amount'  => $fee,
                'platform_fee_rate'    => $feeRate,
                'supplier_net_revenue' => round($total - $fee, 2),
                'supplier_credited'    => rand(0, 1),
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }
        DB::table('event_reservation_materials')->insert($rows);
        $this->command->info('  event_reservation_materials: ' . count($rows));
    }

    private function seedReservationGuides(Carbon $now): void
    {
        $circuitIds = DB::table('circuits')->pluck('id')->toArray();
        $rows = [
            ['reserver_id' => 3,  'guide_id' => 8, 'creation_date' => $now->copy()->subDays(20)->toDateString(), 'type' => 'randonnée', 'discription' => 'Randonnée guidée au Jebel Zaghouan pour un groupe de 4.',     'status' => 'approved'],
            ['reserver_id' => 9,  'guide_id' => 8, 'creation_date' => $now->copy()->subDays(10)->toDateString(), 'type' => 'circuit',   'discription' => 'Accompagnement sur le circuit des oasis de montagne.',        'status' => 'pending'],
            ['reserver_id' => 10, 'guide_id' => 8, 'creation_date' => $now->copy()->subDays(5)->toDateString(),  'type' => 'bivouac',   'discription' => 'Encadrement bivouac saharien de 2 nuits près de Douz.',       'status' => 'rejected'],
            ['reserver_id' => 4,  'guide_id' => 8, 'creation_date' => $now->copy()->addDays(7)->toDateString(),  'type' => 'événement', 'discription' => 'Guide pour l\'événement Camping Saharien du groupe Nejikh.', 'status' => 'approved'],
        ];
        foreach ($rows as $i => &$r) {
            $r['circuit_id'] = $circuitIds ? $circuitIds[$i % count($circuitIds)] : null;
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
        }
        unset($r);
        DB::table('reservation_guides')->insert($rows);
        $this->command->info('  reservation_guides: ' . count($rows));
    }

    private function seedCentreClaims(Carbon $now): void
    {
        $centreIds = DB::table('camping_centres')->whereNull('user_id')->limit(3)->pluck('id')->toArray();
        if (empty($centreIds)) {
            return;
        }

        $statuses = ['pending', 'approved', 'rejected'];
        $rows = [];
        foreach ($centreIds as $i => $cid) {
            $status = $statuses[$i % 3];
            $rows[] = [
                'centre_id'      => $cid,
                'user_id'        => 2,
                'status'         => $status,
                'message'        => 'Je suis le gérant officiel de ce centre et souhaite récupérer la gestion de sa fiche.',
                'proof_document' => 'uploads/claims/proof_' . $cid . '.pdf',
                'admin_note'     => $status === 'rejected' ? 'Document de preuve illisible.' : ($status === 'approved' ? 'Vérifié par téléphone.' : null),
                'reviewer_id'    => $status === 'pending' ? null : 1,
                'reviewed_at'    => $status === 'pending' ? null : $now->copy()->subDays(rand(1, 5)),
                'created_at'     => $now->copy()->subDays(rand(6, 15)),
                'updated_at'     => $now,
            ];
        }
        DB::table('centre_claims')->insert($rows);
        $this->command->info('  centre_claims: ' . count($rows));
    }

    private function seedZonePolygons(Carbon $now): void
    {
        $zones = DB::table('camping_zones')->whereNotNull('lat')->limit(5)->get(['id', 'lat', 'lng']);
        $rows = [];
        foreach ($zones as $z) {
            $lat = (float) $z->lat;
            $lng = (float) $z->lng;
            $rows[] = [
                'zone_id'     => $z->id,
                'coordinates' => json_encode([
                    [$lat + 0.01, $lng - 0.01],
                    [$lat + 0.01, $lng + 0.01],
                    [$lat - 0.01, $lng + 0.01],
                    [$lat - 0.01, $lng - 0.01],
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows) {
            DB::table('zone_polygons')->insert($rows);
        }
        $this->command->info('  zone_polygons: ' . count($rows));
    }

    private function seedWalletTransactions(Carbon $now): void
    {
        $rows = [
            // camper 3 pays an event reservation from wallet
            ['user_id' => 3, 'related_user_id' => 4,    'type' => 'debit',  'category' => 'reservation_payment', 'amount_gross' => 250.00, 'commission_rate' => 0.00, 'commission_amount' => 0.00,  'net_amount' => 250.00, 'reference_type' => 'reservation_event', 'reference_id' => 1, 'description' => 'Paiement réservation Camping Saharien'],
            // organizer 4 receives the income minus commission
            ['user_id' => 4, 'related_user_id' => 3,    'type' => 'credit', 'category' => 'reservation_income',  'amount_gross' => 250.00, 'commission_rate' => 5.00, 'commission_amount' => 12.50, 'net_amount' => 237.50, 'reference_type' => 'reservation_event', 'reference_id' => 1, 'description' => 'Encaissement réservation Camping Saharien'],
            // supplier income from a material rental
            ['user_id' => 7, 'related_user_id' => 9,    'type' => 'credit', 'category' => 'reservation_income',  'amount_gross' => 77.00,  'commission_rate' => 5.00, 'commission_amount' => 3.85,  'net_amount' => 73.15,  'reference_type' => 'reservation_materielle', 'reference_id' => 2, 'description' => 'Location sacs de couchage'],
            // refund flow
            ['user_id' => 9, 'related_user_id' => 5,    'type' => 'credit', 'category' => 'refund_in',           'amount_gross' => 150.00, 'commission_rate' => 0.00, 'commission_amount' => 0.00,  'net_amount' => 150.00, 'reference_type' => 'refund_request', 'reference_id' => 1, 'description' => 'Remboursement Randonnée Zaghouan'],
            ['user_id' => 5, 'related_user_id' => 9,    'type' => 'debit',  'category' => 'refund_out',          'amount_gross' => 150.00, 'commission_rate' => 0.00, 'commission_amount' => 0.00,  'net_amount' => 150.00, 'reference_type' => 'refund_request', 'reference_id' => 1, 'description' => 'Remboursement émis - Randonnée Zaghouan'],
            // withdrawal + deposit + admin adjustments
            ['user_id' => 4, 'related_user_id' => null, 'type' => 'debit',  'category' => 'withdrawal',          'amount_gross' => 400.00, 'commission_rate' => 0.00, 'commission_amount' => 0.00,  'net_amount' => 400.00, 'reference_type' => 'withdrawal_request', 'reference_id' => 1, 'description' => 'Retrait vers compte bancaire'],
            ['user_id' => 3, 'related_user_id' => null, 'type' => 'credit', 'category' => 'deposit',             'amount_gross' => 100.00, 'commission_rate' => 0.00, 'commission_amount' => 0.00,  'net_amount' => 100.00, 'reference_type' => null, 'reference_id' => null, 'description' => 'Rechargement portefeuille via Konnect'],
            ['user_id' => 10, 'related_user_id' => 1,   'type' => 'credit', 'category' => 'admin_credit',        'amount_gross' => 25.00,  'commission_rate' => 0.00, 'commission_amount' => 0.00,  'net_amount' => 25.00,  'reference_type' => null, 'reference_id' => null, 'description' => 'Geste commercial suite à un incident'],
        ];
        foreach ($rows as &$r) {
            $r['created_at'] = $now->copy()->subDays(rand(1, 30));
            $r['updated_at'] = $now;
        }
        unset($r);
        DB::table('wallet_transactions')->insert($rows);

        DB::table('admin_wallet_transactions')->insert([
            ['category' => 'commission',                'amount' => 12.50, 'reference_type' => 'reservation_event',      'reference_id' => 1,    'related_user_id' => 4,    'description' => 'Commission 5% sur réservation événement #1', 'created_at' => $now->copy()->subDays(20), 'updated_at' => $now],
            ['category' => 'commission',                'amount' => 3.85,  'reference_type' => 'reservation_materielle', 'reference_id' => 2,    'related_user_id' => 7,    'description' => 'Commission 5% sur location matériel #2',      'created_at' => $now->copy()->subDays(15), 'updated_at' => $now],
            ['category' => 'platform_fee',              'amount' => 8.00,  'reference_type' => 'reservation_centre',     'reference_id' => 1,    'related_user_id' => 2,    'description' => 'Frais plateforme réservation centre #1',      'created_at' => $now->copy()->subDays(10), 'updated_at' => $now],
            ['category' => 'platform_cancellation_fee', 'amount' => 7.50,  'reference_type' => 'reservation_event',      'reference_id' => 5,    'related_user_id' => 2,    'description' => 'Frais d\'annulation utilisateur',             'created_at' => $now->copy()->subDays(5),  'updated_at' => $now],
            ['category' => 'refund_funding',            'amount' => 150.00,'reference_type' => 'refund_request',         'reference_id' => 1,    'related_user_id' => 9,    'description' => 'Financement remboursement total',             'created_at' => $now->copy()->subDays(4),  'updated_at' => $now],
        ]);
        $this->command->info('  wallet_transactions: ' . count($rows) . ', admin_wallet_transactions: 5');
    }

    private function seedEmailVerifications(Carbon $now): void
    {
        DB::table('email_verifications')->insert([
            ['user_id' => 3,  'email' => 'deadxshot660@gmail.com', 'code' => '482913', 'token' => null,            'expires_at' => $now->copy()->subDays(30)->addMinutes(15), 'attempts' => 1, 'verified_at' => $now->copy()->subDays(30), 'method' => 'code', 'created_at' => $now->copy()->subDays(30), 'updated_at' => $now->copy()->subDays(30)],
            ['user_id' => 9,  'email' => 'sarah@example.com',      'code' => null,     'token' => Str::random(64), 'expires_at' => $now->copy()->subDays(25)->addHours(24),   'attempts' => 0, 'verified_at' => $now->copy()->subDays(25), 'method' => 'link', 'created_at' => $now->copy()->subDays(25), 'updated_at' => $now->copy()->subDays(25)],
            ['user_id' => 10, 'email' => 'mike@example.com',       'code' => '193847', 'token' => null,            'expires_at' => $now->copy()->addMinutes(10),               'attempts' => 2, 'verified_at' => null,                      'method' => 'code', 'created_at' => $now,                      'updated_at' => $now],
        ]);
        $this->command->info('  email_verifications: 3');
    }

    private function seedMessageAttachments(Carbon $now): void
    {
        $messageIds = DB::table('messages')->limit(3)->pluck('id')->toArray();
        $rows = [];
        foreach ($messageIds as $i => $mid) {
            $rows[] = [
                'message_id' => $mid,
                'file_name'  => 'photo_camp_' . ($i + 1) . '.jpg',
                'file_path'  => 'uploads/messages/photo_camp_' . ($i + 1) . '.jpg',
                'file_type'  => 'image',
                'file_size'  => rand(120000, 2400000),
                'mime_type'  => 'image/jpeg',
                'metadata'   => json_encode(['width' => 1920, 'height' => 1080]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows) {
            DB::table('message_attachments')->insert($rows);
        }
        $this->command->info('  message_attachments: ' . count($rows));
    }
}
