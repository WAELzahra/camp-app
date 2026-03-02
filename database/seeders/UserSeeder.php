<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            // Admin user (role_id = 6)
            [
                'id' => 1,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@tunisiacamp.tn',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123456',
                'ville' => 'Tunis',
                'date_naissance' => '1990-01-01',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 6,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Center Manager (role_id = 3) - njkhouja@gmail.com
            [
                'id' => 2,
                'first_name' => 'Center',
                'last_name' => 'Manager',
                'email' => 'njkhouja@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123457',
                'ville' => 'Tunis',
                'date_naissance' => '1985-03-15',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 3,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Camper (role_id = 1) - deadxshot660@gmail.com
            [
                'id' => 3,
                'first_name' => 'DeadXShot',
                'last_name' => 'Camper',
                'email' => 'deadxshot660@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123458',
                'ville' => 'Sousse',
                'date_naissance' => '1995-05-20',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 1,
                'is_active' => 1,
                'first_login' => 1,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Group user (role_id = 2) - nejikh57@gmail.com
            [
                'id' => 4,
                'first_name' => 'Nejikh',
                'last_name' => 'Group',
                'email' => 'nejikh57@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123459',
                'ville' => 'Bizerte',
                'date_naissance' => '1988-11-10',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 2,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Additional Group Users (role_id = 2) for variety
            [
                'id' => 5,
                'first_name' => 'Forest',
                'last_name' => 'Rangers',
                'email' => 'forest.rangers@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123460',
                'ville' => 'Jendouba',
                'date_naissance' => '1992-04-25',
                'sexe' => 'female',
                'langue' => 'fr',
                'role_id' => 2,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'first_name' => 'Coastal',
                'last_name' => 'Adventures',
                'email' => 'coastal@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123461',
                'ville' => 'Mahdia',
                'date_naissance' => '1987-09-12',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 2,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Suppliers (role_id = 4)
            [
                'id' => 7,
                'first_name' => 'Camp',
                'last_name' => 'Equipment',
                'email' => 'equipment@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123462',
                'ville' => 'Sfax',
                'date_naissance' => '1980-12-05',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 4,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Guides (role_id = 5)
            [
                'id' => 8,
                'first_name' => 'Ahmed',
                'last_name' => 'Guide',
                'email' => 'ahmed.guide@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123463',
                'ville' => 'Tunis',
                'date_naissance' => '1990-06-18',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 5,
                'is_active' => 1,
                'first_login' => 0,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Additional Campers (role_id = 1)
            [
                'id' => 9,
                'first_name' => 'Sarah',
                'last_name' => 'Camper',
                'email' => 'sarah@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123464',
                'ville' => 'Bizerte',
                'date_naissance' => '1997-02-14',
                'sexe' => 'female',
                'langue' => 'fr',
                'role_id' => 1,
                'is_active' => 1,
                'first_login' => 1,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 10,
                'first_name' => 'Mike',
                'last_name' => 'Smith',
                'email' => 'mike@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '+21650123465',
                'ville' => 'Nabeul',
                'date_naissance' => '1993-11-30',
                'sexe' => 'male',
                'langue' => 'fr',
                'role_id' => 1,
                'is_active' => 1,
                'first_login' => 1,
                'nombre_signalement' => 0,
                'last_login_at' => null,
                'avatar' => null,
                'preferences' => null,
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insérer les utilisateurs
        DB::table('users')->insert($users);

        // === CRÉATION DES PROFILS POUR CHAQUE UTILISATEUR ===
        $this->createProfiles();
    }

    /**
     * Créer les profils pour chaque utilisateur
     */
    private function createProfiles()
    {
        // Mapping des types de profil par rôle - CORRIGÉ : 'admin' n'existe pas dans l'ENUM
        $profileTypeMap = [
            1 => 'campeur',      // Camper
            2 => 'groupe',       // Groupe
            3 => 'centre',       // Centre
            4 => 'fournisseur',  // Fournisseur
            5 => 'guide',        // Guide
            6 => null,           // Admin - pas de profil (ou mettre 'campeur' par défaut)
        ];

        // Récupérer tous les utilisateurs
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            // Déterminer le type de profil
            $profileType = $profileTypeMap[$user->role_id] ?? null;
            
            // Si pas de type (admin), on passe
            if (!$profileType) {
                continue;
            }

            // Créer le profil de base
            $profileId = DB::table('profiles')->insertGetId([
                'user_id' => $user->id,
                'bio' => $this->generateBio($user->first_name, $profileType),
                'cover_image' => null,
                'type' => $profileType, // Maintenant toujours une valeur valide
                'activities' => json_encode($this->generateActivities($profileType)),
                'cin_path' => $this->getDocumentPath('cin', $user->id),
                'cin_filename' => $this->getDocumentFilename('cin', $user->first_name, $user->last_name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Créer le profil spécifique selon le type
            switch ($profileType) {
                case 'guide':
                    $this->createGuideProfile($profileId, $user);
                    break;
                case 'centre':
                    $this->createCentreProfile($profileId, $user);
                    break;
                case 'groupe':
                    $this->createGroupeProfile($profileId, $user);
                    break;
                case 'fournisseur':
                    $this->createFournisseurProfile($profileId, $user);
                    break;
                case 'campeur':
                    // Rien à faire
                    break;
            }
        }
    }

    /**
     * Créer le profil guide
     */
    private function createGuideProfile($profileId, $user)
    {
        try {
            DB::table('profile_guides')->insert([
                'profile_id' => $profileId,
                'experience' => rand(2, 15),
                'tarif' => rand(50, 200),
                'zone_travail' => $this->getRandomZone(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas
        }
    }

    /**
     * Créer le profil centre
     */
    private function createCentreProfile($profileId, $user)
    {
        try {
            DB::table('profile_centres')->insert([
                'profile_id' => $profileId,
                'capacite' => rand(50, 500),
                'services_offerts' => 'Tente, électricité, eau, parking',
                'document_legal' => $this->getDocumentPath('legal', $user->id),
                'document_legal_type' => $this->getRandomLegalDocumentType(),
                'document_legal_expiration' => $this->getRandomExpirationDate(),
                'document_legal_filename' => $this->getDocumentFilename('legal', $user->first_name, $user->last_name),
                'disponibilite' => (bool)rand(0, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas
        }
    }

    /**
     * Créer le profil groupe
     */
    private function createGroupeProfile($profileId, $user)
    {
        try {
            DB::table('profile_groupes')->insert([
                'profile_id' => $profileId,
                'nom_groupe' => $this->generateGroupName($user),
                'cin_responsable' => $this->generateCinNumber(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas
        }
    }

    /**
     * Créer le profil fournisseur
     */
    private function createFournisseurProfile($profileId, $user)
    {
        try {
            DB::table('profile_fournisseurs')->insert([
                'profile_id' => $profileId,
                'intervale_prix' => rand(100, 1000) . ' - ' . rand(1000, 5000) . ' TND',
                'product_category' => $this->getRandomProductCategory(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas
        }
    }

    /**
     * Générer un chemin de document simulé
     */
    private function getDocumentPath($type, $userId)
    {
        $imageId = ($userId * 10) + rand(1, 100);
        return 'https://picsum.photos/id/' . $imageId . '/200/300';
    }

    /**
     * Générer un nom de fichier
     */
    private function getDocumentFilename($type, $firstName, $lastName)
    {
        $extensions = ['pdf', 'jpg', 'png'];
        $ext = $extensions[array_rand($extensions)];
        return strtolower($type . '_' . $firstName . '_' . $lastName . '.' . $ext);
    }

    /**
     * Générer une bio
     */
    private function generateBio($name, $type)
    {
        $bios = [
            'campeur' => 'Passionné de camping et de nature, j\'aime explorer les plus beaux paysages de Tunisie.',
            'guide' => 'Guide professionnel avec plusieurs années d\'expérience dans l\'organisation de randonnées.',
            'centre' => 'Centre de camping offrant des services de qualité pour des séjours inoubliables.',
            'groupe' => 'Groupe de passionnés organisant des sorties camping régulières.',
            'fournisseur' => 'Fournisseur d\'équipements de camping de haute qualité.',
        ];
        
        return $bios[$type] ?? 'Membre de TunisiaCamp';
    }

    /**
     * Générer des activités
     */
    private function generateActivities($type)
    {
        $activities = [
            'campeur' => ['camping', 'randonnée', 'photographie'],
            'guide' => ['randonnée', 'escalade', 'orientation'],
            'centre' => ['accueil', 'restauration', 'animation'],
            'groupe' => ['sorties', 'événements', 'rencontres'],
            'fournisseur' => ['vente', 'location', 'conseil'],
        ];
        
        return $activities[$type] ?? ['camping'];
    }

    /**
     * Générer un nom de groupe
     */
    private function generateGroupName($user)
    {
        $names = ['Explorateurs', 'Aventuriers', 'Nomades', 'Randonneurs', 'Campeurs'];
        $suffix = $names[array_rand($names)];
        return $user->first_name . ' ' . $suffix;
    }

    /**
     * Générer un numéro CIN
     */
    private function generateCinNumber()
    {
        return rand(10000000, 99999999);
    }

    /**
     * Zone de travail aléatoire pour guide
     */
    private function getRandomZone()
    {
        $zones = ['Nord', 'Sud', 'Centre', 'Tunis', 'Sousse', 'Bizerte', 'Djerba'];
        return $zones[array_rand($zones)];
    }

    /**
     * Type de document légal aléatoire
     */
    private function getRandomLegalDocumentType()
    {
        $types = ['registre_commerce', 'licence', 'agrement', 'patente'];
        return $types[array_rand($types)];
    }

    /**
     * Catégorie de produit aléatoire pour fournisseur
     */
    private function getRandomProductCategory()
    {
        $categories = ['tentes', 'sacs', 'chaussures', 'accessoires', 'réchauds'];
        return $categories[array_rand($categories)];
    }

    /**
     * Date d'expiration aléatoire
     */
    private function getRandomExpirationDate()
    {
        $options = [
            Carbon::now()->addMonths(rand(1, 24))->format('Y-m-d'),
            Carbon::now()->subMonths(rand(1, 12))->format('Y-m-d'),
            null,
        ];
        return $options[array_rand($options)];
    }
}