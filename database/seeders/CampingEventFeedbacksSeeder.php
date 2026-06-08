<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CampingEventFeedbacksSeeder
 *
 * Creates realistic post-event reviews (feedbacks) for the finished events
 * inserted by CampingEventsSeeder.
 *
 * Rules:
 *  - Only finished events get reviews (you must have attended to review).
 *  - Not every participant leaves a review (realistic ~60-70 % rate).
 *  - Notes range 1-5; most events score 4-5 stars.
 *  - Some organisers reply to reviews (response field).
 *  - A small fraction of feedbacks is 'pending' moderation; one is 'rejected'.
 *  - type = 'evenement'  (distinguishes from zone / materielle feedbacks).
 *  - target_id = organiser user ID (the group account that ran the event).
 */
class CampingEventFeedbacksSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        // ── Idempotency guard ──────────────────────────────────────────────
        $finishedTitles = array_keys($this->feedbackDefinitions());
        $events = DB::table('events')
            ->whereIn('title', $finishedTitles)
            ->where('status', 'finished')
            ->get()
            ->keyBy('title');

        if ($events->isEmpty()) {
            $this->command?->warn(
                '⚠️  No finished campaign events found — run CampingEventsSeeder first.'
            );
            return;
        }

        $anyEventId = $events->first()->id;
        if (DB::table('feedbacks')->where('event_id', $anyEventId)->where('type', 'evenement')->exists()) {
            $this->command?->warn('⚠️  CampingEventFeedbacksSeeder already run — skipping.');
            return;
        }

        // ── Load campeurs (keyed by email for easy lookup) ─────────────────
        $campeurRoleId = DB::table('roles')->where('name', 'campeur')->value('id');
        $campeursByEmail = DB::table('users')
            ->where('role_id', $campeurRoleId)
            ->pluck('id', 'email')
            ->all();

        $rows = [];

        foreach ($this->feedbackDefinitions() as $title => $feedbacks) {
            $event = $events[$title] ?? null;
            if (! $event) continue;

            foreach ($feedbacks as $fb) {
                $userId = $campeursByEmail[$fb['user_email']] ?? null;
                if (! $userId) continue;

                $rows[] = [
                    'user_id'    => $userId,
                    'target_id'  => $event->group_id,     // the organiser group
                    'event_id'   => $event->id,
                    'zone_id'    => null,
                    'contenu'    => $fb['contenu'],
                    'response'   => $fb['response'] ?? null,
                    'note'       => $fb['note'],
                    'type'       => 'evenement',
                    'status'     => $fb['status'] ?? 'approved',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('feedbacks')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($rows) . ' event feedbacks inserted by CampingEventFeedbacksSeeder.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Feedback definitions
    // Each entry maps an event title to 2-4 individual reviews.
    // ──────────────────────────────────────────────────────────────────────────
    private function feedbackDefinitions(): array
    {
        return [

            // ── 1. Bivouac Étoilé Douz ─────────────────────────────────────
            'Bivouac Étoilé Douz — Nuit Saharienne' => [
                [
                    'user_email' => 'omar.ghannouchi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Une expérience magique ! La nuit sous les étoiles à Douz est '
                        . 'tout simplement inoubliable. Tentes confortables, dîner délicieux et '
                        . 'organisation parfaite. Je recommande à 100 %.',
                    'response'   => 'Merci Omar, c\'était un plaisir de t\'accueillir dans le désert ! '
                        . 'On espère te revoir pour notre prochaine expédition.',
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'leila.ghannouchi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Le bivouac dans les dunes de Douz a dépassé toutes mes attentes. '
                        . 'Le lever de soleil sur l\'erg est un moment que je n\'oublierai jamais. '
                        . 'Guides très professionnels et attentionnés.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'adel.rezgui@gmail.com',
                    'note'       => 4,
                    'contenu'    => 'Très belle expérience dans l\'ensemble. La musique gnawa autour '
                        . 'du feu était superbe. Seul bémol : l\'accès en 4x4 un peu stressant pour '
                        . 'les non-habitués. Mais l\'organisation est top.',
                    'response'   => 'Merci pour le retour Adel ! On prévoit d\'améliorer le briefing '
                        . 'sur la piste d\'accès pour les prochaines éditions.',
                    'status'     => 'approved',
                ],
            ],

            // ── 2. Randonnée Forêt d'Aïn Draham ───────────────────────────
            'Randonnée Forêt d\'Aïn Draham — Chênes-Liège' => [
                [
                    'user_email' => 'haythem.khelifi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'La Kroumirie en décembre, c\'est magnifique ! Les forêts de '
                        . 'chênes-liège étaient couvertes de brume matinale. Sentier bien balisé '
                        . 'et guide très pédagogue sur la flore locale.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'dorsaf.ayari@gmail.com',
                    'note'       => 4,
                    'contenu'    => 'Belle randonnée en forêt, paysages superbes. Le rythme était '
                        . 'un peu rapide pour les débutants mais globalement très bien organisé. '
                        . 'Sources naturelles rafraîchissantes sur le parcours.',
                    'response'   => 'Merci Dorsaf ! On ajustera le rythme lors des prochaines sorties '
                        . 'pour mieux accueillir tous les niveaux.',
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'aziz.ferchichi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Parfait pour un premier trek ! La forêt d\'Aïn Draham est un '
                        . 'trésor naturel. Le groupe était sympa et l\'encadrement sérieux.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
            ],

            // ── 3. Nuit Étoilée à Ksar Ghilane ────────────────────────────
            'Nuit Étoilée à Ksar Ghilane — Oasis & Dunes' => [
                [
                    'user_email' => 'bassim.bourguiba@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Ksar Ghilane est un paradis. Les sources thermales, le dîner '
                        . 'gastronomique et la vue sur les dunes depuis la tente panoramique... '
                        . 'Une expérience de luxe en pleine nature, unique en son genre.',
                    'response'   => 'Merci infiniment Bassim ! Ksar Ghilane nous émerveille à '
                        . 'chaque édition. À très bientôt !',
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'wafa.bourguiba@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Week-end romantique parfait ! Les étoiles au-dessus du Sahara '
                        . 'sont à couper le souffle. Service impeccable, repas excellents et balade '
                        . 'en dromadaire mémorable.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'saber.chtioui@gmail.com',
                    'note'       => 4,
                    'contenu'    => 'Très bon séjour, le cadre est exceptionnel. Prix élevé mais '
                        . 'justifié par la qualité des prestations. Le transfert depuis Douz '
                        . 'pourrait être mieux organisé.',
                    'response'   => 'Merci Saber ! On travaille sur l\'amélioration de la logistique '
                        . 'de transfert pour les prochaines dates.',
                    'status'     => 'approved',
                ],
            ],

            // ── 4. Randonnée Littorale Cap Serrat ──────────────────────────
            'Randonnée Littorale Cap Serrat' => [
                [
                    'user_email' => 'ghassen.ferchichi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Cap Serrat est l\'un des endroits les plus sauvages et beaux '
                        . 'de Tunisie. 14 km le long des falaises avec une vue à 360° sur la '
                        . 'Méditerranée. Le guide connaissait chaque recoin du sentier.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'yosra.fakhfakh@gmail.com',
                    'note'       => 4,
                    'contenu'    => 'Très belle randonnée côtière. Quelques passages un peu exposés '
                        . 'mais avec l\'aide du guide, tout s\'est bien passé. La plage sauvage '
                        . 'à mi-parcours est un vrai bijou.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'amine.slama@gmail.com',
                    'note'       => 3,
                    'contenu'    => 'Beau sentier mais le départ était mal organisé — 30 minutes '
                        . 'de retard. Le paysage reste splendide. À améliorer sur la ponctualité.',
                    'response'   => 'Merci pour ce retour honnête Amine. On s\'excuse pour le retard '
                        . 'au départ et on prendra des mesures pour les prochaines éditions.',
                    'status'     => 'approved',
                ],
            ],

            // ── 5. Trek Jebel Zaghouan ─────────────────────────────────────
            'Trek Jebel Zaghouan — Temple des Eaux' => [
                [
                    'user_email' => 'youssef.khelifi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Ascension fantastique ! Le temple romain au sommet du Zaghouan '
                        . 'est bluffant. La montée est sportive mais accessible avec un bon niveau '
                        . 'de base. Je recommande vivement.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'nidhal.chaari@gmail.com',
                    'note'       => 4,
                    'contenu'    => 'Trek sympa, bon groupe et guide compétent. La vue sur la plaine '
                        . 'de Tunis depuis le sommet est époustouflante. Un peu difficile sur les '
                        . '200 derniers mètres mais ça en vaut la peine.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'sarra.mansouri@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Le Zaghouan mérite vraiment le détour. J\'ai adoré la visite '
                        . 'du temple des Eaux, une fenêtre sur l\'histoire romaine de la Tunisie. '
                        . 'Organisation exemplaire.',
                    'response'   => 'Merci Sara ! Le patrimoine de Zaghouan est un trésor. '
                        . 'À bientôt pour de nouvelles aventures.',
                    'status'     => 'approved',
                ],
            ],

            // ── 6. Trek Oued Zitoun ────────────────────────────────────────
            'Trek Oued Zitoun & Gorges du Kef' => [
                [
                    'user_email' => 'bilel.hamdi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Les gorges de l\'Oued Zitoun sont un secret bien gardé du nord '
                        . 'tunisien. La remontée du lit de la rivière est une aventure en soi. '
                        . 'Petite équipe sympa et guide passionné par son territoire.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'farid.amri@outlook.com',
                    'note'       => 4,
                    'contenu'    => 'Randonnée originale et peu fréquentée. Les passages à gué sont '
                        . 'sympas mais prévoir des chaussures imperméables ! Très bonne ambiance '
                        . 'dans le groupe.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
            ],

            // ── 7. Grand Camping Familial Hammamet ─────────────────────────
            'Grand Camping Familial Hammamet — Spring Edition' => [
                [
                    'user_email' => 'mohamed.trabelsi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Parfait pour les familles ! Mes enfants ont adoré les activités '
                        . 'de plage et le quiz nature. L\'organisation était au top, les espaces '
                        . 'bien délimités. On reviendra l\'année prochaine !',
                    'response'   => 'Merci Mohamed ! C\'est toujours un plaisir d\'accueillir les '
                        . 'familles. On vous attend pour la Summer Edition !',
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'fatma.trabelsi@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Super weekend en famille ! La plage de Hammamet était magnifique, '
                        . 'les enfants ont joué toute la journée. Barbecue géant très convivial, '
                        . 'belle rencontre avec d\'autres familles.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'khaled.jomaa@gmail.com',
                    'note'       => 4,
                    'contenu'    => 'Très bon événement familial. Les sanitaires pourraient être '
                        . 'mieux entretenus mais l\'ambiance et les activités étaient formidables. '
                        . 'Le volley de plage était particulièrement apprécié.',
                    'response'   => 'Merci Khaled ! On prend note pour améliorer les installations '
                        . 'sanitaires lors des prochaines éditions.',
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'anis.majdoub@outlook.com',
                    'note'       => 2,
                    'contenu'    => 'Déçu par le manque d\'ombre sur la zone de camping. Il faisait '
                        . 'très chaud en journée et peu de parasols disponibles. Dommage car '
                        . 'l\'ambiance était sympa.',
                    'response'   => 'Merci pour ce retour Anis. Nous avons entendu votre remarque '
                        . 'et investissons dans plus d\'équipements d\'ombrage pour l\'été.',
                    'status'     => 'approved',
                ],
            ],

            // ── 8. Camping Oasis de Tamerza ────────────────────────────────
            'Camping Oasis de Tamerza — Canyon & Cascade' => [
                [
                    'user_email' => 'bassem.tlili@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Tamerza est un endroit hors du temps. La cascade, le ksour '
                        . 'abandonné et le canyon au coucher du soleil forment un tableau '
                        . 'inoubliable. Organisation de grande qualité pour un prix raisonnable.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'nesrine.amri@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Week-end de rêve à Tamerza ! La randonnée dans le canyon au '
                        . 'lever du soleil était magique. Le bivouac sous les étoiles a conclu '
                        . 'parfaitement cette expérience unique.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'hana.bouzid@outlook.com',
                    'note'       => 4,
                    'contenu'    => 'Très belle découverte ! Seul petit bémol : l\'accès routier '
                        . 'jusqu\'à Tamerza est assez difficile — mieux vaut un véhicule 4x4. '
                        . 'Le site lui-même est époustouflant.',
                    'response'   => 'Merci Hana ! On précisera dans les prochaines communications '
                        . 'que le 4x4 est recommandé. Bonne idée !',
                    'status'     => 'approved',
                ],
            ],

            // ── 9. Randonnée Côtière Sidi Bou Said ────────────────────────
            'Randonnée Côtière Sidi Bou Said — Carthage' => [
                [
                    'user_email' => 'zied.benali@gmail.com',
                    'note'       => 4,
                    'contenu'    => 'Randonnée culturelle très enrichissante ! Les ruines de Carthage '
                        . 'et la vue sur le golfe de Tunis depuis les hauteurs de Sidi Bou Said '
                        . 'valent le déplacement. Bien guidé et accessible à tous.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'chaima.bouzid@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'J\'ai adoré cette balade entre culture et nature. La dégustation '
                        . 'du thé à la menthe avec vue sur la mer a été le moment parfait pour '
                        . 'terminer la randonnée. À refaire absolument !',
                    'response'   => 'Merci Chaima ! Nous sommes ravis que cette combinaison culture '
                        . '+ nature vous ait plu. À bientôt !',
                    'status'     => 'approved',
                ],
                [
                    'user_email' => 'hamza.benali@gmail.com',
                    'note'       => 5,
                    'contenu'    => 'Super sortie pour débuter ! Pas trop difficile, beau paysage '
                        . 'et groupe jeune et sympa. Le prix de 10 DT est vraiment accessible.',
                    'response'   => null,
                    'status'     => 'approved',
                ],
                [
                    // pending moderation — low score without explanation
                    'user_email' => 'elyes.trabelsi@outlook.com',
                    'note'       => 1,
                    'contenu'    => 'Nul.',
                    'response'   => null,
                    'status'     => 'pending',  // under moderation review
                ],
            ],
        ];
    }
}
