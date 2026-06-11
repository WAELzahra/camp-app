<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/**
 * Scans the codebase and database, generates knowledge topics,
 * embeds them via Ollama, and stores them in Qdrant.
 *
 * Run via: php artisan knowledge:index
 */
class KnowledgeIndexer
{
    private string $qdrantUrl  = 'http://localhost:6333';
    private string $ollamaUrl  = 'http://localhost:11434';
    private string $collection = 'platform_knowledge';

    /**
     * Build the complete knowledge index.
     */
    public function index(): array
    {
        $topics = $this->extractAllTopics();
        $stored = 0;

        // Ensure collection exists
        $this->createCollection();

        foreach ($topics as $id => $text) {
            $embedding = $this->embed($text);
            $this->upsertPoint($id, $text, $embedding);
            $stored++;
        }

        return [
            'success' => true,
            'topics'  => $stored,
        ];
    }

    /**
     * Extract all platform knowledge as text topics.
     * Each topic is a self-contained description of one feature/area.
     */
    private function extractAllTopics(): array
    {
        $topics = [];

        // 1. Routes grouped by controller
        $topics = array_merge($topics, $this->extractRouteTopics());
        // Front end routes
        $topics = array_merge($topics, $this->extractFrontendTopics());

        // 2. Database schema
        $topics = array_merge($topics, $this->extractDatabaseTopics());

        // 3. Configuration & business rules
        $topics = array_merge($topics, $this->extractConfigTopics());

        // 4. Models & relationships
        $topics = array_merge($topics, $this->extractModelTopics());

        // 5. Actual platform data (centres, zones, gear, policies, events)
        $topics = array_merge($topics, $this->extractDataTopics());

        return $topics;
    }

    /**
     * Group routes by controller into knowledge topics.
     */
    private function extractRouteTopics(): array
    {
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        });

        $grouped = [];

        foreach ($routes as $route) {
            $controller = $route->getAction('controller') ?? 'Closure';
            // Extract short controller name
            if (str_contains($controller, '@')) {
                [$class, $method] = explode('@', $controller);
                $name = class_basename($class);
            } else {
                $name = 'General';
            }

            $uri    = '/' . $route->uri();
            $methods = implode('|', $route->methods());
            $middleware = implode(', ', $route->gatherMiddleware());

            $grouped[$name][] = "{$methods} {$uri}" . ($middleware ? " [{$middleware}]" : '');
        }

        $topics = [];
        foreach ($grouped as $controller => $endpoints) {
            $label = $this->humanizeControllerName($controller);
            $topics["routes_{$label}"] = $this->routesToDescription($label, $endpoints);
        }

        return $topics;
    }

    /**
     * Extract database schema information.
     */
    private function extractDatabaseTopics(): array
    {
        $topics   = [];
        $tables   = DB::select('SHOW TABLES');
        $dbName   = DB::getDatabaseName();
        $tableKey = "Tables_in_{$dbName}";

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;
            $columns   = DB::select("DESCRIBE `{$tableName}`");

            $colDescriptions = [];
            foreach ($columns as $col) {
                $type = $col->Type;
                $null = $col->Null === 'YES' ? 'nullable' : 'required';
                $default = $col->Default ? " default: {$col->Default}" : '';
                $colDescriptions[] = "{$col->Field} ({$type}, {$null}{$default})";
            }

            $label = $this->humanizeTableName($tableName);
            $topics["db_{$label}"] = "Database table '{$tableName}' has columns: "
                . implode('; ', $colDescriptions) . '.';
        }

        return $topics;
    }

    /**
     * Extract configuration and business rules.
     */
    private function extractConfigTopics(): array
    {
        try {
            $zones   = DB::table('camping_zones')->where('status', 1)->where('is_closed', 0)->count();
            $centres = DB::table('camping_centres')->count();
            $gear    = DB::table('materielles')->where('status', 'up')->where('quantite_dispo', '>', 0)->count();
            $liveStats = "Live platform statistics: {$zones} active camping zones available across Tunisia. "
                . "{$centres} camping centres listed (both partner and external). "
                . "{$gear} gear items currently available to rent in the marketplace.";
        } catch (\Throwable) {
            $liveStats = "TunisiaCamp hosts camping zones, centres, and gear items across Tunisia.";
        }

        return [
            'config_live_stats'   => $liveStats,

            'config_cancellation' => "Cancellation fees: Standard camper cancellation fee is "
                . env('CANCELLATION_FEE_PERCENT', 15) . "%. Late cancellation (within 48h) fee is "
                . env('LATE_CANCELLATION_FEE_PERCENT', 50) . "%. Centre and event cancellations have "
                . "their own policies set by the provider.",

            'config_wallet' => "Wallet system: Users deposit money via Flouci or bank transfer. "
                . "Minimum withdrawal is " . env('WITHDRAWAL_MIN_AMOUNT', 20) . " TND. "
                . "Withdrawals are processed on Mondays and Thursdays. "
                . "Platform fee on transactions is " . env('PROCESSING_FEE_PERCENT', 2) . "%.",

            'config_ai_features' => "AI Features status: Trip Planner is "
                . (config('ai.features.trip_planner', true) ? 'enabled' : 'disabled')
                . ". Weather is " . (config('ai.features.weather', true) ? 'enabled' : 'disabled')
                . ". Gear Assistant is " . (config('ai.features.gear_assistant', true) ? 'enabled' : 'disabled')
                . ". Safety is " . (config('ai.features.safety', true) ? 'enabled' : 'disabled')
                . ". Group Matching is " . (config('ai.features.group_matching', false) ? 'enabled' : 'disabled') . ".",

            'config_general' => "Platform name: " . config('app.name', 'TunisiaCamp')
                . ". Environment: " . config('app.env', 'local')
                . ". URL: " . config('app.url', 'http://localhost:8000')
                . ". Support email: " . env('MAIL_SUPPORT_EMAIL', 'support@tunisiacamp.com') . ".",
        ];
    }

    /**
     * Extract model information by scanning the Models directory.
     */
    private function extractModelTopics(): array
    {
        $topics  = [];
        $modelPath = app_path('Models');
        $files   = File::glob("{$modelPath}/*.php");

        foreach ($files as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');

            if (!class_exists($className)) continue;

            $reflection = new \ReflectionClass($className);
            if ($reflection->isAbstract()) continue;

            $shortName = class_basename($className);

            // Get relationships
            $relations = [];
            foreach ($reflection->getMethods() as $method) {
                if ($method->class === $className && $method->isPublic()) {
                    $returnType = $method->getReturnType();
                    if ($returnType) {
                        $typeName = $returnType->getName();
                        if (str_contains($typeName, 'HasMany') ||
                            str_contains($typeName, 'BelongsTo') ||
                            str_contains($typeName, 'HasOne') ||
                            str_contains($typeName, 'BelongsToMany')) {
                            $relations[] = "{$method->getName()} → {$typeName}";
                        }
                    }
                }
            }

            $relText = !empty($relations)
                ? " Relationships: " . implode('; ', $relations) . '.'
                : '';

            $label = $this->humanizeTableName($shortName);
            $topics["model_{$label}"] = "Model '{$shortName}' represents a {$label}.{$relText}";
        }

        return $topics;
    }

    /**
     * Extract actual row data from non-user tables so the chatbot knows
     * specific centres, zones, gear, cancellation policies, and events.
     *
     * EXCLUDED intentionally: users, profile_campeurs, reservations,
     * wallets, transactions, payments, notifications, messages — all
     * contain personal or financial user data.
     */
    private function extractDataTopics(): array
    {
        $topics = [];

        // ── 1. Camping zones ───────────────────────────────────────────
        try {
            DB::table('camping_zones')
                ->where('status', 1)
                ->where('is_closed', 0)
                ->select(['id', 'nom', 'region', 'terrain_type', 'difficulty',
                          'is_beginner_friendly', 'activities', 'description',
                          'full_description', 'rating'])
                ->orderBy('id')
                ->each(function ($zone) use (&$topics) {
                    $activities = '';
                    if ($zone->activities) {
                        $decoded = json_decode($zone->activities, true);
                        if (is_array($decoded) && ! empty($decoded)) {
                            $activities = ' Activities: ' . implode(', ', array_slice($decoded, 0, 6)) . '.';
                        }
                    }
                    $desc    = mb_substr((string) ($zone->full_description ?? $zone->description ?? ''), 0, 250);
                    $beginner = $zone->is_beginner_friendly ? ' Suitable for beginners.' : '';
                    $rating   = $zone->rating ? " Rating: {$zone->rating}/5." : '';

                    $topics["data_zone_{$zone->id}"] =
                        "Camping zone '{$zone->nom}' is located in {$zone->region}."
                        . " Terrain: {$zone->terrain_type}. Difficulty: {$zone->difficulty}.{$beginner}{$rating}"
                        . $activities
                        . ($desc ? " {$desc}" : '');
                });
        } catch (\Throwable) {}

        // ── 2. Camping centres with profile, equipment, and services ──────
        // Equipment types match ProfileCenterEquipment::TYPE_TRANSLATIONS.
        $equipmentLabels = [
            'toilets' => 'toilets', 'drinking_water' => 'drinking water',
            'electricity' => 'electricity', 'parking' => 'parking',
            'wifi' => 'WiFi', 'showers' => 'showers', 'security' => 'security',
            'kitchen' => 'kitchen', 'bbq_area' => 'BBQ area', 'swimming_pool' => 'swimming pool',
        ];

        try {
            // One row per centre; GROUP_CONCAT aggregates equipment and service names.
            // Services use their own `name` column (custom) falling back to the
            // category name — COALESCE picks the first non-null value.
            $centres = DB::table('camping_centres as c')
                ->leftJoin('profile_centres as pc', 'pc.id', '=', 'c.profile_centre_id')
                ->leftJoin('profile_center_equipment as eq', function ($j) {
                    $j->on('eq.profile_center_id', '=', 'pc.id')->where('eq.is_available', 1);
                })
                ->leftJoin('profile_center_services as pcs', function ($j) {
                    $j->on('pcs.profile_center_id', '=', 'pc.id')->where('pcs.is_available', 1);
                })
                ->leftJoin('service_categories as sc', 'sc.id', '=', 'pcs.service_category_id')
                ->select([
                    'c.id', 'c.nom', 'c.region', 'c.adresse', 'c.description', 'c.status',
                    'pc.price_per_night', 'pc.capacite',
                    DB::raw("GROUP_CONCAT(DISTINCT eq.type ORDER BY eq.type SEPARATOR ',') as equipment_types"),
                    DB::raw("GROUP_CONCAT(DISTINCT COALESCE(pcs.name, sc.name) ORDER BY COALESCE(pcs.name, sc.name) SEPARATOR ',') as service_names"),
                ])
                ->groupBy('c.id', 'c.nom', 'c.region', 'c.adresse', 'c.description',
                          'c.status', 'pc.price_per_night', 'pc.capacite')
                ->orderBy('c.id')
                ->get();

            foreach ($centres as $centre) {
                $price    = $centre->price_per_night ? " Price: {$centre->price_per_night} TND/night." : '';
                $cap      = $centre->capacite ? " Capacity: {$centre->capacite} people." : '';
                $desc     = mb_substr((string) ($centre->description ?? ''), 0, 200);
                $bookable = $centre->status
                    ? ' Bookable directly on TunisiaCamp.'
                    : ' External centre — contact directly to reserve.';

                $equipment = '';
                if ($centre->equipment_types) {
                    $types = array_map(
                        fn ($t) => $equipmentLabels[trim($t)] ?? trim($t),
                        explode(',', $centre->equipment_types)
                    );
                    $equipment = ' On-site facilities: ' . implode(', ', $types) . '.';
                }

                $services = '';
                if ($centre->service_names) {
                    $names = array_filter(array_map('trim', explode(',', $centre->service_names)));
                    if (! empty($names)) {
                        $services = ' Services offered: ' . implode(', ', $names) . '.';
                    }
                }

                $topics["data_centre_{$centre->id}"] =
                    "Camping centre '{$centre->nom}' is in {$centre->region}"
                    . ($centre->adresse ? " at {$centre->adresse}" : '') . ".{$price}{$cap}"
                    . ($desc ? " {$desc}" : '') . $equipment . $services . $bookable;
            }
        } catch (\Throwable) {}

        // ── 3. Gear / materials available to rent ─────────────────────
        try {
            $gear = DB::table('materielles')
                ->where('status', 'up')
                ->where('quantite_dispo', '>', 0)
                ->select(['nom', 'brand', 'tarif_nuit'])
                ->orderBy('tarif_nuit')
                ->take(80)
                ->get();

            // Chunk into groups of 15 so each topic is focused and embeds well
            foreach ($gear->chunk(15) as $i => $chunk) {
                $list = $chunk->map(fn ($g) =>
                    trim($g->nom . ($g->brand ? " ({$g->brand})" : ''))
                    . " — {$g->tarif_nuit} TND/night"
                )->implode('; ');
                $topics["data_gear_chunk_{$i}"] =
                    "Gear items available to rent on TunisiaCamp marketplace: {$list}.";
            }
        } catch (\Throwable) {}

        // ── 4. Cancellation policies with tiers ────────────────────────
        try {
            $policies = DB::table('cancellation_policies')
                ->where('is_active', 1)
                ->whereNull('centre_id') // global defaults only
                ->get();

            foreach ($policies as $policy) {
                $tiers = DB::table('cancellation_policy_tiers')
                    ->where('cancellation_policy_id', $policy->id)
                    ->orderByDesc('hours_before')
                    ->get();

                $tierText = $tiers->map(fn ($t) => $t->label)->implode('; ');

                $topics["data_policy_{$policy->id}"] =
                    "Cancellation policy for {$policy->type} bookings ('{$policy->name}'): {$tierText}.";
            }
        } catch (\Throwable) {}

        // ── 5. Upcoming events ─────────────────────────────────────────
        try {
            DB::table('events')
                ->where('status', 'active')
                ->orderBy('date_debut')
                ->take(30)
                ->each(function ($event) use (&$topics) {
                    $title  = $event->titre ?? $event->title ?? $event->nom ?? 'Event';
                    $region = property_exists($event, 'region') ? $event->region : ($event->location ?? '');
                    $start  = property_exists($event, 'date_debut') ? $event->date_debut : ($event->start_date ?? null);
                    $end    = property_exists($event, 'date_fin') ? $event->date_fin : ($event->end_date ?? null);
                    $price  = $event->prix ?? $event->price ?? null;
                    $desc   = mb_substr((string) ($event->description ?? ''), 0, 200);

                    $dates   = $start ? " Dates: {$start}" . ($end ? " to {$end}" : '') . '.' : '';
                    $priceStr = $price ? " Entrance: {$price} TND." : ' Free to attend.';

                    $topics["data_event_{$event->id}"] =
                        "Camping event '{$title}'" . ($region ? " in {$region}" : '') . ".{$dates}{$priceStr}"
                        . ($desc ? " {$desc}" : '');
                });
        } catch (\Throwable) {}

        return $topics;
    }

    // ── Qdrant helpers ─────────────────────────────────────────────────

    private function createCollection(): void
    {
        Http::put("{$this->qdrantUrl}/collections/{$this->collection}", [
            'vectors' => [
                'size'     => 1024,   // bge-m3 embedding dimension
                'distance' => 'Cosine',
            ],
        ]);
    }
    /**
     * Hardcoded navigation map derived from routesConfig.tsx.
     * Using exact full paths prevents the LLM from ever seeing ambiguous bare
     * child names (e.g. "wallet") alongside full paths ("/settings/wallet"),
     * which was causing path concatenation bugs in answers.
     * Re-run knowledge:index after adding new routes.
     */
    private function extractFrontendTopics(): array
    {
        return [
            'frontend_user_navigation' =>
                "User account pages (login required). " .
                "/settings — Main profile settings page. " .
                "/settings/wallet — Wallet page: deposit money, request withdrawals, see your balance and transaction history. " .
                "/settings/reservations — Booking management page: view, cancel, and track all your booked trips. " .
                "/settings/payments — Payment history and receipts page. " .
                "/settings/services — Service configuration page (for partner venue accounts only). " .
                "/settings/favorites — Saved wishlist page: outdoor spots and venues you liked. " .
                "/settings/groups — Group management page. " .
                "/settings/events — Outdoor activity page: create and manage your own trips and group outings. " .
                "/settings/inbox — Messaging inbox page. " .
                "/settings/announcements — My posts page: camping announcements you created. " .
                "/settings/images — Profile photos page. " .
                "/settings/feedbacks — My reviews page: feedback you left on venues and gear. " .
                "/settings/shop — Supplier shop page (for supplier accounts). " .
                "/settings/add-material — List gear page: add a gear item for rent on the marketplace. " .
                "/settings/add-annonce — Create announcement page: post a new camping announcement. " .
                "/dashboard — User dashboard overview page. " .
                "/profile — View and edit your public profile page. " .
                "/notifications — Notifications page.",

            'frontend_public_navigation' =>
                "Public pages visible to everyone (no login required). " .
                "/ — Home page. " .
                "/centres — Find all registered camping venues listed on TunisiaCamp. " .
                "/centre-details/{id} — Full information page for a specific camping venue. " .
                "/zones — Explore natural outdoor areas available for camping across Tunisia. " .
                "/zones-details — Full information page for a specific outdoor area. " .
                "/events — See all upcoming outdoor activities and group trips. " .
                "/event/{id}/details — Full information page for a specific outdoor activity. " .
                "/materials — Gear marketplace: browse rental equipment for your trip. " .
                "/material-details — Full information page for a specific gear item. " .
                "/announcements — Community posts and camping announcements. " .
                "/map — Interactive map of Tunisia showing camping spots. " .
                "/devenir-partenaire — Register your venue as a TunisiaCamp partner. " .
                "/boutique/{id} — A supplier's product shop page. " .
                "/supplier-profile/{id} — Public supplier profile page. " .
                "/itineraire — Directions and route planner page. " .
                "/contact — Contact support page. " .
                "/about — About TunisiaCamp page.",
        ];
    }
    private function upsertPoint(string $id, string $text, array $vector): void
    {
        // Qdrant requires integer or UUID. Hash the string ID to a deterministic UUID.
        $uuid = $this->stringToUuid($id);

        Http::put("{$this->qdrantUrl}/collections/{$this->collection}/points", [
            'points' => [[
                'id'      => $uuid,
                'vector'  => $vector,
                'payload' => ['text' => $text, 'original_id' => $id],
            ]],
        ]);
    }
    private function stringToUuid(string $str): string
    {
        $hash = md5($str);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    private function embed(string $text): array
    {
        $response = Http::timeout(30)->post("{$this->ollamaUrl}/api/embeddings", [
            'model'  => 'bge-m3',
            'prompt' => $text,
        ]);

        return $response->json('embedding');
    }

    // ── Text helpers ───────────────────────────────────────────────────

    private function humanizeControllerName(string $name): string
    {
        return str_replace(
            ['Controller', 'Api'],
            ['', 'API'],
            $name
        );
    }

    private function humanizeTableName(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    private function routesToDescription(string $label, array $endpoints): string
    {
        $count = count($endpoints);
        $list  = implode("\n  - ", array_slice($endpoints, 0, 15));
        $more  = $count > 15 ? "\n  ... and " . ($count - 15) . " more endpoints" : '';

        return "{$label} has {$count} API endpoints:\n  - {$list}{$more}";
    }
}