<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BoutiquesAndMateriellesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('materielles')->truncate();
        DB::table('boutiques')->truncate();
        DB::table('materielles_categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Categories ────────────────────────────────────────────────────────
        $categories = [
            ['nom' => 'Tentes',               'description' => 'Tentes de camping, bivouac et désert pour toutes les conditions.',          'trip_contexts' => ['camping', 'desert', 'montagne'],            'icon' => 'tent',      'is_safety_critical' => false],
            ['nom' => 'Sacs de couchage',      'description' => 'Sacs de couchage pour toutes les températures et saisons.',                'trip_contexts' => ['camping', 'montagne', 'desert'],            'icon' => 'sleeping',  'is_safety_critical' => false],
            ['nom' => 'Cuisine outdoor',       'description' => 'Réchauds, vaisselle et équipements de cuisine pour le camping.',           'trip_contexts' => ['camping', 'famille', 'groupe'],             'icon' => 'cooking',   'is_safety_critical' => false],
            ['nom' => 'Navigation',            'description' => 'GPS, boussoles, cartes et outils de navigation pour randonneurs.',         'trip_contexts' => ['randonnee', 'montagne', 'desert'],          'icon' => 'compass',   'is_safety_critical' => true],
            ['nom' => 'Sécurité',              'description' => 'Kits de survie, premiers secours et équipements de sécurité.',             'trip_contexts' => ['all'],                                      'icon' => 'shield',    'is_safety_critical' => true],
            ['nom' => 'Vêtements techniques',  'description' => 'Vêtements imperméables, chaussures de randonnée et équipements techniques.','trip_contexts' => ['randonnee', 'montagne', 'desert'],         'icon' => 'jacket',    'is_safety_critical' => false],
            ['nom' => 'Éclairage',             'description' => 'Lampes frontales, lanternes et éclairages outdoor.',                       'trip_contexts' => ['camping', 'desert', 'grotte'],              'icon' => 'flashlight','is_safety_critical' => false],
            ['nom' => 'Transport & stockage',  'description' => 'Sacs à dos, sacoches et équipements de transport pour randonneurs.',       'trip_contexts' => ['camping', 'randonnee'],                     'icon' => 'backpack',  'is_safety_critical' => false],
        ];

        foreach ($categories as &$cat) {
            $cat['trip_contexts'] = json_encode($cat['trip_contexts'], JSON_UNESCAPED_UNICODE);
            $cat['created_at']    = $now;
            $cat['updated_at']    = $now;
        }
        unset($cat);

        DB::table('materielles_categories')->insert($categories);

        $catId = DB::table('materielles_categories')->pluck('id', 'nom')->all();

        // ── Fournisseur user IDs ──────────────────────────────────────────────
        $fournisseurRoleId = DB::table('roles')->where('name', 'fournisseur')->value('id');
        $fournisseurs      = DB::table('users')
            ->where('role_id', $fournisseurRoleId)
            ->pluck('id', 'email')
            ->all();

        // ── Boutiques + Materielles definitions ───────────────────────────────
        $shopDefs = $this->shopDefinitions();

        $boutiqueRows   = [];
        $materielleRows = [];

        foreach ($shopDefs as $def) {
            $uid = $fournisseurs[$def['email']] ?? null;
            if (! $uid) continue;

            $boutiqueRows[] = [
                'fournisseur_id' => $uid,
                'nom_boutique'   => $def['nom_boutique'],
                'description'    => $def['description'],
                'status'         => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            foreach ($def['items'] as $item) {
                $materielleRows[] = [
                    'fournisseur_id'      => $uid,
                    'category_id'         => $catId[$item['category']],
                    'nom'                 => $item['nom'],
                    'brand'               => $item['brand'],
                    'description'         => $item['description'],
                    'trip_type_tags'      => json_encode($item['tags'],      JSON_UNESCAPED_UNICODE),
                    'weight_kg'           => $item['weight_kg'],
                    'condition'           => $item['condition'],
                    'is_rentable'         => $item['is_rentable'],
                    'is_sellable'         => $item['is_sellable'],
                    'tarif_nuit'          => $item['tarif_nuit'],
                    'prix_vente'          => $item['prix_vente'],
                    'quantite_total'      => $item['quantite'],
                    'quantite_dispo'      => $item['quantite'],
                    'livraison_disponible'=> $item['livraison'] ?? false,
                    'frais_livraison'     => $item['frais_livraison'] ?? null,
                    'status'              => 'up',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }

        foreach (array_chunk($boutiqueRows, 50) as $chunk)    DB::table('boutiques')->insert($chunk);
        foreach (array_chunk($materielleRows, 50) as $chunk)  DB::table('materielles')->insert($chunk);

        $this->command?->info('✅ ' . count($boutiqueRows) . ' boutiques and ' . count($materielleRows) . ' materielles inserted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shop definitions — one entry per fournisseur
    // ─────────────────────────────────────────────────────────────────────────
    private function shopDefinitions(): array
    {
        return [

            // ── 1. OutdoorTunis Pro — Tentes & Sacs de couchage ──────────────
            [
                'email'        => 'outdoor.tunis.pro@gmail.com',
                'nom_boutique' => 'OutdoorTunis Pro',
                'description'  => 'Tentes et sacs de couchage premium pour randonneurs et campeurs tunisiens.',
                'items' => [
                    ['nom'=>'Tente Quechua MH100 2 personnes','brand'=>'Quechua','category'=>'Tentes','description'=>'Tente légère et résistante idéale pour la randonnée, montage rapide en 2 minutes.','tags'=>['randonnee','montagne','camping'],'weight_kg'=>1.9,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>12.00,'prix_vente'=>135.00,'quantite'=>5,'livraison'=>true,'frais_livraison'=>8.00],
                    ['nom'=>'Tente MSR Hubba Hubba NX 2p','brand'=>'MSR','category'=>'Tentes','description'=>'Tente ultralegère 3 saisons, idéale pour le trekking exigeant et les longues randonnées.','tags'=>['randonnee','montagne','solo','aventure'],'weight_kg'=>1.2,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>22.00,'prix_vente'=>480.00,'quantite'=>3,'livraison'=>true,'frais_livraison'=>10.00],
                    ['nom'=>'Tente Quechua Arpenaz 4.1 familiale','brand'=>'Quechua','category'=>'Tentes','description'=>'Tente 4 personnes confortable, parfaite pour les sorties familiales en camping.','tags'=>['famille','camping','plage'],'weight_kg'=>5.8,'condition'=>'good','is_rentable'=>true,'is_sellable'=>false,'tarif_nuit'=>18.00,'prix_vente'=>null,'quantite'=>4,'livraison'=>true,'frais_livraison'=>12.00],
                    ['nom'=>'Tente Deuter Alta Pro 3p','brand'=>'Deuter','category'=>'Tentes','description'=>'Tente 3 personnes robuste pour les conditions météo difficiles.','tags'=>['montagne','camping','groupe'],'weight_kg'=>3.2,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>16.00,'prix_vente'=>320.00,'quantite'=>4,'livraison'=>false,'frais_livraison'=>null],
                    ['nom'=>'Sac de couchage Deuter Astro 500','brand'=>'Deuter','category'=>'Sacs de couchage','description'=>'Sac de couchage en duvet confort -5°C, idéal pour les nuits fraîches en montagne.','tags'=>['montagne','camping','randonnee'],'weight_kg'=>0.9,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>8.00,'prix_vente'=>195.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Sac de couchage MSR Remmitts 0°C','brand'=>'MSR','category'=>'Sacs de couchage','description'=>'Sac de couchage synthétique 3 saisons, résistant à l\'humidité et léger.','tags'=>['montagne','desert','bivouac'],'weight_kg'=>1.1,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>10.00,'prix_vente'=>245.00,'quantite'=>6,'livraison'=>true,'frais_livraison'=>5.00],
                ],
            ],

            // ── 2. Camp Équipement Sfax — Cuisine outdoor ────────────────────
            [
                'email'        => 'camp.equipement.sfax@gmail.com',
                'nom_boutique' => 'Camp Équipement Sfax',
                'description'  => 'Tout l\'équipement de cuisine outdoor pour vos sorties camping en famille ou en groupe.',
                'items' => [
                    ['nom'=>'Réchaud Coleman Classic 2 feux','brand'=>'Coleman','category'=>'Cuisine outdoor','description'=>'Réchaud à gaz double brûleur, idéal pour cuisiner en camping pour 4 à 6 personnes.','tags'=>['camping','famille','groupe'],'weight_kg'=>2.1,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>7.00,'prix_vente'=>90.00,'quantite'=>6,'livraison'=>false,'frais_livraison'=>null],
                    ['nom'=>'Réchaud Campingaz Party Grill','brand'=>'Campingaz','category'=>'Cuisine outdoor','description'=>'Réchaud et grill combiné, parfait pour les repas en plein air.','tags'=>['camping','famille','plage'],'weight_kg'=>1.4,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>9.00,'prix_vente'=>125.00,'quantite'=>5,'livraison'=>false,'frais_livraison'=>null],
                    ['nom'=>'Kit cuisine camping Campingaz 8p','brand'=>'Campingaz','category'=>'Cuisine outdoor','description'=>'Set complet casseroles et poêles anti-adhésives légères pour groupe de 8.','tags'=>['camping','groupe','famille'],'weight_kg'=>1.6,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>6.00,'prix_vente'=>150.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>8.00],
                    ['nom'=>'Glacière Coleman 50L','brand'=>'Coleman','category'=>'Cuisine outdoor','description'=>'Glacière rigide 50L haute performance, maintien du froid 4 jours.','tags'=>['camping','famille','plage'],'weight_kg'=>3.8,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>9.00,'prix_vente'=>175.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>10.00],
                    ['nom'=>'Barbecue portable Weber Go-Anywhere','brand'=>'Weber','category'=>'Cuisine outdoor','description'=>'Barbecue au charbon compact et portable pour le camping.','tags'=>['camping','famille','plage','groupe'],'weight_kg'=>2.0,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>10.00,'prix_vente'=>145.00,'quantite'=>5,'livraison'=>false,'frais_livraison'=>null],
                ],
            ],

            // ── 3. Côte Outdoor Sousse — Équipement côtier & nautique ─────────
            [
                'email'        => 'cote.outdoor.sousse@gmail.com',
                'nom_boutique' => 'Côte Outdoor Sousse',
                'description'  => 'Équipements nautiques et de plage pour tous vos séjours camping côtiers.',
                'items' => [
                    ['nom'=>'Kayak gonflable Intex 2p','brand'=>'Intex','category'=>'Transport & stockage','description'=>'Kayak gonflable stable et robuste pour explorer les côtes tunisiennes.','tags'=>['plage','nautique','aventure'],'weight_kg'=>9.5,'condition'=>'good','is_rentable'=>true,'is_sellable'=>false,'tarif_nuit'=>35.00,'prix_vente'=>null,'quantite'=>3,'livraison'=>false,'frais_livraison'=>null],
                    ['nom'=>'Set snorkeling Cressi complet','brand'=>'Cressi','category'=>'Sécurité','description'=>'Masque + palmes + tuba de qualité professionnelle pour la plongée côtière.','tags'=>['plage','nautique','baignade'],'weight_kg'=>0.8,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>5.00,'prix_vente'=>65.00,'quantite'=>10,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Tente de plage anti-UV UPF50+','brand'=>'Quechua','category'=>'Tentes','description'=>'Abri de plage facile à monter, protection solaire maximale pour la famille.','tags'=>['plage','famille','camping'],'weight_kg'=>1.2,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>8.00,'prix_vente'=>85.00,'quantite'=>6,'livraison'=>true,'frais_livraison'=>6.00],
                    ['nom'=>'Stand Up Paddle gonflable 10\'6','brand'=>'Itiwit','category'=>'Transport & stockage','description'=>'Planche SUP gonflable avec pagaie réglable, idéale pour les eaux calmes.','tags'=>['plage','nautique','sport'],'weight_kg'=>7.5,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>28.00,'prix_vente'=>380.00,'quantite'=>2,'livraison'=>false,'frais_livraison'=>null],
                    ['nom'=>'Parasol de plage XXL 240cm','brand'=>'Decathlon','category'=>'Transport & stockage','description'=>'Grand parasol de plage ultra résistant au vent avec ancre à sable.','tags'=>['plage','famille','camping'],'weight_kg'=>1.5,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>5.00,'prix_vente'=>45.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>4.00],
                ],
            ],

            // ── 4. Sahara Gear Tozeur — Équipement désertique ────────────────
            [
                'email'        => 'sahara.gear.tozeur@gmail.com',
                'nom_boutique' => 'Sahara Gear Tozeur',
                'description'  => 'Matériel spécialisé pour expéditions désertiques et bivouac saharien.',
                'items' => [
                    ['nom'=>'GPS Garmin eTrex 32x','brand'=>'Garmin','category'=>'Navigation','description'=>'GPS de randonnée robuste avec cartographie TopoActive, résistant eau et chocs.','tags'=>['desert','randonnee','montagne','navigation'],'weight_kg'=>0.14,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>18.00,'prix_vente'=>385.00,'quantite'=>5,'livraison'=>true,'frais_livraison'=>7.00],
                    ['nom'=>'Tente bivouac désert Ferrino Lightent','brand'=>'Ferrino','category'=>'Tentes','description'=>'Tente ultralegère spéciale désert, résistance au vent et sable, 1,1kg.','tags'=>['desert','bivouac','solo','aventure'],'weight_kg'=>1.1,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>16.00,'prix_vente'=>240.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>9.00],
                    ['nom'=>'Kit survie désert 72h','brand'=>'BCB','category'=>'Sécurité','description'=>'Kit de survie complet : purificateur eau, couverture survie, signaux, trousse secours.','tags'=>['desert','survie','securite'],'weight_kg'=>0.5,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>155.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Boussole Suunto A-10','brand'=>'Suunto','category'=>'Navigation','description'=>'Boussole de base fiable et précise pour navigation en terrain désertique.','tags'=>['desert','navigation','randonnee'],'weight_kg'=>0.04,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>65.00,'quantite'=>12,'livraison'=>true,'frais_livraison'=>4.00],
                    ['nom'=>'Filtre à eau LifeStraw Personal','brand'=>'LifeStraw','category'=>'Sécurité','description'=>'Filtre à eau portable élimine 99,9% des bactéries et parasites, 1000L de capacité.','tags'=>['desert','survie','randonnee','securite'],'weight_kg'=>0.06,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>58.00,'quantite'=>15,'livraison'=>true,'frais_livraison'=>4.00],
                    ['nom'=>'Sac à dos désert Osprey Aether 70L','brand'=>'Osprey','category'=>'Transport & stockage','description'=>'Sac à dos 70L haute performance, suspension ajustable pour longs treks désertiques.','tags'=>['desert','randonnee','aventure'],'weight_kg'=>2.2,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>14.00,'prix_vente'=>295.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>8.00],
                ],
            ],

            // ── 5. Trek Nord Bizerte — Vêtements & Randonnée ─────────────────
            [
                'email'        => 'trek.nord.bizerte@gmail.com',
                'nom_boutique' => 'Trek Nord Bizerte',
                'description'  => 'Vêtements techniques et équipements de randonnée pour le nord tunisien.',
                'items' => [
                    ['nom'=>'Veste imperméable Columbia Watertight II','brand'=>'Columbia','category'=>'Vêtements techniques','description'=>'Veste de pluie légère et compacte, imperméable et respirante pour la randonnée.','tags'=>['randonnee','montagne','foret'],'weight_kg'=>0.35,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>225.00,'quantite'=>6,'livraison'=>true,'frais_livraison'=>6.00],
                    ['nom'=>'Bâtons de randonnée Leki Cressida paire','brand'=>'Leki','category'=>'Transport & stockage','description'=>'Paire de bâtons pliants en aluminium avec poignées antitranspirant.','tags'=>['randonnee','montagne','trekking'],'weight_kg'=>0.4,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>5.00,'prix_vente'=>165.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Chaussures randonnée Merrell Moab 3','brand'=>'Merrell','category'=>'Vêtements techniques','description'=>'Chaussures de randonnée imperméables, semelle Vibram, confort toute la journée.','tags'=>['randonnee','montagne','foret'],'weight_kg'=>0.55,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>320.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>7.00],
                    ['nom'=>'Pantalon trekking Quechua MH500','brand'=>'Quechua','category'=>'Vêtements techniques','description'=>'Pantalon modulable zip-off léger et résistant, idéal randonnée multi-jours.','tags'=>['randonnee','montagne','camping'],'weight_kg'=>0.25,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>85.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Guêtres imperméables Outdoor Research','brand'=>'Outdoor Research','category'=>'Vêtements techniques','description'=>'Guêtres hautes imperméables, protection boue et neige pour terrains difficiles.','tags'=>['randonnee','montagne','foret'],'weight_kg'=>0.18,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>48.00,'quantite'=>10,'livraison'=>true,'frais_livraison'=>4.00],
                ],
            ],

            // ── 6. Camp Générale Monastir — Camping général ───────────────────
            [
                'email'        => 'camp.generale.monastir@gmail.com',
                'nom_boutique' => 'Camp Générale Monastir',
                'description'  => 'Location et vente de matériel de camping généraliste pour toutes les activités.',
                'items' => [
                    ['nom'=>'Tente camping 3p Quechua 2 Seconds','brand'=>'Quechua','category'=>'Tentes','description'=>'Tente pop-up 3 personnes montage instantané, idéale pour campeurs débutants.','tags'=>['camping','famille','plage'],'weight_kg'=>3.5,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>10.00,'prix_vente'=>145.00,'quantite'=>5,'livraison'=>false,'frais_livraison'=>null],
                    ['nom'=>'Sac de couchage été Quechua 15°C','brand'=>'Quechua','category'=>'Sacs de couchage','description'=>'Sac de couchage léger pour les nuits d\'été en camping.','tags'=>['camping','plage','ete'],'weight_kg'=>0.6,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>4.00,'prix_vente'=>45.00,'quantite'=>10,'livraison'=>true,'frais_livraison'=>4.00],
                    ['nom'=>'Table + 4 chaises pliantes camping','brand'=>'Decathlon','category'=>'Cuisine outdoor','description'=>'Set table pliante avec 4 chaises légères, idéal camping en famille.','tags'=>['camping','famille','groupe'],'weight_kg'=>5.5,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>8.00,'prix_vente'=>95.00,'quantite'=>4,'livraison'=>false,'frais_livraison'=>null],
                    ['nom'=>'Réchaud camping gaz Campingaz Twister','brand'=>'Campingaz','category'=>'Cuisine outdoor','description'=>'Réchaud compact à visser sur cartouche gaz, idéal pour randonneurs et campeurs.','tags'=>['camping','randonnee','solo'],'weight_kg'=>0.3,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>4.00,'prix_vente'=>42.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>4.00],
                    ['nom'=>'Matelas de camping mousse Quechua','brand'=>'Quechua','category'=>'Transport & stockage','description'=>'Matelas isolant mousse léger et compact pour camping au sol.','tags'=>['camping','randonnee','bivouac'],'weight_kg'=>0.35,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>3.00,'prix_vente'=>22.00,'quantite'=>12,'livraison'=>true,'frais_livraison'=>3.00],
                ],
            ],

            // ── 7. Éclairage Outdoor Béja ─────────────────────────────────────
            [
                'email'        => 'eclairage.outdoor.beja@gmail.com',
                'nom_boutique' => 'Éclairage Outdoor Béja',
                'description'  => 'Lampes frontales, lanternes et solutions d\'éclairage pour toutes vos aventures.',
                'items' => [
                    ['nom'=>'Lampe frontale Petzl Tikka 300lm','brand'=>'Petzl','category'=>'Éclairage','description'=>'Lampe frontale légère 300 lumens, 3 modes d\'éclairage, résistante à l\'eau IPX4.','tags'=>['camping','randonnee','bivouac','grotte'],'weight_kg'=>0.08,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>3.00,'prix_vente'=>48.00,'quantite'=>15,'livraison'=>true,'frais_livraison'=>3.00],
                    ['nom'=>'Lampe frontale Petzl Actik Core 450lm','brand'=>'Petzl','category'=>'Éclairage','description'=>'Frontale rechargeable 450 lumens avec batterie Core, idéale randonnée nocturne.','tags'=>['camping','randonnee','desert','grotte'],'weight_kg'=>0.1,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>5.00,'prix_vente'=>88.00,'quantite'=>10,'livraison'=>true,'frais_livraison'=>3.00],
                    ['nom'=>'Lanterne LED Ledlenser ML6','brand'=>'Ledlenser','category'=>'Éclairage','description'=>'Lanterne LED rechargeable 750 lumens, diffusion 360°, autonomie 120h en veille.','tags'=>['camping','famille','groupe'],'weight_kg'=>0.22,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>5.00,'prix_vente'=>95.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>4.00],
                    ['nom'=>'Guirlande solaire LED 10m','brand'=>'Luci','category'=>'Éclairage','description'=>'Guirlande solaire 50 LEDs, charge via panneau solaire intégré, décoration camping.','tags'=>['camping','glamping','famille'],'weight_kg'=>0.3,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>4.00,'prix_vente'=>38.00,'quantite'=>10,'livraison'=>true,'frais_livraison'=>3.00],
                    ['nom'=>'Torche LED rechargeable Nitecore 1000lm','brand'=>'Nitecore','category'=>'Éclairage','description'=>'Torche puissante 1000 lumens, portée 150m, rechargeable USB-C.','tags'=>['camping','desert','securite'],'weight_kg'=>0.15,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>4.00,'prix_vente'=>95.00,'quantite'=>6,'livraison'=>true,'frais_livraison'=>3.00],
                ],
            ],

            // ── 8. Navigation Tunisie Gafsa — Navigation & Sécurité ───────────
            [
                'email'        => 'navigation.tunisie.gafsa@gmail.com',
                'nom_boutique' => 'Navigation Tunisie Gafsa',
                'description'  => 'GPS, boussoles et équipements de sécurité pour randonneurs et désertophiles.',
                'items' => [
                    ['nom'=>'GPS Garmin GPSMAP 66i satellite','brand'=>'Garmin','category'=>'Navigation','description'=>'GPS satellite avec messagerie Iridium, cartographie mondiale, pour expéditions extrêmes.','tags'=>['desert','montagne','expedition','securite'],'weight_kg'=>0.23,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>22.00,'prix_vente'=>585.00,'quantite'=>3,'livraison'=>true,'frais_livraison'=>8.00],
                    ['nom'=>'Montre GPS Suunto 9 Baro','brand'=>'Suunto','category'=>'Navigation','description'=>'Montre GPS multisport avec altimètre barométrique et autonomie 120h en GPS.','tags'=>['randonnee','montagne','desert','trail'],'weight_kg'=>0.08,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>20.00,'prix_vente'=>480.00,'quantite'=>3,'livraison'=>true,'frais_livraison'=>7.00],
                    ['nom'=>'Kit premiers secours Trail complet','brand'=>'Lifesystems','category'=>'Sécurité','description'=>'Trousse de premiers secours complète 50 pièces, homologuée pour randonnée et trail.','tags'=>['randonnee','desert','montagne','securite'],'weight_kg'=>0.22,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>6.00,'prix_vente'=>115.00,'quantite'=>10,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Radio balise PLB ACR ResQLink','brand'=>'ACR','category'=>'Sécurité','description'=>'Balise de détresse personnelle homologuée, alerte les secours par satellite.','tags'=>['desert','montagne','securite','expedition'],'weight_kg'=>0.13,'condition'=>'new','is_rentable'=>true,'is_sellable'=>false,'tarif_nuit'=>18.00,'prix_vente'=>null,'quantite'=>4,'livraison'=>true,'frais_livraison'=>6.00],
                    ['nom'=>'Sifflet de sécurité + boussole Fox 40','brand'=>'Fox 40','category'=>'Sécurité','description'=>'Sifflet sans bille très puissant (120dB) avec boussole intégrée, léger et résistant.','tags'=>['securite','randonnee','desert'],'weight_kg'=>0.02,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>18.00,'quantite'=>20,'livraison'=>true,'frais_livraison'=>3.00],
                ],
            ],

            // ── 9. Family Camp Mahdia — Camping familial ─────────────────────
            [
                'email'        => 'family.camp.mahdia@gmail.com',
                'nom_boutique' => 'Family Camp Mahdia',
                'description'  => 'Tout le matériel nécessaire pour des vacances camping en famille réussies.',
                'items' => [
                    ['nom'=>'Grande tente familiale Quechua 4.1 XL 6p','brand'=>'Quechua','category'=>'Tentes','description'=>'Tente 6 personnes avec 2 chambres séparées, grande hauteur intérieure, montage facile.','tags'=>['famille','camping','plage'],'weight_kg'=>8.5,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>24.00,'prix_vente'=>295.00,'quantite'=>3,'livraison'=>true,'frais_livraison'=>15.00],
                    ['nom'=>'Auvent + abri solaire 4x4m','brand'=>'Quechua','category'=>'Tentes','description'=>'Auvent de camping 4x4m avec poteaux aluminium, protège du soleil et de la pluie.','tags'=>['famille','camping','plage'],'weight_kg'=>4.2,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>12.00,'prix_vente'=>165.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>10.00],
                    ['nom'=>'Lit de camp pliant Quechua','brand'=>'Quechua','category'=>'Transport & stockage','description'=>'Lit de camp léger et solide pour adulte, confort optimisé pour camping.','tags'=>['camping','famille','confort'],'weight_kg'=>2.5,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>6.00,'prix_vente'=>78.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>8.00],
                    ['nom'=>'Set vaisselle camping inox 12p','brand'=>'Decathlon','category'=>'Cuisine outdoor','description'=>'Service complet 12 pièces en inox, assiettes creuses + plates + couverts pour famille.','tags'=>['camping','famille','groupe'],'weight_kg'=>1.2,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>5.00,'prix_vente'=>55.00,'quantite'=>6,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Table pliante camping grande Lafuma','brand'=>'Lafuma','category'=>'Cuisine outdoor','description'=>'Table de camping pliante résistante 120x60cm, pieds réglables en hauteur.','tags'=>['camping','famille','groupe'],'weight_kg'=>3.8,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>5.00,'prix_vente'=>65.00,'quantite'=>5,'livraison'=>false,'frais_livraison'=>null],
                ],
            ],

            // ── 10. Sécurité Plein Air Tunis ──────────────────────────────────
            [
                'email'        => 'securite.pleinair.tunis@gmail.com',
                'nom_boutique' => 'Sécurité Plein Air Tunis',
                'description'  => 'Kits de survie, équipements de sécurité et secourisme pour tous les aventuriers.',
                'items' => [
                    ['nom'=>'Kit survie premium 72h Mil-Tec','brand'=>'Mil-Tec','category'=>'Sécurité','description'=>'Kit de survie complet : nourriture 72h, outils, abri, eau, signaux de détresse.','tags'=>['desert','montagne','survie','securite'],'weight_kg'=>1.2,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>385.00,'quantite'=>5,'livraison'=>true,'frais_livraison'=>8.00],
                    ['nom'=>'Trousse premiers secours PRO 100p','brand'=>'Lifesystems','category'=>'Sécurité','description'=>'Trousse professionnelle 100 pièces, adaptée groupes et expéditions longues.','tags'=>['securite','groupe','expedition'],'weight_kg'=>0.45,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>8.00,'prix_vente'=>95.00,'quantite'=>8,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Couvertures de survie × 5','brand'=>'SOL','category'=>'Sécurité','description'=>'Pack 5 couvertures de survie thermorégulantes réflectrices, réutilisables.','tags'=>['securite','desert','montagne'],'weight_kg'=>0.15,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>38.00,'quantite'=>20,'livraison'=>true,'frais_livraison'=>3.00],
                    ['nom'=>'Kit purification eau Katadyn BeFree','brand'=>'Katadyn','category'=>'Sécurité','description'=>'Filtre à eau ultra-rapide 1L/min, élimine bactéries et parasites, 1000L de durée de vie.','tags'=>['desert','randonnee','survie','securite'],'weight_kg'=>0.06,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>85.00,'quantite'=>12,'livraison'=>true,'frais_livraison'=>4.00],
                    ['nom'=>'Fusées de détresse marines Pains Wessex','brand'=>'Pains Wessex','category'=>'Sécurité','description'=>'Kit 4 fusées de signalisation homologuées, portée visible 15km.','tags'=>['securite','nautique','desert','expedition'],'weight_kg'=>0.35,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>55.00,'quantite'=>10,'livraison'=>true,'frais_livraison'=>5.00],
                    ['nom'=>'Corde d\'escalade + descendeur ATC','brand'=>'Black Diamond','category'=>'Sécurité','description'=>'Corde statique 10mm 30m avec descendeur ATC, idéale escalade et rappel.','tags'=>['montagne','escalade','aventure','securite'],'weight_kg'=>2.8,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>15.00,'prix_vente'=>320.00,'quantite'=>2,'livraison'=>false,'frais_livraison'=>null],
                ],
            ],

            // ── 11. Transport Camp Kairouan ────────────────────────────────────
            [
                'email'        => 'transport.camp.kairouan@gmail.com',
                'nom_boutique' => 'Transport Camp Kairouan',
                'description'  => 'Sacs à dos de qualité et équipements de transport pour randonneurs et campeurs.',
                'items' => [
                    ['nom'=>'Sac à dos randonnée Osprey Atmos 65L','brand'=>'Osprey','category'=>'Transport & stockage','description'=>'Sac à dos 65L avec suspension anti-gravité, idéal randonnées multi-jours.','tags'=>['randonnee','montagne','trekking'],'weight_kg'=>2.0,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>12.00,'prix_vente'=>485.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>8.00],
                    ['nom'=>'Sac à dos Deuter Futura 42L','brand'=>'Deuter','category'=>'Transport & stockage','description'=>'Sac à dos 42L avec dos aéré Aircomfort, parfait pour randonnées d\'une journée à 3 jours.','tags'=>['randonnee','montagne','foret','camping'],'weight_kg'=>1.6,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>9.00,'prix_vente'=>285.00,'quantite'=>5,'livraison'=>true,'frais_livraison'=>7.00],
                    ['nom'=>'Housse pluie imperméable sac 50-70L','brand'=>'Quechua','category'=>'Transport & stockage','description'=>'Housse imperméable universelle pour sac de 50 à 70L, protège le contenu en randonnée.','tags'=>['randonnee','montagne'],'weight_kg'=>0.1,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>18.00,'quantite'=>15,'livraison'=>true,'frais_livraison'=>3.00],
                    ['nom'=>'Sac de compression Sea to Summit 20L','brand'=>'Sea to Summit','category'=>'Transport & stockage','description'=>'Sac de compression étanche 20L pour vêtements et duvet, réduit le volume de 60%.','tags'=>['randonnee','camping','desert'],'weight_kg'=>0.08,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>42.00,'quantite'=>12,'livraison'=>true,'frais_livraison'=>4.00],
                    ['nom'=>'Bidon souple Platypus 2L + filtre','brand'=>'Platypus','category'=>'Transport & stockage','description'=>'Poche à eau souple 2L avec filtre intégré, légère et compacte pour randonnée.','tags'=>['randonnee','desert','montagne'],'weight_kg'=>0.09,'condition'=>'new','is_rentable'=>false,'is_sellable'=>true,'tarif_nuit'=>null,'prix_vente'=>35.00,'quantite'=>15,'livraison'=>true,'frais_livraison'=>3.00],
                ],
            ],

            // ── 12. Glamping Supply Sousse — Glamping & Luxe outdoor ──────────
            [
                'email'        => 'glamping.supply.sousse@gmail.com',
                'nom_boutique' => 'Glamping Supply Sousse',
                'description'  => 'Équipements de glamping haut de gamme pour des séjours en plein air exceptionnels.',
                'items' => [
                    ['nom'=>'Tente Bell Tent canvas 5x5m','brand'=>'Bell Tent','category'=>'Tentes','description'=>'Grande tente bell 5m de diamètre en coton canvas, ultra confortable pour glamping.','tags'=>['glamping','camping','luxe'],'weight_kg'=>18.0,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>45.00,'prix_vente'=>790.00,'quantite'=>2,'livraison'=>true,'frais_livraison'=>25.00],
                    ['nom'=>'Lit de camp double gonflable Coleman','brand'=>'Coleman','category'=>'Transport & stockage','description'=>'Lit de camp double gonflable avec pompe intégrée, confort moelleux en plein air.','tags'=>['glamping','camping','confort'],'weight_kg'=>5.5,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>18.00,'prix_vente'=>345.00,'quantite'=>3,'livraison'=>true,'frais_livraison'=>12.00],
                    ['nom'=>'Set mobilier outdoor rotin 4 personnes','brand'=>'Lafuma','category'=>'Cuisine outdoor','description'=>'Salon outdoor table + 4 fauteuils en rotin synthétique, ambiance lounge camping.','tags'=>['glamping','luxe','camping'],'weight_kg'=>12.0,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>20.00,'prix_vente'=>495.00,'quantite'=>2,'livraison'=>true,'frais_livraison'=>20.00],
                    ['nom'=>'Kit cuisine gourmet camping Lodge','brand'=>'Lodge','category'=>'Cuisine outdoor','description'=>'Set fonte de cuisine outdoor premium : cocotte, poêle, plancha, idéal glamping.','tags'=>['glamping','gastronomie','camping'],'weight_kg'=>4.5,'condition'=>'like_new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>15.00,'prix_vente'=>325.00,'quantite'=>3,'livraison'=>true,'frais_livraison'=>10.00],
                    ['nom'=>'Tapis outdoor imperméable 3x4m','brand'=>'Bo-Camp','category'=>'Transport & stockage','description'=>'Tapis de camping extérieur 3x4m résistant eau et UV, idéal pour terrasse de tente.','tags'=>['glamping','camping','confort'],'weight_kg'=>2.8,'condition'=>'good','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>10.00,'prix_vente'=>185.00,'quantite'=>4,'livraison'=>true,'frais_livraison'=>8.00],
                    ['nom'=>'Guirlandes + décoration outdoor LED','brand'=>'Luci','category'=>'Éclairage','description'=>'Set décoration outdoor : guirlandes solaires 10m + lanternes LED pour ambiance glamping.','tags'=>['glamping','camping','decoration'],'weight_kg'=>0.6,'condition'=>'new','is_rentable'=>true,'is_sellable'=>true,'tarif_nuit'=>8.00,'prix_vente'=>95.00,'quantite'=>6,'livraison'=>true,'frais_livraison'=>5.00],
                ],
            ],
        ];
    }
}
