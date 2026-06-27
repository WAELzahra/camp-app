<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Legal documents (must run before UserSeeder so acceptances can be linked) ──
            LegalDocumentSeeder::class,

            // ── Foundation ──────────────────────────────────────────────
            RoleSeeder::class,
            ServiceCategorySeeder::class,
            PlatformSettingSeeder::class,
            CancellationPolicySeeder::class,
            UserSeeder::class,              // users + profiles (+ campeur/guide/groupe/fournisseur sub-profiles)
            ProfileCentreSeeder::class,     // profile_centres + services + equipment

            // ── Geography ───────────────────────────────────────────────
            CampingCentresSeeder::class,    // public CCV/MJ/CJ centres
            CampingZonesSeeder::class,
            TunisiaCampingSeeder::class,    // extra zones + private centres
            CircuitSeeder::class,

            // ── Shop (categories, boutique, materielles, rentals) ───────
            ShopSeeder::class,

            // ── Events & reservations ───────────────────────────────────
            EventSeeder::class,
            ReservationEventSeeder::class,
            ReservationsCentreSeeder::class,
            ReservationServiceItemSeeder::class,
            PaymentSeeder::class,           // payments + refund_requests (links reservations)
            BalanceWithdrawalSeeder::class,
            ExpenseSeeder::class,

            // ── Messaging ───────────────────────────────────────────────
            ConversationSeeder::class,
            ConversationParticipantSeeder::class,
            MessageSeeder::class,
            MessageStatusSeeder::class,
            MessageReactionSeeder::class,

            // ── Community content ───────────────────────────────────────
            AnnonceSeeder::class,           // annonces + photos + likes + comments
            FeedbackSeeder::class,          // needs materielles, zones, events
            SignalesSeeder::class,
            ReportSeeder::class,
            ContactMessageSeeder::class,
            FavorisSeeder::class,           // needs centres, zones, materielles, annonces

            // ── Notifications & misc rich data ─────────────────────────
            NotificationSeeder::class,
            RichDataSeeder::class,          // fills every remaining table
        ]);
    }
}
