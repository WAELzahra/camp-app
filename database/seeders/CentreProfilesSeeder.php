<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CentreProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('profile_center_equipment')->truncate();
        DB::table('profile_center_services')->truncate();
        DB::table('profile_centres')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Load service category IDs ──────────────────────────────────────────
        $cats = DB::table('service_categories')->pluck('id', 'name')->all();

        // ── Load centre users → profile_id ────────────────────────────────────
        $centreRoleId = DB::table('roles')->where('name', 'centre')->value('id');

        $centreUsers = DB::table('users')
            ->where('role_id', $centreRoleId)
            ->select('id', 'email', 'first_name', 'last_name', 'phone_number')
            ->get();

        $profileByUserId = DB::table('profiles')
            ->pluck('id', 'user_id')
            ->all();

        // ── Insert profile_centres ─────────────────────────────────────────────
        $centreDefs = $this->centreDefs();
        $profileCentreRows = [];

        foreach ($centreUsers as $u) {
            $def = $centreDefs[$u->email] ?? null;
            if (! $def) continue;

            $pid = $profileByUserId[$u->id] ?? null;
            if (! $pid) continue;

            $profileCentreRows[$u->email] = [
                'profile_id'                => $pid,
                'name'                      => $def['name'],
                'capacite'                  => $def['capacite'],
                'price_per_night'           => $def['price_per_night'],
                'category'                  => $def['category'],
                'legal_document'            => null,
                'document_legal_type'       => null,
                'document_legal_expiration' => null,
                'disponibilite'             => 1,
                'latitude'                  => $def['lat'],
                'longitude'                 => $def['lng'],
                'contact_email'             => $u->email,
                'contact_phone'             => $u->phone_number,
                'manager_name'              => $u->first_name . ' ' . $u->last_name,
                'established_date'          => $def['established'],
                'created_at'                => $now,
                'updated_at'                => $now,
            ];
        }

        DB::table('profile_centres')->insert(array_values($profileCentreRows));

        $this->command?->info('✅ ' . count($profileCentreRows) . ' profile_centres inserted.');

        // ── Query back profile_centre IDs keyed by email ───────────────────────
        $pcIdByEmail = DB::table('profile_centres as pc')
            ->join('profiles as p', 'p.id', '=', 'pc.profile_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->pluck('pc.id', 'u.email')
            ->all();

        // ── Insert services ────────────────────────────────────────────────────
        $serviceRows = [];
        foreach ($centreDefs as $email => $def) {
            $pcId = $pcIdByEmail[$email] ?? null;
            if (! $pcId) continue;

            foreach ($def['services'] as $svc) {
                $serviceRows[] = [
                    'profile_center_id'  => $pcId,
                    'service_category_id'=> $cats[$svc['cat']] ?? null,
                    'name'               => $svc['name'],
                    'price'              => $svc['price'],
                    'unit'               => $svc['unit'],
                    'description'        => $svc['desc'],
                    'is_available'       => 1,
                    'is_standard'        => $svc['standard'] ? 1 : 0,
                    'min_quantity'       => $svc['min'] ?? 1,
                    'max_quantity'       => $svc['max'] ?? null,
                    'nbr_place'          => $svc['places'] ?? null,
                    'is_refundable'      => 1,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
        }

        foreach (array_chunk($serviceRows, 50) as $chunk) {
            DB::table('profile_center_services')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($serviceRows) . ' profile_center_services inserted.');

        // ── Insert equipment ───────────────────────────────────────────────────
        $equipRows = [];
        foreach ($centreDefs as $email => $def) {
            $pcId = $pcIdByEmail[$email] ?? null;
            if (! $pcId) continue;

            foreach ($def['equipment'] as $eq) {
                $equipRows[] = [
                    'profile_center_id' => $pcId,
                    'type'              => $eq['type'],
                    'is_available'      => $eq['available'] ? 1 : 0,
                    'notes'             => $eq['notes'] ?? null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        foreach (array_chunk($equipRows, 50) as $chunk) {
            DB::table('profile_center_equipment')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($equipRows) . ' profile_center_equipment inserted.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Centre definitions — keyed by email
    // ──────────────────────────────────────────────────────────────────────────
    private function centreDefs(): array
    {
        return [
            'centre.capbon@gmail.com' => [
                'name'        => 'Centre Cap Bon Camping',
                'category'    => 'plage',
                'capacite'    => 120,
                'price_per_night' => 45.00,
                'lat'         => 36.4513, 'lng' => 10.7357,
                'established' => '1980-06-01',
                'services'    => [
                    ['cat'=>'Basic Camping',      'name'=>'Emplacement tente', 'price'=>45.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement sablonneux avec accès direct à la plage.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Tent Rental',        'name'=>'Location tente 2 places', 'price'=>30.00, 'unit'=>'nuit', 'desc'=>'Tente imperméable montée sur place.', 'standard'=>false, 'min'=>1, 'max'=>10, 'places'=>2],
                    ['cat'=>'Breakfast',          'name'=>'Petit-déjeuner continental', 'price'=>12.00, 'unit'=>'personne', 'desc'=>'Café, jus, pain, viennoiseries et fruits frais.', 'standard'=>false, 'min'=>1, 'max'=>50, 'places'=>null],
                    ['cat'=>'Transport Service',  'name'=>'Navette depuis Nabeul', 'price'=>8.00, 'unit'=>'personne/trajet', 'desc'=>'Navette aller-retour depuis le centre-ville de Nabeul.', 'standard'=>false, 'min'=>2, 'max'=>20, 'places'=>null],
                    ['cat'=>'Guided Tour',        'name'=>'Excursion Cap Bon', 'price'=>35.00, 'unit'=>'personne', 'desc'=>'Tour guidé des sites historiques et naturels du Cap Bon.', 'standard'=>false, 'min'=>4, 'max'=>20, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Blocs sanitaires rénovés, 6 cabines'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches eau chaude disponibles 7h-22h'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Robinets répartis sur tout le site'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Prises 220V disponibles pour emplacements premium'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking surveillé 80 places'],
                    ['type'=>'wifi',           'available'=>true,  'notes'=>'WiFi zone centrale, débit moyen'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Zone barbecue collective avec tables en pierre'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Gardien présent 24h/24'],
                ],
            ],

            'camping.hammam.bourguiba@gmail.com' => [
                'name'        => 'Camping Hammam Bourguiba',
                'category'    => 'forêt',
                'capacite'    => 80,
                'price_per_night' => 35.00,
                'lat'         => 37.2744, 'lng' => 9.8634,
                'established' => '1978-04-01',
                'services'    => [
                    ['cat'=>'Basic Camping',      'name'=>'Emplacement en forêt', 'price'=>35.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement ombragé sous les pins et eucalyptus.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>6],
                    ['cat'=>'Tent Rental',        'name'=>'Location tente familiale', 'price'=>40.00, 'unit'=>'nuit', 'desc'=>'Grande tente 4 places, sol renforcé.', 'standard'=>false, 'min'=>1, 'max'=>8, 'places'=>4],
                    ['cat'=>'BBQ Equipment',      'name'=>'Kit barbecue', 'price'=>15.00, 'unit'=>'location/jour', 'desc'=>'Barbecue, charbon, allume-feu et ustensiles fournis.', 'standard'=>false, 'min'=>1, 'max'=>20, 'places'=>null],
                    ['cat'=>'Sleeping Bag Rental','name'=>'Location sac de couchage', 'price'=>10.00, 'unit'=>'nuit', 'desc'=>'Sac de couchage 3 saisons propre et désinfecté.', 'standard'=>false, 'min'=>1, 'max'=>30, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires propres, nettoyage 2x/jour'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches eau froide (chaude en supplément)'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Fontaines aux emplacements'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking 50 places non surveillé'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Zone barbecue avec abri en cas de pluie'],
                    ['type'=>'electricity',    'available'=>false, 'notes'=>'Groupe électrogène disponible en location'],
                    ['type'=>'wifi',           'available'=>false, 'notes'=>'Pas de WiFi — zone de déconnexion volontaire'],
                ],
            ],

            'ecocamp.aindraham@gmail.com' => [
                'name'        => 'Eco Camp Aïn Draham',
                'category'    => 'montagne',
                'capacite'    => 50,
                'price_per_night' => 55.00,
                'lat'         => 36.7794, 'lng' => 8.6875,
                'established' => '1985-09-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Bivouac éco-responsable', 'price'=>55.00, 'unit'=>'nuit/personne', 'desc'=>'Emplacement en forêt de chênes-lièges, zéro déchet, matériel biodégradable.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>null],
                    ['cat'=>'Guided Tour',     'name'=>'Randonnée guidée forêt', 'price'=>45.00, 'unit'=>'personne', 'desc'=>'Guide naturaliste, 3h de randonnée dans la forêt de la Kroumirie.', 'standard'=>false, 'min'=>2, 'max'=>15, 'places'=>null],
                    ['cat'=>'Breakfast',       'name'=>'Petit-déjeuner bio local', 'price'=>18.00, 'unit'=>'personne', 'desc'=>'Produits locaux : figues, olives, fromage, pain maison.', 'standard'=>false, 'min'=>1, 'max'=>50, 'places'=>null],
                    ['cat'=>'Camping Chair Rental', 'name'=>'Kit camping complet', 'price'=>25.00, 'unit'=>'nuit', 'desc'=>'Chaise, table, lanterne solaire et tapis de sol.', 'standard'=>false, 'min'=>1, 'max'=>20, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Toilettes sèches écologiques'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Source naturelle filtrée sur place'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches solaires (eau chaude l\'après-midi)'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking 25 places en bordure de forêt'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Site clôturé, gardien nocturne'],
                    ['type'=>'wifi',           'available'=>false, 'notes'=>'Aucun WiFi — immersion totale en nature'],
                    ['type'=>'electricity',    'available'=>false, 'notes'=>'Panneaux solaires pour éclairage commun uniquement'],
                ],
            ],

            'camp.desert.douz@gmail.com' => [
                'name'        => 'Camp Désert Douz',
                'category'    => 'désert',
                'capacite'    => 60,
                'price_per_night' => 90.00,
                'lat'         => 33.4567, 'lng' => 9.0233,
                'established' => '1982-01-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Tente berbère traditionnelle', 'price'=>90.00, 'unit'=>'nuit/tente', 'desc'=>'Tente berbère authentique pour 2 personnes avec tapis et coussins.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>2],
                    ['cat'=>'Guided Tour',     'name'=>'Balade dromadaire coucher de soleil', 'price'=>60.00, 'unit'=>'personne', 'desc'=>'1h30 de balade sur les dunes avec guide local au coucher du soleil.', 'standard'=>false, 'min'=>1, 'max'=>20, 'places'=>null],
                    ['cat'=>'Dinner',          'name'=>'Dîner berbère traditionnel', 'price'=>35.00, 'unit'=>'personne', 'desc'=>'Couscous, tajine et thé à la menthe servis sous la tente.', 'standard'=>false, 'min'=>2, 'max'=>40, 'places'=>null],
                    ['cat'=>'Breakfast',       'name'=>'Petit-déjeuner saharien', 'price'=>20.00, 'unit'=>'personne', 'desc'=>'Dattes, lben, pain de chameau et café à la cardamome.', 'standard'=>false, 'min'=>1, 'max'=>40, 'places'=>null],
                    ['cat'=>'Transport Service','name'=>'4x4 excursion Ksar Ghilane', 'price'=>120.00, 'unit'=>'personne', 'desc'=>'Journée excursion en 4x4 jusqu\'à l\'oasis de Ksar Ghilane.', 'standard'=>false, 'min'=>2, 'max'=>8, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Toilettes sanitaires dans tentes séparées'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches eau chaude solaire disponibles'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau minérale fournie en bouteilles'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Générateur pour éclairage nocturne'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking sécurisé à l\'entrée du camp'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Gardiens nuit et jour, camp clôturé'],
                    ['type'=>'wifi',           'available'=>false, 'notes'=>'Pas de WiFi — contemplation du ciel étoilé'],
                ],
            ],

            'camping.plage.hammamet@gmail.com' => [
                'name'        => 'Camping Plage Hammamet',
                'category'    => 'plage',
                'capacite'    => 150,
                'price_per_night' => 50.00,
                'lat'         => 36.4000, 'lng' => 10.6167,
                'established' => '1979-07-01',
                'services'    => [
                    ['cat'=>'Basic Camping',      'name'=>'Emplacement bord de mer', 'price'=>50.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement face à la mer, 20m de la plage privée.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Cabin Rental',       'name'=>'Bungalow mer', 'price'=>120.00, 'unit'=>'nuit', 'desc'=>'Bungalow climatisé pour 4 personnes avec terrasse vue mer.', 'standard'=>false, 'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Breakfast',          'name'=>'Buffet petit-déjeuner', 'price'=>15.00, 'unit'=>'personne', 'desc'=>'Buffet complet avec fruits de mer, fromages et pâtisseries.', 'standard'=>false, 'min'=>1, 'max'=>80, 'places'=>null],
                    ['cat'=>'Lunch',              'name'=>'Barbecue midi', 'price'=>28.00, 'unit'=>'personne', 'desc'=>'Poisson grillé, salades et boissons fraîches.', 'standard'=>false, 'min'=>2, 'max'=>80, 'places'=>null],
                    ['cat'=>'Transport Service',  'name'=>'Navette Hammamet-Tunis', 'price'=>25.00, 'unit'=>'personne', 'desc'=>'Navette climatisée avec départs fixes matin et soir.', 'standard'=>false, 'min'=>4, 'max'=>30, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires modernes, nettoyage toutes les 2h'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches eau chaude en accès libre'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Distribution eau potable en continu'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Prises 220V à tous les emplacements'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Grand parking surveillé 200 places'],
                    ['type'=>'wifi',           'available'=>true,  'notes'=>'Fibre optique, excellente couverture'],
                    ['type'=>'swimming_pool',  'available'=>true,  'notes'=>'Piscine 25m + pataugeoire enfants'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'10 barbecues en pierre sur la plage'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Vidéosurveillance et gardiens 24h/24'],
                ],
            ],

            'centre.ichkeul@gmail.com' => [
                'name'        => 'Centre Aventure Ichkeul',
                'category'    => 'nature',
                'capacite'    => 45,
                'price_per_night' => 40.00,
                'lat'         => 37.1677, 'lng' => 9.6648,
                'established' => '1988-03-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Camping parc naturel', 'price'=>40.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement dans la zone tampon du parc national d\'Ichkeul.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>6],
                    ['cat'=>'Guided Tour',     'name'=>'Safari ornithologique', 'price'=>55.00, 'unit'=>'personne', 'desc'=>'Observation des flamants, cigognes et canards sauvages avec guide naturaliste.', 'standard'=>false, 'min'=>2, 'max'=>15, 'places'=>null],
                    ['cat'=>'Guided Tour',     'name'=>'Ascension Jebel Ichkeul', 'price'=>40.00, 'unit'=>'personne', 'desc'=>'Randonnée guidée vers le sommet du Jebel Ichkeul, 3h.', 'standard'=>false, 'min'=>2, 'max'=>12, 'places'=>null],
                    ['cat'=>'BBQ Equipment',   'name'=>'Pique-nique équipé', 'price'=>20.00, 'unit'=>'groupe', 'desc'=>'Table, nappe, barbecue et matériel de pique-nique pour 6 personnes.', 'standard'=>false, 'min'=>1, 'max'=>10, 'places'=>6],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires en bois naturel'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau potable au robinet'],
                    ['type'=>'showers',        'available'=>false, 'notes'=>'Pas de douches — site de nature sauvage'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking 30 places à l\'entrée du parc'],
                    ['type'=>'electricity',    'available'=>false, 'notes'=>'Site hors réseau — apporter batterie portative'],
                    ['type'=>'wifi',           'available'=>false, 'notes'=>'Aucune connexion — immersion nature'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Rangers du parc présents en journée'],
                ],
            ],

            'camping.oasis.nefta@gmail.com' => [
                'name'        => 'Camping Oasis de Nefta',
                'category'    => 'oasis',
                'capacite'    => 55,
                'price_per_night' => 65.00,
                'lat'         => 33.8792, 'lng' => 7.8778,
                'established' => '1983-01-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Tente sous les palmiers', 'price'=>65.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement ombragé sous les palmiers dattiers centenaires de Nefta.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Guided Tour',     'name'=>'Visite oasis et Corbeille', 'price'=>30.00, 'unit'=>'personne', 'desc'=>'Visite guidée de la Corbeille de Nefta et de ses palmeraies.', 'standard'=>false, 'min'=>2, 'max'=>20, 'places'=>null],
                    ['cat'=>'Dinner',          'name'=>'Couscous traditionnel oasien', 'price'=>30.00, 'unit'=>'personne', 'desc'=>'Couscous au poulet et légumes, recette de l\'oasis de Nefta.', 'standard'=>false, 'min'=>4, 'max'=>30, 'places'=>null],
                    ['cat'=>'Transport Service','name'=>'Excursion Chott el-Jérid', 'price'=>45.00, 'unit'=>'personne', 'desc'=>'Sortie au Chott el-Jérid, lever de soleil et mirages.', 'standard'=>false, 'min'=>2, 'max'=>15, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires modernes à proximité'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Eau de source naturelle chauffée au solaire'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau de source filtrée'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Prises disponibles en zone centrale'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking ombragé 40 places'],
                    ['type'=>'wifi',           'available'=>true,  'notes'=>'WiFi zone accueil, débit limité'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Foyers traditionnels pour thé et cuisine'],
                ],
            ],

            'centre.ksar.ghilane@gmail.com' => [
                'name'        => 'Camp de Luxe Ksar Ghilane',
                'category'    => 'désert',
                'capacite'    => 40,
                'price_per_night' => 150.00,
                'lat'         => 32.9726, 'lng' => 9.6308,
                'established' => '1986-01-01',
                'services'    => [
                    ['cat'=>'Cabin Rental',    'name'=>'Tente berbère luxe', 'price'=>150.00, 'unit'=>'nuit/tente', 'desc'=>'Tente berbère premium avec literie 5 étoiles, salle de bain privative et AC.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>2],
                    ['cat'=>'Dinner',          'name'=>'Dîner gastronomique sous les étoiles', 'price'=>80.00, 'unit'=>'personne', 'desc'=>'Menu 4 plats avec spécialités sahariennes revisitées, musiciens locaux.', 'standard'=>false, 'min'=>2, 'max'=>30, 'places'=>null],
                    ['cat'=>'Guided Tour',     'name'=>'Source thermale privative', 'price'=>40.00, 'unit'=>'heure/couple', 'desc'=>'Accès privatisé à la source chaude naturelle de Ksar Ghilane.', 'standard'=>false, 'min'=>1, 'max'=>null, 'places'=>2],
                    ['cat'=>'Guided Tour',     'name'=>'Bivouac étoiles premium', 'price'=>100.00, 'unit'=>'personne', 'desc'=>'Nuit de bivouac avec télescope, guide astro et transfert 4x4 luxe.', 'standard'=>false, 'min'=>2, 'max'=>8, 'places'=>null],
                    ['cat'=>'Transport Service','name'=>'Transfert 4x4 luxe', 'price'=>200.00, 'unit'=>'véhicule', 'desc'=>'4x4 Land Cruiser depuis Douz ou Gabès jusqu\'à Ksar Ghilane.', 'standard'=>false, 'min'=>1, 'max'=>null, 'places'=>5],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Salle de bain privée dans chaque tente'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Eau chaude 24h/24 par chauffage solaire'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau minérale en bouteille fournie en illimité'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Générateur silencieux + solaire'],
                    ['type'=>'swimming_pool',  'available'=>true,  'notes'=>'Piscine naturelle avec eau thermale de la source'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Hangar véhicules climatisé pour 4x4'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Sécurité discrète 24h/24'],
                    ['type'=>'wifi',           'available'=>true,  'notes'=>'Satellite très haut débit'],
                ],
            ],

            'camp.nature.beja@gmail.com' => [
                'name'        => 'Camp Nature Béja',
                'category'    => 'forêt',
                'capacite'    => 60,
                'price_per_night' => 38.00,
                'lat'         => 36.7261, 'lng' => 9.1811,
                'established' => '1990-05-01',
                'services'    => [
                    ['cat'=>'Basic Camping',      'name'=>'Emplacement forêt Béja', 'price'=>38.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement naturel dans les chênaies de Béja.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Tent Rental',        'name'=>'Location tente dôme', 'price'=>25.00, 'unit'=>'nuit', 'desc'=>'Tente dôme légère 3 places, idéale pour randonneurs.', 'standard'=>false, 'min'=>1, 'max'=>15, 'places'=>3],
                    ['cat'=>'Guided Tour',        'name'=>'Trek Jebel Ghorra', 'price'=>50.00, 'unit'=>'personne', 'desc'=>'Randonnée guidée vers le sommet du Jebel Ghorra (900m de dénivelé).', 'standard'=>false, 'min'=>2, 'max'=>10, 'places'=>null],
                    ['cat'=>'Sleeping Bag Rental','name'=>'Location sac de couchage hiver', 'price'=>15.00, 'unit'=>'nuit', 'desc'=>'Sac de couchage -10°C pour les nuits fraîches de Béja.', 'standard'=>false, 'min'=>1, 'max'=>20, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires dans chalet central'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches chaudes, eau de source'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Source naturelle potable sur site'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking 40 places'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Prises en zone commune uniquement'],
                    ['type'=>'kitchen',        'available'=>true,  'notes'=>'Cuisine commune équipée (réfrigérateur, réchaud)'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Zone feu de camp autorisée avec surveillance'],
                ],
            ],

            'camping.lac.ichkeul@gmail.com' => [
                'name'        => 'Camping Lac Ichkeul',
                'category'    => 'lac',
                'capacite'    => 70,
                'price_per_night' => 32.00,
                'lat'         => 37.1500, 'lng' => 9.7000,
                'established' => '1984-01-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Emplacement rive du lac', 'price'=>32.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement au bord du lac Ichkeul, site UNESCO.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>5],
                    ['cat'=>'Guided Tour',     'name'=>'Tour en barque lac Ichkeul', 'price'=>40.00, 'unit'=>'barque (4 pers.)', 'desc'=>'Observation des oiseaux migrateurs depuis une barque traditionnelle.', 'standard'=>false, 'min'=>2, 'max'=>4, 'places'=>4],
                    ['cat'=>'BBQ Equipment',   'name'=>'Barbecue lac', 'price'=>18.00, 'unit'=>'location', 'desc'=>'Barbecue avec charbon et ustensiles pour 6 personnes.', 'standard'=>false, 'min'=>1, 'max'=>15, 'places'=>6],
                    ['cat'=>'Breakfast',       'name'=>'Déjeuner pêcheurs', 'price'=>22.00, 'unit'=>'personne', 'desc'=>'Poissons du lac frits et salade mechouia, recette locale.', 'standard'=>false, 'min'=>2, 'max'=>30, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires au bord de l\'eau'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches solaires disponibles'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau potable'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking 60 places'],
                    ['type'=>'electricity',    'available'=>false, 'notes'=>'Panneau solaire zone commune seulement'],
                    ['type'=>'wifi',           'available'=>false, 'notes'=>'Pas de WiFi'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Zone barbecue collective vue sur le lac'],
                ],
            ],

            'camp.sousse.plage@gmail.com' => [
                'name'        => 'Camp Sousse Plage',
                'category'    => 'plage',
                'capacite'    => 100,
                'price_per_night' => 48.00,
                'lat'         => 35.8245, 'lng' => 10.6346,
                'established' => '1981-06-01',
                'services'    => [
                    ['cat'=>'Basic Camping',      'name'=>'Emplacement plage Sousse', 'price'=>48.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement sableux à 50m de la mer, Sahel tunisien.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Cabin Rental',       'name'=>'Bungalow bois', 'price'=>95.00, 'unit'=>'nuit', 'desc'=>'Bungalow en bois pour 4 avec ventilateur et terrasse.', 'standard'=>false, 'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Lunch',              'name'=>'Barbecue poissons', 'price'=>30.00, 'unit'=>'personne', 'desc'=>'Loup de mer, daurade et calamars grillés avec accompagnements.', 'standard'=>false, 'min'=>2, 'max'=>50, 'places'=>null],
                    ['cat'=>'Transport Service',  'name'=>'Jet-ski', 'price'=>60.00, 'unit'=>'demi-heure', 'desc'=>'Location de jet-ski avec équipement de sécurité.', 'standard'=>false, 'min'=>1, 'max'=>null, 'places'=>1],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires rénovés 2023'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches eau douce après baignade'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau potable'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'220V emplacements premium'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking gardé 100 places'],
                    ['type'=>'wifi',           'available'=>true,  'notes'=>'WiFi plage disponible'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Terrasse barbecue face à la mer'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Surveillance nocturne'],
                ],
            ],

            'centre.trekking.zaghouan@gmail.com' => [
                'name'        => 'Centre Trekking Zaghouan',
                'category'    => 'montagne',
                'capacite'    => 35,
                'price_per_night' => 42.00,
                'lat'         => 36.3972, 'lng' => 10.1086,
                'established' => '1987-01-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Bivouac Zaghouan', 'price'=>42.00, 'unit'=>'nuit/personne', 'desc'=>'Bivouac au pied du Jebel Zaghouan, près du temple des eaux.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>null],
                    ['cat'=>'Guided Tour',     'name'=>'Ascension temple des eaux', 'price'=>45.00, 'unit'=>'personne', 'desc'=>'Randonnée guidée vers le temple romain de Zaghouan et le sommet.', 'standard'=>false, 'min'=>2, 'max'=>15, 'places'=>null],
                    ['cat'=>'Tent Rental',     'name'=>'Location tente alpine', 'price'=>35.00, 'unit'=>'nuit', 'desc'=>'Tente 4 saisons résistante au vent pour camping en altitude.', 'standard'=>false, 'min'=>1, 'max'=>10, 'places'=>2],
                    ['cat'=>'Guided Tour',     'name'=>'Circuit aqueducs romains', 'price'=>30.00, 'unit'=>'personne', 'desc'=>'Randonnée sur le tracé de l\'aqueduc romain Zaghouan-Carthage, 20 km.', 'standard'=>false, 'min'=>4, 'max'=>20, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Cabines de toilettes portables'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau de source naturelle'],
                    ['type'=>'showers',        'available'=>false, 'notes'=>'Pas de douches sur le site de bivouac'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking au col, 20 places'],
                    ['type'=>'electricity',    'available'=>false, 'notes'=>'Hors réseau — apporter batterie'],
                    ['type'=>'wifi',           'available'=>false, 'notes'=>'Réseau téléphonique limité en altitude'],
                    ['type'=>'kitchen',        'available'=>true,  'notes'=>'Cuisine de refuge pour groupes'],
                ],
            ],

            'camp.tabarka.foret@gmail.com' => [
                'name'        => 'Camp Tabarka Forêt',
                'category'    => 'forêt',
                'capacite'    => 75,
                'price_per_night' => 52.00,
                'lat'         => 36.9544, 'lng' => 8.7583,
                'established' => '1989-06-01',
                'services'    => [
                    ['cat'=>'Basic Camping',      'name'=>'Emplacement pinède Tabarka', 'price'=>52.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement sous les pins parasols face à la mer de Tabarka.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>6],
                    ['cat'=>'Tent Rental',        'name'=>'Location tente familiale 6 places', 'price'=>50.00, 'unit'=>'nuit', 'desc'=>'Grande tente familiale montée et équipée.', 'standard'=>false, 'min'=>1, 'max'=>8, 'places'=>6],
                    ['cat'=>'Guided Tour',        'name'=>'Plongée sous-marine Tabarka', 'price'=>65.00, 'unit'=>'personne', 'desc'=>'Sortie plongée dans les fonds marins de Tabarka (PADI).', 'standard'=>false, 'min'=>2, 'max'=>8, 'places'=>null],
                    ['cat'=>'Breakfast',          'name'=>'Petit-déjeuner forêt', 'price'=>14.00, 'unit'=>'personne', 'desc'=>'Produits locaux et spécialités de Tabarka.', 'standard'=>false, 'min'=>1, 'max'=>60, 'places'=>null],
                    ['cat'=>'BBQ Equipment',      'name'=>'Soirée barbecue animée', 'price'=>40.00, 'unit'=>'personne', 'desc'=>'Barbecue collectif avec animation musicale, 3h.', 'standard'=>false, 'min'=>10, 'max'=>60, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Blocs sanitaires répartis sur le site'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches eau chaude disponibles'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau potable'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Bornes électriques emplacements premium'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Grand parking 80 places'],
                    ['type'=>'wifi',           'available'=>true,  'notes'=>'WiFi zone d\'accueil'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Zone barbecue couverte'],
                    ['type'=>'security',       'available'=>true,  'notes'=>'Gardien nocturne'],
                ],
            ],

            'camp.sfax.nature@gmail.com' => [
                'name'        => 'Camp Sfax Nature',
                'category'    => 'nature',
                'capacite'    => 40,
                'price_per_night' => 28.00,
                'lat'         => 34.7406, 'lng' => 10.7603,
                'established' => '1983-01-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Emplacement oliveraie', 'price'=>28.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement dans une oliveraie centenaire aux environs de Sfax.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Guided Tour',     'name'=>'Découverte oliveraie biologique', 'price'=>20.00, 'unit'=>'personne', 'desc'=>'Visite guidée d\'une oliveraie biologique avec dégustation d\'huile.', 'standard'=>false, 'min'=>2, 'max'=>20, 'places'=>null],
                    ['cat'=>'Lunch',           'name'=>'Déjeuner sfaxien', 'price'=>25.00, 'unit'=>'personne', 'desc'=>'Spécialités de Sfax : kamounia, poisson à la charmoula et makroudh.', 'standard'=>false, 'min'=>4, 'max'=>30, 'places'=>null],
                    ['cat'=>'Transport Service','name'=>'Excursion Kerkennah', 'price'=>30.00, 'unit'=>'personne', 'desc'=>'Sortie en ferry aux îles Kerkennah avec guide local.', 'standard'=>false, 'min'=>2, 'max'=>20, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires simples et propres'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau potable'],
                    ['type'=>'showers',        'available'=>false, 'notes'=>'Pas de douches — site nature rudimentaire'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking 30 places'],
                    ['type'=>'electricity',    'available'=>false, 'notes'=>'Pas d\'électricité'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Foyers traditionnels'],
                    ['type'=>'kitchen',        'available'=>true,  'notes'=>'Cuisine ouverte commune'],
                ],
            ],

            'centre.camping.kairouan@gmail.com' => [
                'name'        => 'Centre Camping Kairouan',
                'category'    => 'culturel',
                'capacite'    => 65,
                'price_per_night' => 30.00,
                'lat'         => 35.6781, 'lng' => 10.0963,
                'established' => '1985-01-01',
                'services'    => [
                    ['cat'=>'Basic Camping',   'name'=>'Emplacement Kairouan', 'price'=>30.00, 'unit'=>'nuit/emplacement', 'desc'=>'Emplacement proche de la médina de Kairouan, ville du patrimoine.', 'standard'=>true,  'min'=>1, 'max'=>null, 'places'=>4],
                    ['cat'=>'Guided Tour',     'name'=>'Visite guidée médina & mosquée', 'price'=>35.00, 'unit'=>'personne', 'desc'=>'Visite guidée de la Grande Mosquée de Kairouan et de la médina historique.', 'standard'=>false, 'min'=>2, 'max'=>25, 'places'=>null],
                    ['cat'=>'Dinner',          'name'=>'Dîner traditionnel kairouan', 'price'=>28.00, 'unit'=>'personne', 'desc'=>'Assiette de makroudh, couscous local et thé à la fleur d\'oranger.', 'standard'=>false, 'min'=>4, 'max'=>40, 'places'=>null],
                    ['cat'=>'Guided Tour',     'name'=>'Circuit piscines Aghlabides', 'price'=>15.00, 'unit'=>'personne', 'desc'=>'Visite des bassins aghlabides du IXe siècle, joyau de l\'architecture islamique.', 'standard'=>false, 'min'=>1, 'max'=>30, 'places'=>null],
                ],
                'equipment' => [
                    ['type'=>'toilets',        'available'=>true,  'notes'=>'Sanitaires modernes'],
                    ['type'=>'showers',        'available'=>true,  'notes'=>'Douches eau chaude'],
                    ['type'=>'drinking_water', 'available'=>true,  'notes'=>'Eau potable'],
                    ['type'=>'electricity',    'available'=>true,  'notes'=>'Prises 220V disponibles'],
                    ['type'=>'parking',        'available'=>true,  'notes'=>'Parking 50 places sécurisé'],
                    ['type'=>'wifi',           'available'=>true,  'notes'=>'WiFi dans la zone commune'],
                    ['type'=>'kitchen',        'available'=>true,  'notes'=>'Cuisine équipée pour les groupes'],
                    ['type'=>'bbq_area',       'available'=>true,  'notes'=>'Terrasse barbecue couverte'],
                ],
            ],
        ];
    }
}
