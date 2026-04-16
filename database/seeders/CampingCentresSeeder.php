<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CampingCentresSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $centres = [
            // Béja
            ['nom' => 'CCV Zoueraa', 'type' => 'CCV', 'description' => 'Camping balnéaire et forestier', 'adresse' => 'Nefza, Béja', 'lat' => 37.0589, 'lng' => 8.9776, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'Complexe de Jeunes de Béja', 'type' => 'CJ', 'description' => 'Complexe forestier', 'adresse' => 'Béja', 'lat' => 36.7256, 'lng' => 9.1817, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            
            // Bizerte
            ['nom' => 'CCV Rimel', 'type' => 'CCV', 'description' => 'Camping balnéaire', 'adresse' => 'Bizerte', 'lat' => 37.2744, 'lng' => 9.8739, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Chatt Mami', 'type' => 'CCV', 'description' => 'Camping balnéaire', 'adresse' => 'Bizerte Nord', 'lat' => 37.2900, 'lng' => 9.8600, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ 15 Octobre de Bizerte', 'type' => 'MJ', 'description' => 'Maison de jeunes', 'adresse' => 'Bizerte Centre', 'lat' => 37.2760, 'lng' => 9.8640, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Gabès
            ['nom' => 'CJ Saniet El Bey', 'type' => 'CJ', 'description' => 'Complexe saharien', 'adresse' => 'Gabès', 'lat' => 33.8814, 'lng' => 10.0982, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Zarat', 'type' => 'CCV', 'description' => 'Camping saharien', 'adresse' => 'Zarat, Gabès', 'lat' => 33.6800, 'lng' => 10.3500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Gafsa
            ['nom' => 'CJ Gafsa', 'type' => 'CJ', 'description' => 'Complexe saharien', 'adresse' => 'Gafsa', 'lat' => 34.4250, 'lng' => 8.7842, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Metlaoui', 'type' => 'CJ', 'description' => 'Complexe saharien', 'adresse' => 'Metlaoui', 'lat' => 34.3208, 'lng' => 8.4014, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Jendouba
            ['nom' => 'CCV Maghrébin Ain Soltan', 'type' => 'CCV', 'description' => 'Camping forestier', 'adresse' => 'Aïn Soltan', 'lat' => 36.5167, 'lng' => 8.4667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Beni Mtir', 'type' => 'CCV', 'description' => 'Camping forestier', 'adresse' => 'Beni Mtir', 'lat' => 36.7400, 'lng' => 8.7300, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Merij', 'type' => 'CCV', 'description' => 'Camping forestier', 'adresse' => 'Merij, Jendouba', 'lat' => 36.6000, 'lng' => 8.5000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Kairouan
            ['nom' => 'CJ Kairouan', 'type' => 'CJ', 'description' => 'Complexe culturel', 'adresse' => 'Kairouan', 'lat' => 35.6781, 'lng' => 10.0963, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Avenue de Fes', 'type' => 'MJ', 'description' => 'Maison de jeunes', 'adresse' => 'Kairouan', 'lat' => 35.6750, 'lng' => 10.1000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Hajeb Ayoun', 'type' => 'MJ', 'description' => 'Maison de jeunes rurale', 'adresse' => 'Hajeb Ayoun', 'lat' => 35.5500, 'lng' => 9.8833, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Nasrallah', 'type' => 'MJ', 'description' => 'Maison de jeunes rurale', 'adresse' => 'Nasrallah', 'lat' => 35.3500, 'lng' => 9.8167, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Kasserine
            ['nom' => 'CCV Chambi', 'type' => 'CCV', 'description' => 'Camping de montagne', 'adresse' => 'Parc National Chambi', 'lat' => 35.1833, 'lng' => 8.6833, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Haïdra', 'type' => 'MJ', 'description' => 'Maison de jeunes archéologique', 'adresse' => 'Haïdra', 'lat' => 35.5667, 'lng' => 8.4500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Kasserine', 'type' => 'CJ', 'description' => 'Complexe forestier', 'adresse' => 'Kasserine', 'lat' => 35.1667, 'lng' => 8.8333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Sbeïtla', 'type' => 'MJ', 'description' => 'Maison de jeunes archéologique', 'adresse' => 'Sbeïtla', 'lat' => 35.2333, 'lng' => 9.1167, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Ain Selsla', 'type' => 'CCV', 'description' => 'Camping forestier', 'adresse' => 'Aïn Selsla', 'lat' => 35.2000, 'lng' => 8.7000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Kébili
            ['nom' => 'CCV Douz', 'type' => 'CCV', 'description' => 'Camping saharien', 'adresse' => 'Douz', 'lat' => 33.4500, 'lng' => 9.0167, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Kébili', 'type' => 'MJ', 'description' => 'Maison de jeunes saharienne', 'adresse' => 'Kébili', 'lat' => 33.7050, 'lng' => 8.9650, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Le Kef
            ['nom' => 'MJ Dahmani', 'type' => 'MJ', 'description' => 'Maison de jeunes rurale', 'adresse' => 'Dahmani', 'lat' => 35.9500, 'lng' => 8.8333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Le Kef', 'type' => 'CJ', 'description' => 'Complexe culturel et forestier', 'adresse' => 'Le Kef', 'lat' => 36.1667, 'lng' => 8.7000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Sakiet Sidi Youssef', 'type' => 'CCV', 'description' => 'Camping rural frontalier', 'adresse' => 'Sakiet Sidi Youssef', 'lat' => 36.2167, 'lng' => 8.3500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Mahdia
            ['nom' => 'CCV Douira / Chebba', 'type' => 'CCV', 'description' => 'Camping balnéaire', 'adresse' => 'Chebba', 'lat' => 35.2333, 'lng' => 11.1167, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Mahdia', 'type' => 'MJ', 'description' => 'Maison de jeunes balnéaire', 'adresse' => 'Mahdia', 'lat' => 35.5000, 'lng' => 11.0667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Médenine
            ['nom' => 'Centre d\'Accueil Aghir', 'type' => 'CAJ', 'description' => 'Centre balnéaire à Djerba', 'adresse' => 'Aghir, Djerba', 'lat' => 33.7500, 'lng' => 11.0333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Houmet Essouk', 'type' => 'CJ', 'description' => 'Complexe balnéaire et culturel', 'adresse' => 'Houmt Souk, Djerba', 'lat' => 33.8667, 'lng' => 10.8500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Marsa Leksiba', 'type' => 'CCV', 'description' => 'Camping balnéaire', 'adresse' => 'Marsa Leksiba', 'lat' => 33.5000, 'lng' => 11.1000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Médenine', 'type' => 'CJ', 'description' => 'Complexe saharien et culturel', 'adresse' => 'Médenine', 'lat' => 33.3500, 'lng' => 10.5000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Midoun', 'type' => 'MJ', 'description' => 'Maison de jeunes balnéaire', 'adresse' => 'Midoun, Djerba', 'lat' => 33.8000, 'lng' => 11.0500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Zarzis', 'type' => 'MJ', 'description' => 'Maison de jeunes balnéaire', 'adresse' => 'Zarzis', 'lat' => 33.5000, 'lng' => 11.1167, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Nabeul
            ['nom' => 'CCV Rojaa', 'type' => 'CCV', 'description' => 'Camping balnéaire', 'adresse' => 'Nabeul, Cap Bon', 'lat' => 36.4500, 'lng' => 10.7333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Hammamet', 'type' => 'CJ', 'description' => 'Complexe balnéaire', 'adresse' => 'Hammamet', 'lat' => 36.4000, 'lng' => 10.6167, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Kélibia', 'type' => 'MJ', 'description' => 'Maison de jeunes balnéaire', 'adresse' => 'Kélibia', 'lat' => 36.8500, 'lng' => 11.0833, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Menzel Temime', 'type' => 'MJ', 'description' => 'Maison de jeunes balnéaire', 'adresse' => 'Menzel Temime', 'lat' => 36.7833, 'lng' => 10.9833, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Nabeul', 'type' => 'CJ', 'description' => 'Complexe balnéaire', 'adresse' => 'Nabeul', 'lat' => 36.4500, 'lng' => 10.7333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Korba', 'type' => 'MJ', 'description' => 'Maison de jeunes balnéaire', 'adresse' => 'Korba', 'lat' => 36.5667, 'lng' => 10.8667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Sfax
            ['nom' => 'CJ Route Aéroport Sfax', 'type' => 'CJ', 'description' => 'Complexe urbain', 'adresse' => 'Sfax', 'lat' => 34.7333, 'lng' => 10.7667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'Maison de Jeunes Sfax', 'type' => 'MJ', 'description' => 'Maison de jeunes urbaine', 'adresse' => 'Sfax Centre', 'lat' => 34.7333, 'lng' => 10.7667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Ramla – Kerkennah', 'type' => 'CCV', 'description' => 'Camping insulaire', 'adresse' => 'Îles Kerkennah', 'lat' => 34.7000, 'lng' => 11.1667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Sidi Bouzid
            ['nom' => 'Complexe Jeunes 17 Décembre', 'type' => 'CJ', 'description' => 'Complexe rural', 'adresse' => 'Sidi Bouzid', 'lat' => 35.0333, 'lng' => 9.4833, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Siliana
            ['nom' => 'CCV Ain Bousaadia', 'type' => 'CCV', 'description' => 'Camping forestier et thermal', 'adresse' => 'Aïn Bousaadia', 'lat' => 36.0000, 'lng' => 9.3667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Kesra', 'type' => 'MJ', 'description' => 'Maison de jeunes berbère', 'adresse' => 'Kesra', 'lat' => 35.8167, 'lng' => 9.3667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Makthar', 'type' => 'MJ', 'description' => 'Maison de jeunes archéologique', 'adresse' => 'Makthar', 'lat' => 35.8500, 'lng' => 9.2000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Siliana', 'type' => 'CJ', 'description' => 'Complexe rural', 'adresse' => 'Siliana', 'lat' => 36.0833, 'lng' => 9.3667, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Monastir
            ['nom' => 'CJ Ali Skhiri', 'type' => 'CJ', 'description' => 'Complexe balnéaire', 'adresse' => 'Monastir', 'lat' => 35.7667, 'lng' => 10.8167, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Bekalta', 'type' => 'CCV', 'description' => 'Camping balnéaire', 'adresse' => 'Bekalta', 'lat' => 35.6167, 'lng' => 11.0000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Sousse
            ['nom' => 'CJ Hergla', 'type' => 'CJ', 'description' => 'Complexe balnéaire', 'adresse' => 'Hergla', 'lat' => 36.0333, 'lng' => 10.5000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Sahloul', 'type' => 'CJ', 'description' => 'Complexe balnéaire', 'adresse' => 'Sahloul, Sousse', 'lat' => 35.8167, 'lng' => 10.6333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Sousse', 'type' => 'CJ', 'description' => 'Complexe balnéaire et culturel', 'adresse' => 'Sousse', 'lat' => 35.8333, 'lng' => 10.6333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CCV Salloum', 'type' => 'CCV', 'description' => 'Camping balnéaire', 'adresse' => 'Salloum', 'lat' => 35.9000, 'lng' => 10.5500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Tataouine
            ['nom' => 'MJ Dh\'hiba', 'type' => 'MJ', 'description' => 'Maison de jeunes saharienne', 'adresse' => 'Dhehiba', 'lat' => 32.0167, 'lng' => 10.7000, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Maghrébins Tataouine', 'type' => 'CJ', 'description' => 'Complexe saharien et culturel', 'adresse' => 'Tataouine', 'lat' => 32.9333, 'lng' => 10.4500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Tozeur
            ['nom' => 'MJ Nefta', 'type' => 'MJ', 'description' => 'Maison de jeunes saharienne', 'adresse' => 'Nefta', 'lat' => 33.8833, 'lng' => 7.8833, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'CJ Tozeur', 'type' => 'CJ', 'description' => 'Complexe saharien', 'adresse' => 'Tozeur', 'lat' => 33.9167, 'lng' => 8.1333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['nom' => 'MJ Tozeur (Route Elhamma)', 'type' => 'MJ', 'description' => 'Maison de jeunes saharienne', 'adresse' => 'Tozeur', 'lat' => 33.9167, 'lng' => 8.1333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Tunis
            ['nom' => 'CJ La Marsa', 'type' => 'CJ', 'description' => 'Complexe balnéaire et urbain', 'adresse' => 'La Marsa', 'lat' => 36.8833, 'lng' => 10.3333, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Ben Arous
            ['nom' => 'CJ Maghrébin Radès', 'type' => 'CJ', 'description' => 'Complexe urbain', 'adresse' => 'Radès', 'lat' => 36.7667, 'lng' => 10.2833, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],

            // Zaghouan
            ['nom' => 'CJ Zaghouan', 'type' => 'CJ', 'description' => 'Complexe forestier et archéologique', 'adresse' => 'Zaghouan', 'lat' => 36.4000, 'lng' => 10.1500, 'image' => null, 'status' => 1, 'validation_status' => 'approved', 'user_id' => null, 'profile_centre_id' => null, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('camping_centres')->insert($centres);
    }
}