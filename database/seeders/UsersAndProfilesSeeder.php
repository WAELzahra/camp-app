<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersAndProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $password = bcrypt('password');
        $now      = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('profile_guides')->truncate();
        DB::table('profile_fournisseurs')->truncate();
        DB::table('profile_groupes')->truncate();
        DB::table('profile_campeurs')->truncate();
        DB::table('balances')->truncate();
        DB::table('profiles')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $roles   = DB::table('roles')->pluck('id', 'name');
        $records = $this->records();

        // ── Insert users ──────────────────────────────────────────────────────
        $userRows = array_map(fn($r) => [
            'first_name'        => explode(' ', $r['name'], 2)[0],
            'last_name'         => explode(' ', $r['name'], 2)[1] ?? '',
            'email'             => $r['email'],
            'email_verified_at' => $now,
            'password'          => $password,
            'phone_number'      => $r['phone'],
            'ville'             => $r['ville'],
            'date_naissance'    => $r['dob'],
            'sexe'              => $r['sexe'],
            'langue'            => 'fr',
            'role_id'           => $roles[$r['role']],
            'is_active'         => 1,
            'first_login'       => 0,
            'preferences'       => isset($r['archetype'])
                                    ? json_encode(['archetype' => $r['archetype']])
                                    : null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ], $records);

        foreach (array_chunk($userRows, 50) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        $userIds = DB::table('users')->pluck('id', 'email')->all();

        // ── Insert profiles ───────────────────────────────────────────────────
        $profileRows = array_map(fn($r) => [
            'user_id'    => $userIds[$r['email']],
            'type'       => in_array($r['role'], ['campeur','guide','centre','fournisseur','groupe'])
                                ? $r['role'] : 'campeur',
            'bio'        => $r['bio'],
            'city'       => $r['ville'],
            'address'    => $r['ville'] . ', Tunisie',
            'is_public'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $records);

        foreach (array_chunk($profileRows, 50) as $chunk) {
            DB::table('profiles')->insert($chunk);
        }

        $profileByUser = DB::table('profiles')->pluck('id', 'user_id')->all();

        // ── Role-specific sub-profiles ────────────────────────────────────────
        $groupeRows      = [];
        $fournisseurRows = [];
        $guideRows       = [];

        foreach ($records as $r) {
            $uid = $userIds[$r['email']];
            $pid = $profileByUser[$uid];

            if ($r['role'] === 'groupe') {
                $groupeRows[] = [
                    'profile_id'  => $pid,
                    'nom_groupe'  => $r['nom_groupe'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            } elseif ($r['role'] === 'fournisseur') {
                $fournisseurRows[] = [
                    'profile_id'       => $pid,
                    'intervale_prix'   => $r['intervale_prix'],
                    'product_category' => $r['product_category'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            } elseif ($r['role'] === 'guide') {
                $guideRows[] = [
                    'profile_id'   => $pid,
                    'experience'   => $r['experience'],
                    'tarif'        => $r['tarif'],
                    'zone_travail' => $r['zone_travail'],
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        foreach (array_chunk($groupeRows, 50) as $chunk)      DB::table('profile_groupes')->insert($chunk);
        foreach (array_chunk($fournisseurRows, 50) as $chunk) DB::table('profile_fournisseurs')->insert($chunk);
        foreach (array_chunk($guideRows, 50) as $chunk)       DB::table('profile_guides')->insert($chunk);

        // ── Balances ──────────────────────────────────────────────────────────
        $balanceRows = array_map(fn($r) => [
            'user_id'              => $userIds[$r['email']],
            'solde_disponible'     => $r['balance'],
            'solde_en_attente'     => 0,
            'total_encaisse'       => $r['balance'],
            'total_retire'         => 0,
            'total_rembourse'      => 0,
            'dernier_mouvement_at' => $now,
            'created_at'           => $now,
            'updated_at'           => $now,
        ], $records);

        foreach (array_chunk($balanceRows, 50) as $chunk) {
            DB::table('balances')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($records) . ' users, profiles, sub-profiles, and balances inserted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DATA
    // ─────────────────────────────────────────────────────────────────────────

    private function records(): array
    {
        return array_merge(
            $this->admin(),
            $this->campeursA(),
            $this->campeursB(),
            $this->campeursC(),
            $this->campeursD(),
            $this->campeursE(),
            $this->campeursF(),
            $this->groupes(),
            $this->centres(),
            $this->fournisseurs(),
            $this->guides(),
        );
    }

    // ── Admin (1) ─────────────────────────────────────────────────────────────
    private function admin(): array
    {
        return [
            ['name'=>'Admin TunisiaCamp','email'=>'admin@tunisiacamp.tn','phone'=>'+216 71 000 000','ville'=>'Tunis','dob'=>'1985-01-01','sexe'=>'homme','role'=>'admin','bio'=>'Administrateur de la plateforme TunisiaCamp.','balance'=>0.00],
        ];
    }

    // ── Campeurs Archetype A — Budget Solo Adventurer (12) ───────────────────
    private function campeursA(): array
    {
        return [
            ['name'=>'Youssef Khelifi','email'=>'youssef.khelifi@gmail.com','phone'=>'+216 22 100 001','ville'=>'Tunis','dob'=>'1999-03-15','sexe'=>'homme','role'=>'campeur','archetype'=>'A','bio'=>'Randonnée solo et camping sauvage, toujours en quête de nouveaux sommets.','balance'=>45.00],
            ['name'=>'Rami Zaied','email'=>'rami.zaied@gmail.com','phone'=>'+216 22 100 002','ville'=>'Sfax','dob'=>'2000-07-22','sexe'=>'homme','role'=>'campeur','archetype'=>'A','bio'=>'Passionné de bivouac et d\'escalade dans les montagnes du nord.','balance'=>30.00],
            ['name'=>'Skander Dridi','email'=>'skander.dridi@gmail.com','phone'=>'+216 22 100 003','ville'=>'Sousse','dob'=>'1998-11-05','sexe'=>'homme','role'=>'campeur','archetype'=>'A','bio'=>'Explorateur des sentiers cachés, minimaliste et autonome.','balance'=>60.00],
            ['name'=>'Nidhal Chaari','email'=>'nidhal.chaari@gmail.com','phone'=>'+216 22 100 004','ville'=>'Tunis','dob'=>'2001-04-18','sexe'=>'homme','role'=>'campeur','archetype'=>'A','bio'=>'Amateur de trekking et de camping sous les étoiles.','balance'=>20.00],
            ['name'=>'Ayoub Ferchichi','email'=>'ayoub.ferchichi@outlook.com','phone'=>'+216 22 100 005','ville'=>'Sfax','dob'=>'2002-08-30','sexe'=>'homme','role'=>'campeur','archetype'=>'A','bio'=>'Jeune aventurier sfaxien, expert en camping de montagne.','balance'=>15.00],
            ['name'=>'Bilel Hamdi','email'=>'bilel.hamdi@gmail.com','phone'=>'+216 22 100 006','ville'=>'Tunis','dob'=>'1999-01-25','sexe'=>'homme','role'=>'campeur','archetype'=>'A','bio'=>'Solo hiker qui préfère les zones sauvages aux campings aménagés.','balance'=>80.00],
            ['name'=>'Wassim Bouzid','email'=>'wassim.bouzid@gmail.com','phone'=>'+216 22 100 007','ville'=>'Sousse','dob'=>'2000-05-12','sexe'=>'homme','role'=>'campeur','archetype'=>'A','bio'=>'Alpiniste amateur, toujours sac au dos et tente légère.','balance'=>35.00],
            ['name'=>'Sarra Mansouri','email'=>'sarra.mansouri@gmail.com','phone'=>'+216 22 100 008','ville'=>'Tunis','dob'=>'1999-09-08','sexe'=>'femme','role'=>'campeur','archetype'=>'A','bio'=>'Randonneuse solitaire passionnée de photographie de paysage.','balance'=>55.00],
            ['name'=>'Rim Slama','email'=>'rim.slama@outlook.com','phone'=>'+216 22 100 009','ville'=>'Sfax','dob'=>'2001-02-14','sexe'=>'femme','role'=>'campeur','archetype'=>'A','bio'=>'Exploratrice des forêts et des gorges tunisiennes.','balance'=>25.00],
            ['name'=>'Nour Nasr','email'=>'nour.nasr@gmail.com','phone'=>'+216 22 100 010','ville'=>'Tunis','dob'=>'2002-06-20','sexe'=>'femme','role'=>'campeur','archetype'=>'A','bio'=>'Passionnée de trekking et de bivouac en pleine nature.','balance'=>40.00],
            ['name'=>'Hajer Riahi','email'=>'hajer.riahi@gmail.com','phone'=>'+216 22 100 011','ville'=>'Sfax','dob'=>'2000-10-03','sexe'=>'femme','role'=>'campeur','archetype'=>'A','bio'=>'Aventurière solo, spécialiste des randonnées nocturnes.','balance'=>18.00],
            ['name'=>'Ines Ayari','email'=>'ines.ayari@gmail.com','phone'=>'+216 22 100 012','ville'=>'Sousse','dob'=>'1998-12-28','sexe'=>'femme','role'=>'campeur','archetype'=>'A','bio'=>'Grimpeuse et campeuse sauvage, toujours à la recherche de nouveaux défis.','balance'=>70.00],
        ];
    }

    // ── Campeurs Archetype B — Family Camper (15) ─────────────────────────────
    private function campeursB(): array
    {
        return [
            ['name'=>'Mohamed Trabelsi','email'=>'mohamed.trabelsi@gmail.com','phone'=>'+216 22 200 001','ville'=>'Nabeul','dob'=>'1985-04-10','sexe'=>'homme','role'=>'campeur','archetype'=>'B','bio'=>'Père de famille qui aime partager la nature avec ses enfants.','balance'=>120.00],
            ['name'=>'Khaled Jomaa','email'=>'khaled.jomaa@gmail.com','phone'=>'+216 22 200 002','ville'=>'Monastir','dob'=>'1982-08-25','sexe'=>'homme','role'=>'campeur','archetype'=>'B','bio'=>'Camping familial sur les plages de Monastir, détente garantie.','balance'=>95.00],
            ['name'=>'Ahmed Fakhfakh','email'=>'ahmed.fakhfakh@gmail.com','phone'=>'+216 22 200 003','ville'=>'Sousse','dob'=>'1986-02-14','sexe'=>'homme','role'=>'campeur','archetype'=>'B','bio'=>'Passionné de camping avec la famille en bord de mer.','balance'=>150.00],
            ['name'=>'Tarek Saidi','email'=>'tarek.saidi@gmail.com','phone'=>'+216 22 200 004','ville'=>'Nabeul','dob'=>'1988-11-30','sexe'=>'homme','role'=>'campeur','archetype'=>'B','bio'=>'Organisateur de sorties camping familiales dans la région de Nabeul.','balance'=>85.00],
            ['name'=>'Anis Majdoub','email'=>'anis.majdoub@outlook.com','phone'=>'+216 22 200 005','ville'=>'Monastir','dob'=>'1983-06-17','sexe'=>'homme','role'=>'campeur','archetype'=>'B','bio'=>'Fan de camping côtier et de baignades en famille.','balance'=>110.00],
            ['name'=>'Sofien Kilani','email'=>'sofien.kilani@gmail.com','phone'=>'+216 22 200 006','ville'=>'Sousse','dob'=>'1987-03-22','sexe'=>'homme','role'=>'campeur','archetype'=>'B','bio'=>'Camping plage avec les enfants, sécurité et confort avant tout.','balance'=>75.00],
            ['name'=>'Maher Belhaj','email'=>'maher.belhaj@gmail.com','phone'=>'+216 22 200 007','ville'=>'Nabeul','dob'=>'1984-09-05','sexe'=>'homme','role'=>'campeur','archetype'=>'B','bio'=>'Amateur de pique-nique et de promenades en nature avec la famille.','balance'=>130.00],
            ['name'=>'Fatma Trabelsi','email'=>'fatma.trabelsi@gmail.com','phone'=>'+216 22 200 008','ville'=>'Nabeul','dob'=>'1986-07-19','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Mère de famille, passionnée de camping côtier et d\'activités aquatiques.','balance'=>100.00],
            ['name'=>'Amira Jomaa','email'=>'amira.jomaa@gmail.com','phone'=>'+216 22 200 009','ville'=>'Monastir','dob'=>'1984-01-28','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Organisatrice de weekends camping pour familles sur les plages de Monastir.','balance'=>90.00],
            ['name'=>'Olfa Mansouri','email'=>'olfa.mansouri@gmail.com','phone'=>'+216 22 200 010','ville'=>'Sousse','dob'=>'1987-05-11','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Camping débutante, préfère les sites aménagés et sécurisés.','balance'=>65.00],
            ['name'=>'Manel Chaari','email'=>'manel.chaari@gmail.com','phone'=>'+216 22 200 011','ville'=>'Nabeul','dob'=>'1985-10-24','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Amateure de nature et de camping familial en bord de mer.','balance'=>145.00],
            ['name'=>'Sirine Dridi','email'=>'sirine.dridi@outlook.com','phone'=>'+216 22 200 012','ville'=>'Monastir','dob'=>'1983-12-07','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Passionnée de baignade et de pique-nique sur les plages tunisiennes.','balance'=>80.00],
            ['name'=>'Rahma Rezgui','email'=>'rahma.rezgui@gmail.com','phone'=>'+216 22 200 013','ville'=>'Sousse','dob'=>'1989-08-15','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Camping famille, nature douce et activités nautiques.','balance'=>115.00],
            ['name'=>'Emna Tlili','email'=>'emna.tlili@gmail.com','phone'=>'+216 22 200 014','ville'=>'Nabeul','dob'=>'1982-04-02','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Passionnée de sorties nature en famille et de camping confort.','balance'=>55.00],
            ['name'=>'Salma Chtioui','email'=>'salma.chtioui@gmail.com','phone'=>'+216 22 200 015','ville'=>'Monastir','dob'=>'1990-11-18','sexe'=>'femme','role'=>'campeur','archetype'=>'B','bio'=>'Maman randonneuse, fan de camping côtier et de balades en famille.','balance'=>170.00],
        ];
    }

    // ── Campeurs Archetype C — Weekend Explorer (18) ──────────────────────────
    private function campeursC(): array
    {
        return [
            ['name'=>'Zied Ben Ali','email'=>'zied.benali@gmail.com','phone'=>'+216 22 300 001','ville'=>'Tunis','dob'=>'1993-02-08','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Explorer passionné de randonnées de weekend dans les forêts du nord.','balance'=>75.00],
            ['name'=>'Ghassen Ferchichi','email'=>'ghassen.ferchichi@gmail.com','phone'=>'+216 22 300 002','ville'=>'Bizerte','dob'=>'1995-06-14','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Randonnée le weekend dans les montagnes de Bizerte et les forêts de Béja.','balance'=>90.00],
            ['name'=>'Nizar Hamdi','email'=>'nizar.hamdi@gmail.com','phone'=>'+216 22 300 003','ville'=>'Béja','dob'=>'1992-10-29','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Week-end warrior des sentiers de la Kroumirie et Mogod.','balance'=>55.00],
            ['name'=>'Chaker Nasr','email'=>'chaker.nasr@gmail.com','phone'=>'+216 22 300 004','ville'=>'Tunis','dob'=>'1994-03-17','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Explorateur du weekend, amateur de photographie et de camping intermédiaire.','balance'=>110.00],
            ['name'=>'Fares Zaied','email'=>'fares.zaied@outlook.com','phone'=>'+216 22 300 005','ville'=>'Bizerte','dob'=>'1991-08-05','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Randonneur intermédiaire, chaque weekend une nouvelle zone à découvrir.','balance'=>85.00],
            ['name'=>'Haythem Khelifi','email'=>'haythem.khelifi@gmail.com','phone'=>'+216 22 300 006','ville'=>'Béja','dob'=>'1996-12-23','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Passionné de la forêt de Aïn Draham et des randonnées en nature.','balance'=>40.00],
            ['name'=>'Sami Belhaj','email'=>'sami.belhaj@gmail.com','phone'=>'+216 22 300 007','ville'=>'Tunis','dob'=>'1993-05-01','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Explorateur du weekend entre forêts et lacs, toujours avec son appareil photo.','balance'=>125.00],
            ['name'=>'Amine Slama','email'=>'amine.slama@gmail.com','phone'=>'+216 22 300 008','ville'=>'Bizerte','dob'=>'1992-09-16','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Fan de camping et de randonnée dans le nord de la Tunisie.','balance'=>70.00],
            ['name'=>'Oussama Riahi','email'=>'oussama.riahi@gmail.com','phone'=>'+216 22 300 009','ville'=>'Tunis','dob'=>'1994-01-28','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Randonneur hebdomadaire, suit tous les clubs outdoor de Tunis.','balance'=>95.00],
            ['name'=>'Karim Jomaa','email'=>'karim.jomaa@gmail.com','phone'=>'+216 22 300 010','ville'=>'Béja','dob'=>'1995-07-09','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Amoureux des paysages de la Kroumirie, camping nature.','balance'=>60.00],
            ['name'=>'Mehdi Saidi','email'=>'mehdi.saidi@outlook.com','phone'=>'+216 22 300 011','ville'=>'Tunis','dob'=>'1991-04-22','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Explorer des chemins de traverse et campeur intermédiaire.','balance'=>140.00],
            ['name'=>'Walid Othmani','email'=>'walid.othmani@gmail.com','phone'=>'+216 22 300 012','ville'=>'Bizerte','dob'=>'1993-11-07','sexe'=>'homme','role'=>'campeur','archetype'=>'C','bio'=>'Passionné de camping nature et de randonnée en forêt.','balance'=>80.00],
            ['name'=>'Chaima Bouzid','email'=>'chaima.bouzid@gmail.com','phone'=>'+216 22 300 013','ville'=>'Tunis','dob'=>'1994-06-30','sexe'=>'femme','role'=>'campeur','archetype'=>'C','bio'=>'Exploratrice du weekend, randonnée et photographie de nature.','balance'=>65.00],
            ['name'=>'Yosra Fakhfakh','email'=>'yosra.fakhfakh@gmail.com','phone'=>'+216 22 300 014','ville'=>'Bizerte','dob'=>'1992-02-18','sexe'=>'femme','role'=>'campeur','archetype'=>'C','bio'=>'Randonneuse intermédiaire, adore les forêts de la région de Bizerte.','balance'=>100.00],
            ['name'=>'Dorsaf Ayari','email'=>'dorsaf.ayari@gmail.com','phone'=>'+216 22 300 015','ville'=>'Béja','dob'=>'1995-08-14','sexe'=>'femme','role'=>'campeur','archetype'=>'C','bio'=>'Exploratrice nature, fan de camping en forêt et de photographie.','balance'=>45.00],
            ['name'=>'Asma Kilani','email'=>'asma.kilani@gmail.com','phone'=>'+216 22 300 016','ville'=>'Tunis','dob'=>'1993-12-03','sexe'=>'femme','role'=>'campeur','archetype'=>'C','bio'=>'Randonneuse du weekend, toujours à la découverte de nouveaux sentiers.','balance'=>115.00],
            ['name'=>'Dorra Majdoub','email'=>'dorra.majdoub@outlook.com','phone'=>'+216 22 300 017','ville'=>'Bizerte','dob'=>'1994-04-25','sexe'=>'femme','role'=>'campeur','archetype'=>'C','bio'=>'Explorer enthousiaste, préfère les petits groupes et les sentiers moins fréquentés.','balance'=>55.00],
            ['name'=>'Sabrine Haddad','email'=>'sabrine.haddad@gmail.com','phone'=>'+216 22 300 018','ville'=>'Béja','dob'=>'1991-10-11','sexe'=>'femme','role'=>'campeur','archetype'=>'C','bio'=>'Randonneuse nature passionnée, chaque weekend une nouvelle aventure.','balance'=>90.00],
        ];
    }

    // ── Campeurs Archetype D — Desert Enthusiast (10) ────────────────────────
    private function campeursD(): array
    {
        return [
            ['name'=>'Omar Ghannouchi','email'=>'omar.ghannouchi@gmail.com','phone'=>'+216 22 400 001','ville'=>'Tozeur','dob'=>'1989-05-17','sexe'=>'homme','role'=>'campeur','archetype'=>'D','bio'=>'Passionné du désert tunisien, bivouac sous les étoiles du Sahara.','balance'=>50.00],
            ['name'=>'Adel Rezgui','email'=>'adel.rezgui@gmail.com','phone'=>'+216 22 400 002','ville'=>'Gafsa','dob'=>'1991-09-02','sexe'=>'homme','role'=>'campeur','archetype'=>'D','bio'=>'Expert des dunes et des pistes sahariennes en 4x4.','balance'=>35.00],
            ['name'=>'Bassem Tlili','email'=>'bassem.tlili@gmail.com','phone'=>'+216 22 400 003','ville'=>'Gabès','dob'=>'1986-01-14','sexe'=>'homme','role'=>'campeur','archetype'=>'D','bio'=>'Aventurier du sud tunisien, expert en navigation désertique.','balance'=>65.00],
            ['name'=>'Farid Amri','email'=>'farid.amri@outlook.com','phone'=>'+216 22 400 004','ville'=>'Tozeur','dob'=>'1992-06-28','sexe'=>'homme','role'=>'campeur','archetype'=>'D','bio'=>'Bivouac saharien et observation des étoiles, passionné du Sahara.','balance'=>20.00],
            ['name'=>'Ramzi Mbarki','email'=>'ramzi.mbarki@gmail.com','phone'=>'+216 22 400 005','ville'=>'Gafsa','dob'=>'1988-11-09','sexe'=>'homme','role'=>'campeur','archetype'=>'D','bio'=>'Randonneur du désert, spécialiste des Chotts et des oasis.','balance'=>45.00],
            ['name'=>'Hichem Nasr','email'=>'hichem.nasr@gmail.com','phone'=>'+216 22 400 006','ville'=>'Gabès','dob'=>'1994-03-25','sexe'=>'homme','role'=>'campeur','archetype'=>'D','bio'=>'Explorateur des canyons du sud et des ksour berbères.','balance'=>30.00],
            ['name'=>'Leila Ghannouchi','email'=>'leila.ghannouchi@gmail.com','phone'=>'+216 22 400 007','ville'=>'Tozeur','dob'=>'1990-08-18','sexe'=>'femme','role'=>'campeur','archetype'=>'D','bio'=>'Amateure de bivouac désertique et de stargazing dans le Sahara.','balance'=>55.00],
            ['name'=>'Farah Rezgui','email'=>'farah.rezgui@gmail.com','phone'=>'+216 22 400 008','ville'=>'Gafsa','dob'=>'1988-12-04','sexe'=>'femme','role'=>'campeur','archetype'=>'D','bio'=>'Passionnée des expéditions dans les zones désertiques du sud.','balance'=>25.00],
            ['name'=>'Hana Bouzid','email'=>'hana.bouzid@outlook.com','phone'=>'+216 22 400 009','ville'=>'Gabès','dob'=>'1992-04-07','sexe'=>'femme','role'=>'campeur','archetype'=>'D','bio'=>'Exploratrice du sud tunisien, fan de 4x4 et de bivouac.','balance'=>40.00],
            ['name'=>'Nesrine Amri','email'=>'nesrine.amri@gmail.com','phone'=>'+216 22 400 010','ville'=>'Tozeur','dob'=>'1986-07-22','sexe'=>'femme','role'=>'campeur','archetype'=>'D','bio'=>'Aventurière du désert, navigation et survie en zone aride.','balance'=>60.00],
        ];
    }

    // ── Campeurs Archetype E — Glamping Couple (10) ───────────────────────────
    private function campeursE(): array
    {
        return [
            ['name'=>'Bassim Bourguiba','email'=>'bassim.bourguiba@gmail.com','phone'=>'+216 22 500 001','ville'=>'Tunis','dob'=>'1985-02-14','sexe'=>'homme','role'=>'campeur','archetype'=>'E','bio'=>'Amant de la nature chic, glamping et gastronomie en plein air.','balance'=>180.00],
            ['name'=>'Saber Chtioui','email'=>'saber.chtioui@gmail.com','phone'=>'+216 22 500 002','ville'=>'Sousse','dob'=>'1983-07-09','sexe'=>'homme','role'=>'campeur','archetype'=>'E','bio'=>'Fan de glamping sur les plages de Sousse, romantique et confort.','balance'=>200.00],
            ['name'=>'Lotfi Kilani','email'=>'lotfi.kilani@gmail.com','phone'=>'+216 22 500 003','ville'=>'Nabeul','dob'=>'1987-11-20','sexe'=>'homme','role'=>'campeur','archetype'=>'E','bio'=>'Glamping avec vue sur mer, amateur de gastronomie locale.','balance'=>150.00],
            ['name'=>'Mondher Majdoub','email'=>'mondher.majdoub@outlook.com','phone'=>'+216 22 500 004','ville'=>'Tunis','dob'=>'1984-04-03','sexe'=>'homme','role'=>'campeur','archetype'=>'E','bio'=>'Luxury camping, toujours en quête du meilleur confort en plein air.','balance'=>195.00],
            ['name'=>'Nabil Haddad','email'=>'nabil.haddad@gmail.com','phone'=>'+216 22 500 005','ville'=>'Sousse','dob'=>'1989-09-16','sexe'=>'homme','role'=>'campeur','archetype'=>'E','bio'=>'Amateur de camping premium, préfère les centres bien équipés.','balance'=>160.00],
            ['name'=>'Wafa Bourguiba','email'=>'wafa.bourguiba@gmail.com','phone'=>'+216 22 500 006','ville'=>'Tunis','dob'=>'1985-05-28','sexe'=>'femme','role'=>'campeur','archetype'=>'E','bio'=>'Glamping lover, spa en plein air et couchers de soleil sur la côte.','balance'=>185.00],
            ['name'=>'Mariem Chtioui','email'=>'mariem.chtioui@gmail.com','phone'=>'+216 22 500 007','ville'=>'Sousse','dob'=>'1987-01-12','sexe'=>'femme','role'=>'campeur','archetype'=>'E','bio'=>'Fan de camping luxe avec belles vues et cuisine gastronomique.','balance'=>175.00],
            ['name'=>'Chiraz Kilani','email'=>'chiraz.kilani@gmail.com','phone'=>'+216 22 500 008','ville'=>'Nabeul','dob'=>'1983-10-07','sexe'=>'femme','role'=>'campeur','archetype'=>'E','bio'=>'Glamping romantique, toujours à la recherche des meilleures vues.','balance'=>190.00],
            ['name'=>'Mouna Jomaa','email'=>'mouna.jomaa@outlook.com','phone'=>'+216 22 500 009','ville'=>'Tunis','dob'=>'1990-06-24','sexe'=>'femme','role'=>'campeur','archetype'=>'E','bio'=>'Premium camping, confort et détente en pleine nature.','balance'=>145.00],
            ['name'=>'Samira Haddad','email'=>'samira.haddad@gmail.com','phone'=>'+216 22 500 010','ville'=>'Sousse','dob'=>'1986-12-19','sexe'=>'femme','role'=>'campeur','archetype'=>'E','bio'=>'Amateure de glamping et de cuisine en plein air haut de gamme.','balance'=>170.00],
        ];
    }

    // ── Campeurs Archetype F — Student Group Member (10) ─────────────────────
    private function campeursF(): array
    {
        return [
            ['name'=>'Aziz Ferchichi','email'=>'aziz.ferchichi@gmail.com','phone'=>'+216 22 600 001','ville'=>'Tunis','dob'=>'2002-03-10','sexe'=>'homme','role'=>'campeur','archetype'=>'F','bio'=>'Étudiant aventurier, rejoint tous les groupes de camping de Tunis.','balance'=>10.00],
            ['name'=>'Khalil Dridi','email'=>'khalil.dridi@gmail.com','phone'=>'+216 22 600 002','ville'=>'Sfax','dob'=>'2003-07-25','sexe'=>'homme','role'=>'campeur','archetype'=>'F','bio'=>'Étudiant passionné de plein air, toujours partant pour une nouvelle aventure.','balance'=>15.00],
            ['name'=>'Hamza Ben Ali','email'=>'hamza.benali@gmail.com','phone'=>'+216 22 600 003','ville'=>'Monastir','dob'=>'2001-11-08','sexe'=>'homme','role'=>'campeur','archetype'=>'F','bio'=>'Membre actif des clubs nature de Monastir, adore le camping en groupe.','balance'=>8.00],
            ['name'=>'Elyes Trabelsi','email'=>'elyes.trabelsi@outlook.com','phone'=>'+216 22 600 004','ville'=>'Tunis','dob'=>'2004-01-15','sexe'=>'homme','role'=>'campeur','archetype'=>'F','bio'=>'Jeune étudiant fan de sorties nature et d\'événements camping.','balance'=>12.00],
            ['name'=>'Yassine Slama','email'=>'yassine.slama@gmail.com','phone'=>'+216 22 600 005','ville'=>'Sfax','dob'=>'2002-05-19','sexe'=>'homme','role'=>'campeur','archetype'=>'F','bio'=>'Étudiant dynamique, suit de nombreux groupes et participe à leurs événements.','balance'=>20.00],
            ['name'=>'Amal Ferchichi','email'=>'amal.ferchichi@gmail.com','phone'=>'+216 22 600 006','ville'=>'Tunis','dob'=>'2003-09-02','sexe'=>'femme','role'=>'campeur','archetype'=>'F','bio'=>'Étudiante active dans les clubs nature, camping budget en groupe.','balance'=>5.00],
            ['name'=>'Rania Dridi','email'=>'rania.dridi@gmail.com','phone'=>'+216 22 600 007','ville'=>'Sfax','dob'=>'2002-12-27','sexe'=>'femme','role'=>'campeur','archetype'=>'F','bio'=>'Jeune exploratrice étudiante, adore les sorties organisées par les groupes.','balance'=>18.00],
            ['name'=>'Marwa Belhaj','email'=>'marwa.belhaj@gmail.com','phone'=>'+216 22 600 008','ville'=>'Monastir','dob'=>'2001-06-14','sexe'=>'femme','role'=>'campeur','archetype'=>'F','bio'=>'Étudiante passionnée de nature, toujours partante pour un camping de groupe.','balance'=>9.00],
            ['name'=>'Lina Chaari','email'=>'lina.chaari@outlook.com','phone'=>'+216 22 600 009','ville'=>'Tunis','dob'=>'2003-02-28','sexe'=>'femme','role'=>'campeur','archetype'=>'F','bio'=>'Étudiante aventurière, suit les groupes outdoor de la capitale.','balance'=>14.00],
            ['name'=>'Syrine Nasr','email'=>'syrine.nasr@gmail.com','phone'=>'+216 22 600 010','ville'=>'Sfax','dob'=>'2004-08-11','sexe'=>'femme','role'=>'campeur','archetype'=>'F','bio'=>'Jeune étudiante fan de camping et de nouvelles rencontres en nature.','balance'=>7.00],
        ];
    }

    // ── Groupes (18) ──────────────────────────────────────────────────────────
    private function groupes(): array
    {
        return [
            ['name'=>'Club Aventure Tunis','email'=>'club.aventure.tunis@gmail.com','phone'=>'+216 71 300 001','ville'=>'Tunis','dob'=>'1990-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Club de randonnée et camping basé à Tunis, plus de 500 membres actifs.','nom_groupe'=>'Club Aventure Tunis','balance'=>350.00],
            ['name'=>'Randonneurs du Nord','email'=>'randonneurs.nord@gmail.com','phone'=>'+216 72 300 002','ville'=>'Bizerte','dob'=>'1988-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Association de randonnée dans le nord de la Tunisie, forêts et montagnes.','nom_groupe'=>'Les Randonneurs du Nord','balance'=>220.00],
            ['name'=>'Désert Explorers Tozeur','email'=>'desert.explorers.tozeur@gmail.com','phone'=>'+216 76 300 003','ville'=>'Tozeur','dob'=>'1992-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Club spécialisé dans les expéditions désertiques du sud tunisien.','nom_groupe'=>'Désert Explorers Tozeur','balance'=>180.00],
            ['name'=>'Mer et Montagne Bizerte','email'=>'mer.montagne.bizerte@gmail.com','phone'=>'+216 72 300 004','ville'=>'Bizerte','dob'=>'1985-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Explorations côtières et montagnardes autour de Bizerte.','nom_groupe'=>'Mer et Montagne Bizerte','balance'=>290.00],
            ['name'=>'Camp Family Nabeul','email'=>'camp.family.nabeul@gmail.com','phone'=>'+216 72 300 005','ville'=>'Nabeul','dob'=>'1995-01-01','sexe'=>'femme','role'=>'groupe','bio'=>'Sorties camping familiales sur les plages de Cap Bon.','nom_groupe'=>'Camp Family Nabeul','balance'=>160.00],
            ['name'=>'Aventure Sfax','email'=>'aventure.sfax@gmail.com','phone'=>'+216 74 300 006','ville'=>'Sfax','dob'=>'1989-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Club outdoor de Sfax, randonnées et camping dans le centre-est tunisien.','nom_groupe'=>'Aventure Sfax','balance'=>200.00],
            ['name'=>'Trek Sud Tunisie','email'=>'trek.sud.tunisie@gmail.com','phone'=>'+216 76 300 007','ville'=>'Gafsa','dob'=>'1991-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Trekking dans le désert et les montagnes du sud tunisien.','nom_groupe'=>'Trek Sud Tunisie','balance'=>140.00],
            ['name'=>'Nature Lovers Sousse','email'=>'nature.lovers.sousse@gmail.com','phone'=>'+216 73 300 008','ville'=>'Sousse','dob'=>'1993-01-01','sexe'=>'femme','role'=>'groupe','bio'=>'Amoureux de la nature côtière et forestière autour de Sousse.','nom_groupe'=>'Nature Lovers Sousse','balance'=>175.00],
            ['name'=>'Mountain Climbers Béja','email'=>'mountain.climbers.beja@gmail.com','phone'=>'+216 78 300 009','ville'=>'Béja','dob'=>'1987-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Escalade et trekking dans les massifs montagneux de Béja.','nom_groupe'=>'Mountain Climbers Béja','balance'=>210.00],
            ['name'=>'Bivouac Sahara Club','email'=>'bivouac.sahara@gmail.com','phone'=>'+216 76 300 010','ville'=>'Tozeur','dob'=>'1994-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Club de bivouac désertique, expéditions nocturnes et stargazing.','nom_groupe'=>'Bivouac Sahara Club','balance'=>130.00],
            ['name'=>'Randonneurs Kairouan','email'=>'randonneurs.kairouan@gmail.com','phone'=>'+216 77 300 011','ville'=>'Kairouan','dob'=>'1990-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Randonnées culturelles et naturelles autour de Kairouan.','nom_groupe'=>'Randonneurs Kairouan','balance'=>165.00],
            ['name'=>'Coastal Explorers Mahdia','email'=>'coastal.explorers.mahdia@gmail.com','phone'=>'+216 73 300 012','ville'=>'Mahdia','dob'=>'1996-01-01','sexe'=>'femme','role'=>'groupe','bio'=>'Exploration côtière de Mahdia, plongée et camping plage.','nom_groupe'=>'Coastal Explorers Mahdia','balance'=>245.00],
            ['name'=>'Jeunesse Nature Monastir','email'=>'jeunesse.nature.monastir@gmail.com','phone'=>'+216 73 300 013','ville'=>'Monastir','dob'=>'1998-01-01','sexe'=>'femme','role'=>'groupe','bio'=>'Club jeunesse nature de Monastir, sorties pédagogiques et camping.','nom_groupe'=>'Jeunesse Nature Monastir','balance'=>120.00],
            ['name'=>'Sahara Trek Gabès','email'=>'sahara.trek.gabes@gmail.com','phone'=>'+216 75 300 014','ville'=>'Gabès','dob'=>'1992-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Trekking entre oasis, désert et montagne dans la région de Gabès.','nom_groupe'=>'Sahara Trek Gabès','balance'=>155.00],
            ['name'=>'Aventuriers du Kef','email'=>'aventuriers.kef@gmail.com','phone'=>'+216 78 300 015','ville'=>'Le Kef','dob'=>'1988-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Randonnées et camping dans les montagnes du nord-ouest tunisien.','nom_groupe'=>'Aventuriers du Kef','balance'=>190.00],
            ['name'=>'Ecotourisme Jendouba','email'=>'ecotourisme.jendouba@gmail.com','phone'=>'+216 78 300 016','ville'=>'Jendouba','dob'=>'1993-01-01','sexe'=>'femme','role'=>'groupe','bio'=>'Écotourisme et randonnée dans les forêts de la Kroumirie.','nom_groupe'=>'Ecotourisme Jendouba','balance'=>100.00],
            ['name'=>'Outdoor Club Siliana','email'=>'outdoor.club.siliana@gmail.com','phone'=>'+216 78 300 017','ville'=>'Siliana','dob'=>'1991-01-01','sexe'=>'homme','role'=>'groupe','bio'=>'Club outdoor de Siliana, randonnée et camping dans la Dorsale tunisienne.','nom_groupe'=>'Outdoor Club Siliana','balance'=>145.00],
            ['name'=>'Wilderness Tunis','email'=>'wilderness.tunis@gmail.com','phone'=>'+216 71 300 018','ville'=>'Tunis','dob'=>'1995-01-01','sexe'=>'femme','role'=>'groupe','bio'=>'Wilderness camping et survie en nature pour aventuriers tunisois.','nom_groupe'=>'Wilderness Tunis','balance'=>280.00],
        ];
    }

    // ── Centres (15) ──────────────────────────────────────────────────────────
    private function centres(): array
    {
        return [
            ['name'=>'Centre Cap Bon Camping','email'=>'centre.capbon@gmail.com','phone'=>'+216 72 400 001','ville'=>'Nabeul','dob'=>'1980-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Site de camping aménagé sur les rivages de Cap Bon avec accès direct à la plage.','balance'=>500.00],
            ['name'=>'Camping Hammam Bourguiba','email'=>'camping.hammam.bourguiba@gmail.com','phone'=>'+216 72 400 002','ville'=>'Bizerte','dob'=>'1978-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camping familial à Hammam Bourguiba, zone boisée avec sanitaires modernes.','balance'=>420.00],
            ['name'=>'Eco Camp Ain Draham','email'=>'ecocamp.aindraham@gmail.com','phone'=>'+216 78 400 003','ville'=>'Jendouba','dob'=>'1985-01-01','sexe'=>'femme','role'=>'centre','bio'=>'Éco-camping dans les forêts de chênes-liège d\'Aïn Draham.','balance'=>380.00],
            ['name'=>'Camp Désert Douz','email'=>'camp.desert.douz@gmail.com','phone'=>'+216 75 400 004','ville'=>'Tozeur','dob'=>'1982-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camp désertique aux portes du Sahara, tentes berbères et nuits étoilées.','balance'=>650.00],
            ['name'=>'Camping Plage Hammamet','email'=>'camping.plage.hammamet@gmail.com','phone'=>'+216 72 400 005','ville'=>'Nabeul','dob'=>'1979-01-01','sexe'=>'femme','role'=>'centre','bio'=>'Camping en bord de mer à Hammamet, plage privée et activités nautiques.','balance'=>700.00],
            ['name'=>'Centre Aventure Ichkeul','email'=>'centre.ichkeul@gmail.com','phone'=>'+216 72 400 006','ville'=>'Bizerte','dob'=>'1988-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camping nature près du parc national d\'Ichkeul, observation des oiseaux.','balance'=>290.00],
            ['name'=>'Camping Oasis Nefta','email'=>'camping.oasis.nefta@gmail.com','phone'=>'+216 76 400 007','ville'=>'Tozeur','dob'=>'1983-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camping dans l\'oasis de Nefta, palmiers et sources naturelles.','balance'=>360.00],
            ['name'=>'Centre Ksar Ghilane','email'=>'centre.ksar.ghilane@gmail.com','phone'=>'+216 75 400 008','ville'=>'Gabès','dob'=>'1986-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camp de luxe à Ksar Ghilane, sources thermales et dunes du Sahara.','balance'=>800.00],
            ['name'=>'Camp Nature Béja','email'=>'camp.nature.beja@gmail.com','phone'=>'+216 78 400 009','ville'=>'Béja','dob'=>'1990-01-01','sexe'=>'femme','role'=>'centre','bio'=>'Camping nature dans les forêts de Béja, randonnées et air pur.','balance'=>240.00],
            ['name'=>'Camping Lac Ichkeul','email'=>'camping.lac.ichkeul@gmail.com','phone'=>'+216 72 400 010','ville'=>'Bizerte','dob'=>'1984-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camping au bord du lac d\'Ichkeul, patrimoine mondial de l\'UNESCO.','balance'=>310.00],
            ['name'=>'Camp Sousse Plage','email'=>'camp.sousse.plage@gmail.com','phone'=>'+216 73 400 011','ville'=>'Sousse','dob'=>'1981-01-01','sexe'=>'femme','role'=>'centre','bio'=>'Camping bord de mer à Sousse, idéal pour familles et couples.','balance'=>450.00],
            ['name'=>'Centre Trekking Zaghouan','email'=>'centre.trekking.zaghouan@gmail.com','phone'=>'+216 72 400 012','ville'=>'Nabeul','dob'=>'1987-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camping montagne près du djebel Zaghouan, randonnées guidées.','balance'=>280.00],
            ['name'=>'Camp Tabarka Forêt','email'=>'camp.tabarka.foret@gmail.com','phone'=>'+216 78 400 013','ville'=>'Jendouba','dob'=>'1989-01-01','sexe'=>'femme','role'=>'centre','bio'=>'Camping dans les pinèdes de Tabarka, plage et forêt réunis.','balance'=>520.00],
            ['name'=>'Camp Sfax Nature','email'=>'camp.sfax.nature@gmail.com','phone'=>'+216 74 400 014','ville'=>'Sfax','dob'=>'1983-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camping nature aux alentours de Sfax, oliviers et air marin.','balance'=>180.00],
            ['name'=>'Centre Camping Kairouan','email'=>'centre.camping.kairouan@gmail.com','phone'=>'+216 77 400 015','ville'=>'Kairouan','dob'=>'1985-01-01','sexe'=>'homme','role'=>'centre','bio'=>'Camping culturel à Kairouan, entre patrimoine et nature.','balance'=>220.00],
        ];
    }

    // ── Fournisseurs (12) ─────────────────────────────────────────────────────
    private function fournisseurs(): array
    {
        return [
            ['name'=>'OutdoorTunis Pro','email'=>'outdoor.tunis.pro@gmail.com','phone'=>'+216 71 500 001','ville'=>'Tunis','dob'=>'1975-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'Spécialiste en tentes et sacs de couchage premium pour randonneurs tunisiens.','intervale_prix'=>'50-600 TND','product_category'=>'Tentes & Sacs de couchage','balance'=>750.00],
            ['name'=>'Camp Équipement Sfax','email'=>'camp.equipement.sfax@gmail.com','phone'=>'+216 74 500 002','ville'=>'Sfax','dob'=>'1980-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'Équipements de cuisine outdoor et matériel de camping pour groupes.','intervale_prix'=>'20-200 TND','product_category'=>'Cuisine outdoor','balance'=>480.00],
            ['name'=>'Côte Outdoor Sousse','email'=>'cote.outdoor.sousse@gmail.com','phone'=>'+216 73 500 003','ville'=>'Sousse','dob'=>'1978-01-01','sexe'=>'femme','role'=>'fournisseur','bio'=>'Équipements nautiques et de plage pour camping côtier en Tunisie.','intervale_prix'=>'15-400 TND','product_category'=>'Équipement côtier & nautique','balance'=>550.00],
            ['name'=>'Sahara Gear Tozeur','email'=>'sahara.gear.tozeur@gmail.com','phone'=>'+216 76 500 004','ville'=>'Tozeur','dob'=>'1977-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'Matériel spécialisé pour expéditions désertiques et bivouac saharien.','intervale_prix'=>'30-500 TND','product_category'=>'Équipement désertique','balance'=>620.00],
            ['name'=>'Trek Nord Bizerte','email'=>'trek.nord.bizerte@gmail.com','phone'=>'+216 72 500 005','ville'=>'Bizerte','dob'=>'1982-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'Vêtements techniques et équipements de randonnée pour le nord tunisien.','intervale_prix'=>'40-450 TND','product_category'=>'Vêtements & Randonnée','balance'=>390.00],
            ['name'=>'Camp Générale Monastir','email'=>'camp.generale.monastir@gmail.com','phone'=>'+216 73 500 006','ville'=>'Monastir','dob'=>'1979-01-01','sexe'=>'femme','role'=>'fournisseur','bio'=>'Location et vente de matériel de camping généraliste pour toutes les activités.','intervale_prix'=>'10-300 TND','product_category'=>'Camping général','balance'=>320.00],
            ['name'=>'Éclairage Outdoor Béja','email'=>'eclairage.outdoor.beja@gmail.com','phone'=>'+216 78 500 007','ville'=>'Béja','dob'=>'1983-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'Lampes frontales, lanternes et équipements d\'éclairage pour aventuriers.','intervale_prix'=>'15-150 TND','product_category'=>'Éclairage outdoor','balance'=>280.00],
            ['name'=>'Navigation Tunisie Gafsa','email'=>'navigation.tunisie.gafsa@gmail.com','phone'=>'+216 76 500 008','ville'=>'Gafsa','dob'=>'1976-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'GPS, boussoles et équipements de navigation pour randonneurs et désertophiles.','intervale_prix'=>'50-600 TND','product_category'=>'Navigation & Sécurité','balance'=>510.00],
            ['name'=>'Family Camp Mahdia','email'=>'family.camp.mahdia@gmail.com','phone'=>'+216 73 500 009','ville'=>'Mahdia','dob'=>'1981-01-01','sexe'=>'femme','role'=>'fournisseur','bio'=>'Location de tentes familiales et matériel de camping pour séjours côtiers.','intervale_prix'=>'20-250 TND','product_category'=>'Camping familial','balance'=>350.00],
            ['name'=>'Sécurité Plein Air Tunis','email'=>'securite.pleinair.tunis@gmail.com','phone'=>'+216 71 500 010','ville'=>'Tunis','dob'=>'1974-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'Kits de sécurité, trousses de premiers secours et équipements de survie.','intervale_prix'=>'30-400 TND','product_category'=>'Sécurité & Survie','balance'=>680.00],
            ['name'=>'Transport Camp Kairouan','email'=>'transport.camp.kairouan@gmail.com','phone'=>'+216 77 500 011','ville'=>'Kairouan','dob'=>'1978-01-01','sexe'=>'homme','role'=>'fournisseur','bio'=>'Sacs à dos, sacoches et équipements de transport pour randonneurs.','intervale_prix'=>'25-350 TND','product_category'=>'Transport & Stockage','balance'=>240.00],
            ['name'=>'Glamping Supply Sousse','email'=>'glamping.supply.sousse@gmail.com','phone'=>'+216 73 500 012','ville'=>'Sousse','dob'=>'1980-01-01','sexe'=>'femme','role'=>'fournisseur','bio'=>'Équipements de glamping haut de gamme, tentes luxe et mobilier outdoor.','intervale_prix'=>'80-800 TND','product_category'=>'Glamping & Luxe outdoor','balance'=>720.00],
        ];
    }

    // ── Guides (8) ────────────────────────────────────────────────────────────
    private function guides(): array
    {
        return [
            ['name'=>'Mounir Tlili','email'=>'mounir.tlili@gmail.com','phone'=>'+216 22 700 001','ville'=>'Tozeur','dob'=>'1982-06-10','sexe'=>'homme','role'=>'guide','bio'=>'Guide certifié du désert tunisien, 15 ans d\'expérience dans le Sahara.','experience'=>15,'tarif'=>150.00,'zone_travail'=>'Tozeur, Kébili, Douz','balance'=>300.00],
            ['name'=>'Jamel Haddad','email'=>'jamel.haddad@gmail.com','phone'=>'+216 22 700 002','ville'=>'Jendouba','dob'=>'1978-09-23','sexe'=>'homme','role'=>'guide','bio'=>'Guide de randonnée montagne, spécialisé dans les forêts de la Kroumirie.','experience'=>18,'tarif'=>120.00,'zone_travail'=>'Béja, Jendouba, Le Kef','balance'=>250.00],
            ['name'=>'Saber Ghannouchi','email'=>'saber.ghannouchi@gmail.com','phone'=>'+216 22 700 003','ville'=>'Nabeul','dob'=>'1985-02-14','sexe'=>'homme','role'=>'guide','bio'=>'Guide côtier et maritime, Cap Bon et îles Kerkennah.','experience'=>12,'tarif'=>100.00,'zone_travail'=>'Nabeul, Hammamet, Kelibia','balance'=>180.00],
            ['name'=>'Faouzi Riahi','email'=>'faouzi.riahi@gmail.com','phone'=>'+216 22 700 004','ville'=>'Bizerte','dob'=>'1980-11-05','sexe'=>'homme','role'=>'guide','bio'=>'Guide nature et ornithologie, parc national Ichkeul et lac Bizerte.','experience'=>16,'tarif'=>110.00,'zone_travail'=>'Bizerte, Mateur, Menzel Bourguiba','balance'=>220.00],
            ['name'=>'Lotfi Bouzid','email'=>'lotfi.bouzid@gmail.com','phone'=>'+216 22 700 005','ville'=>'Gafsa','dob'=>'1983-07-19','sexe'=>'homme','role'=>'guide','bio'=>'Guide de trekking sud-tunisien, entre Chott el-Jérid et monts des Aurès.','experience'=>13,'tarif'=>130.00,'zone_travail'=>'Gafsa, Tamerza, Mides','balance'=>190.00],
            ['name'=>'Slim Amri','email'=>'slim.amri@gmail.com','phone'=>'+216 22 700 006','ville'=>'Tunis','dob'=>'1987-04-02','sexe'=>'homme','role'=>'guide','bio'=>'Guide multi-activités, randonnée, via ferrata et camping dans le nord.','experience'=>10,'tarif'=>95.00,'zone_travail'=>'Tunis, Zaghouan, Siliana','balance'=>160.00],
            ['name'=>'Aicha Mansouri','email'=>'aicha.mansouri@gmail.com','phone'=>'+216 22 700 007','ville'=>'Sousse','dob'=>'1989-12-28','sexe'=>'femme','role'=>'guide','bio'=>'Guide nature certifiée, spécialisée en randonnées familiales et découverte de la faune.','experience'=>8,'tarif'=>80.00,'zone_travail'=>'Sousse, Monastir, Mahdia','balance'=>140.00],
            ['name'=>'Ramzi Ferchichi','email'=>'ramzi.ferchichi@gmail.com','phone'=>'+216 22 700 008','ville'=>'Le Kef','dob'=>'1981-08-15','sexe'=>'homme','role'=>'guide','bio'=>'Guide montagne et patrimoine, circuit des ksour et randonnées du nord-ouest.','experience'=>14,'tarif'=>115.00,'zone_travail'=>'Le Kef, Siliana, Kasserine','balance'=>200.00],
        ];
    }
}
