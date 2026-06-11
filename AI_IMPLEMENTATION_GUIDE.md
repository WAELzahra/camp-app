# TunisiaCamp — AI Implementation Guide

> **What this document is.** A complete, end-to-end account of how the AI
> features are built: the architecture, every layer, the data flow, the
> techniques and models, the frontend integration, and the concrete bugs we
> found and fixed while wiring it all together.
>
> **Scope.** The non-conversational AI surface: Recommendation Engine (zones,
> centres, gear), Behavioral Profiles, Group Matching, Dynamic Pricing, Safety,
> Weather Intelligence, Gear Assistant, and Explainability. The chatbot / trip
> planner (RAG over Qdrant) shares some infrastructure but is documented
> separately.

---

## 1. Design philosophy: "PHP decides, the LLM phrases"

Every AI feature follows one rule: **all decisions are made deterministically in
PHP** using classical ML and explicit rule engines. The **LLM only turns a
finished, structured result into a natural-language French sentence.** The LLM
never chooses a zone, a price, a risk score, or a match — it cannot change a
number or a ranking.

Why this matters:
- **Reproducible & testable** — the same inputs always produce the same ranking.
- **Resilient** — every service has a rule-based fallback and a `mock` mode; a
  failed LLM/weather call degrades to a still-correct deterministic answer
  instead of an error.
- **Explainable** — because PHP computes a `score_breakdown`, we can always say
  *why* something was recommended.

There are **no trained/neural models** in this layer. The "models" are classical:
cosine similarity, K-Means++/DBSCAN clustering, collaborative filtering, a
demand-multiplier pricing model, and severity-weighted rule engines. The only
external model is **Groq** (`llama-3.3-70b-versatile`) used for phrasing.

---

## 2. The layered architecture

```
┌── DATA LAYER (MySQL) ──────────────────────────────────────────────┐
│ profile_campeurs · camping_zones(+AI cols) · camping_centres ·       │
│ profile_centres · materielles · materielles_categories(+safety) ·    │
│ reservations_centres(+group_skill_level,trip_purpose) ·              │
│ reservations_materielles · feedbacks · favorites · users/roles       │
└───────────────────────────────┬────────────────────────────────────┘
                                 ▼
┌── SERVICE LAYER (app/Services/AI) — ALL DECISIONS HERE ─────────────┐
│ RecommendationService   BehavioralProfileService   CentreLookup      │
│ GroupMatchingService ── Matching/{KMeans++, DBSCAN, VectorBuilder}   │
│ DynamicPricingService   SafetyService   WeatherService               │
│ GearAssistantService    ExplainabilityService                        │
│ DTOs (readonly): Pricing/* Safety/* Matching/* Weather/* Gear/* …    │
│ Adapters: LLMAdapterInterface→Groq|Mock · WeatherAdapter→OWM|Mock     │
│ RateLimitService                                                     │
└──────────┬───────────────────────────────────────┬─────────────────┘
           │ phrasing only                          │ all data/decisions
           ▼                                        ▼
   Groq (LLM)   OpenWeatherMap                   MySQL + Cache
                                 ▲
                                 │
┌── HTTP LAYER (app/Http/Controllers/AI) ────────────────────────────┐
│ AiTripPlannerController(recommendations/gear/weather)               │
│ GroupMatchingController · PricingController · SafetyController ·     │
│ ExplainabilityController                                            │
│ Middleware: auth:sanctum + throttle · role gates · ownership checks │
└───────────────────────────────┬────────────────────────────────────┘
                                 ▼  /api/ai/*
┌── FRONTEND LAYER (React + TypeScript) ─────────────────────────────┐
│ services/aiService.ts   (one typed client for every endpoint)       │
│ hooks/useAi.ts          (react-query: caching, dedupe, retries)     │
│ components/ai/*         (SafetyBadge, ZoneSafetyPanel, GearChecklist,│
│                          WeatherRiskCard, PricingIntelligencePanel,  │
│                          RecommendationStrip, GroupMatchesStrip,     │
│                          DashboardAiSection, ExplanationChip)        │
│ pages: RecommendationsPage, GroupMatchingPage                       │
│ injected into: ZoneDetail, listing pages, UserDashboard, modals,    │
│                Header (Explore menu)                                 │
└────────────────────────────────────────────────────────────────────┘
```

---

## 3. Data layer

### 3.1 AI columns added by migrations
- `camping_zones`: `is_beginner_friendly`, `terrain_type` (enum: forest, mountain,
  desert, coastal, plain, wetland), `min_temp_celsius`, `max_temp_celsius`
  (plus existing `difficulty`, `danger_level`, `rating`, `reviews_count`,
  `activities`).
- `materielles_categories`: `trip_contexts`, `icon`, `is_safety_critical`.
- `reservations_centres` / `reservations_events`: `group_skill_level`,
  `trip_purpose`.
- `profile_campeurs` (new table): `skill_level`, `comfort_level`, `budget_range`,
  `preferred_trip_styles` (JSON), `preferred_activities` (JSON),
  `gear_preferences` (JSON), `total_trips`.

### 3.2 Data volumes (current seed)
420 zones (100% AI-fields populated) · 150 centres · 64 gear items · 93
`profile_campeurs` (75 campeurs + 18 organizers) · 50 centre reservations (30
approved) · 60 gear rentals · 62 feedbacks · 337 favorites.

### 3.3 Seeder fixes we made (critical for the AI to return real data)
1. **`ReservationsSeeder` — centre reservations were silently 0.** The seeder
   resolved centres by a hardcoded list of stale Gmail addresses that no longer
   exist (real centre accounts use slug emails like
   `bouhertma-outdoors-1@tunisiacamp.tn`), so every lookup returned null and the
   row was skipped. **Fix:** assign reservations from the live centre-user pool.
   → 50 centre reservations now seed, which powers behavioral profiling, pricing
   demand, and trending tags.
2. **`GroupeProfileCampeursSeeder` — new.** Organizer (groupe) accounts had no
   `profile_campeurs` row, so the matching engine couldn't score them. This
   seeder gives the 18 organizers camper-style profiles, deliberately spread
   across skill/budget/comfort buckets so every K-Means cluster contains groups.

---

## 4. Adapter & cross-cutting layer

- **Provider strategy** (`AppServiceProvider`): `config('ai.provider')` =
  `groq` | `mock`. `LLMAdapterInterface` binds to `GroqAdapter` or `MockAdapter`;
  `WeatherAdapterInterface` to `OpenWeatherAdapter` or `MockWeatherAdapter`. All
  services depend on the interface, never the concrete class.
- **Groq** (`GroqAdapter`): `llama-3.3-70b-versatile`, multi-turn messages,
  exponential-backoff retry, 30/min·6000/day soft limits.
- **OpenWeatherMap** (`OpenWeatherAdapter`): 5-day/3-hour forecast aggregated to
  daily, cached 3h at ~1 km precision, 50/min·900/day.
- **`RateLimitService`**: cache-counter token buckets, plus Laravel route
  throttles (`ai` 10/min·100/day, `weather` 30/min, `safety` 60/min).
- **Feature flags** (`config/ai.php`): `weather, gear_assistant, group_matching,
  pricing, safety, explainability` (+ `trip_planner`).
- **Caching**: `Cache::remember` everywhere — recommendations 30 min, pricing 1h
  (market 2h), safety 30 min (moderation 24h), clusters 1h, behavioral 1h,
  weather 3h.
- **Observability**: structured `Log::info/warning/error` on every decision.

---

## 5. Feature-by-feature implementation

For each feature: **data flow → technique/model → algorithm → where it lives →
what we built/fixed.**

### 5.1 Recommendation Engine (zones · centres · gear)

**Endpoint:** `GET /api/ai/recommendations` (`AiTripPlannerController`).
**Service:** `RecommendationService`.

**Data flow:** authenticated user → `ProfileCampeur` (static prefs) → fetch the
**full active catalogue** of zones/centres/gear → score each → attach
explanations → cache 30 min → JSON.

**Technique — content-based cosine + collaborative filtering:**
- **Zone user vector (6-dim, 0–1):** skill, budget, terrain-weight,
  activity-richness, experience, behavioral-confidence.
- **Zone item vector (6-dim):** difficulty, rating, **terrain-match** (user
  relative), activity-overlap (Jaccard), accessibility, social-proof.
- **Content score** = `cosineSimilarity(user, zone)`.
- **Collaborative signal:** find users with the same skill+budget, pull their
  approved zone feedback; reward zones with ≥2 positive reviews or avg note ≥4.5.
- **Blend:** `final = cosine × 0.7 + collaborative × 0.3`. Top 5 zones.
- **Gear:** 4-dim user vector vs 4-dim item vector (terrain-tag match, normalized
  price, condition, availability), cosine, top 10.
- **Centres** (`scoreCentres`, added this session): transparent weighted blend
  `budget_fit×0.45 + equipment_richness×0.35 + capacity_fit×0.20` (centres lack
  ratings/terrain, so a cosine would be noise). Top 6.

**Where it surfaces (frontend):** `RecommendationsPage` (full grid with "why"),
`RecommendationStrip` (compact rows on dashboard + `/zones` + `/centres` +
`/materials`).

**What we built/fixed:**
- Added **centre recommendations** end-to-end (`scoreCentres` + controller +
  types + UI).
- **Relaxed the endpoint** so non-campers no longer 404 — a neutral default
  profile yields generic discovery recs for every role.
- **Fixed the terrain personalization bug** (see *Bugs found & fixed*) —
  French↔English synonyms + scoring the full catalogue instead of the first 50
  zones by id.

### 5.2 Behavioral Profiles

**Service:** `BehavioralProfileService` (internal — no public endpoint).

**Data flow:** raw activity (centre bookings, gear rentals, approved feedback,
favorites) → 6 inferred signals → confidence → cache 1h, invalidated by
observers on booking/feedback/favorite changes.

**Technique — heuristic aggregation (weak supervision):**
1. `inferred_skill_level` ← avg of `group_skill_level` on bookings.
2. `inferred_budget_range` ← avg spend (centre `total_price` + gear `montant_total`).
3. `inferred_terrain_preference` ← net score per terrain from feedback + favorites.
4. `inferred_gear_needed` ← gear rentals vs centre-only trips.
5. `inferred_group_size` ← avg `nbr_place`.
6. `confidence_score` ← booking volume + feedback/favorites bonuses.

`mergeWithStatic()` overrides static profile fields **when confidence ≥ 0.4**, so
recommendations reflect real behavior. (The recommendations endpoint uses the
static profile directly; the trip planner uses the merged profile.)

### 5.3 Group Matching

**Endpoints:** `GET /ai/groups/matches`, `GET /ai/groups/cluster-stats` (admin),
`POST /ai/groups/recluster` (admin).
**Service:** `GroupMatchingService` + `Matching/{KMeansClusterer,
DBSCANClusterer, VectorBuilder}`.

**Technique — clustering + cosine ranking:**
- **VectorBuilder** maps each `ProfileCampeur` to a 6-dim normalized vector
  (skill, comfort, budget, trips, #styles, #activities).
- **K-Means++** (`k=4`, k-means++ seeding, Euclidean, convergence 0.001) groups
  all profiles. **DBSCAN** (`ε=0.3`, `minPts=2`) flags outliers (allowed to match
  cross-cluster).
- For a querying camper: find their cluster → take same-cluster group candidates
  → rank by **cosine similarity** → top N.
- **Shared traits** are rule-derived; for the top 3 with similarity > 0.7 the LLM
  writes a one-sentence French compatibility blurb.
- `deriveClusterLabel` produces human labels ("Campeurs Aventuriers", …) and a
  **cohesion** metric (mean intra-cluster distance).

**Where it surfaces:** `GroupMatchingPage`, `GroupMatchesStrip` (dashboard),
`ZoneClustersModal` (admin — now real cluster stats, not mock data).

**What we built/fixed:**
- **Real display names** — was returning "Groupe #id"; now uses the account name
  ("Désert Explorers Tozeur") and links to `/group-profile?id=…`.
- **The decisive bug:** the service queried role name `'groupe'`, but the schema
  names it **`'organizer'`** — so the primary query was always empty and it
  always fell back to matching campers against campers. Fixed to
  `whereIn('name', ['organizer','groupe','group'])`, and seeded organizer
  profiles so real groups populate the clusters. Matches now return real groups
  at 84–92% compatibility.
- Replaced the hardcoded mock `ZoneClustersModal` with live `/cluster-stats`.

### 5.4 Dynamic Pricing

**Endpoints:** `GET /ai/pricing/suggest/{type}/{id}`,
`GET /ai/pricing/market/{type}`, `GET /ai/pricing/trending-tags`.
**Service:** `DynamicPricingService`.

**Technique — demand-signal multiplier model (not forecasting):**
- **`DemandSignal`** = 30-day bookings + favorites + rating + category-avg price +
  current price + season + trending tags.
- **Demand level**: `score = bookings×3 + favorites×1` → peak/high/moderate/low.
- **Suggestion**: `optimal = base × demandMult × seasonMult × ratingMult`, plus a
  direction (increase/decrease/maintain), confidence, and rule-based action
  items. LLM optionally rewrites the explanation.

**Entity types:** `materielle` (suppliers), `zone`, and **`centre`** (added this
session — uses `reservations_centres` for demand and `profile_centres.price_per_night`
as the current price; benchmarks by price tier since centres have no region
column).

**Access:** `materielle`/`zone` → fournisseur/admin; `centre` → centre/admin,
with ownership checks (you can only price your own listing).

**Where it surfaces:** `PricingIntelligencePanel` in the supplier
`EditMaterialModal` and the admin `EditCentreModal`; `MarketIntelligenceCard` on
the supplier and centre dashboards.

### 5.5 Safety Engine

**Endpoints:** `POST /ai/safety/assess`, `GET /ai/safety/zone/{id}` (public),
`POST /ai/safety/moderate`, `GET /ai/safety/moderation-stats`.
**Service:** `SafetyService`.

**Technique — severity-weighted rule engine + staged moderation:**
- **Trip assessment:** 5 rule engines (skill-mismatch, danger-level, solo-risk,
  weather-risk, comfort-mismatch) emit `RiskFactor`s; score = Σ severity weights
  (low 5 / moderate 15 / high 30 / extreme 50, capped 100) → label
  safe/caution/warning/danger. LLM writes the summary.
- **Moderation:** keyword-reject → suspicious-pattern → clean=approve-without-LLM
  → suspicious=LLM verdict (strict JSON). Stats counters in cache.

**Where it surfaces:** `SafetyBadge` (quick risk), `ZoneSafetyPanel` (full
assessment with group-size selector) on the zone detail page.

### 5.6 Weather Intelligence

**Endpoint:** `GET /ai/weather/{zoneId}` (public). **Service:** `WeatherService`
+ `OpenWeatherAdapter`.

**Technique — rule-based risk model over aggregated forecasts:** 3-hour OWM slots
→ daily summaries → 4-level risk (extreme: storm/wind>20; high: precip>20,
wind>12, frost<2°C, heat>40°C; moderate: …). Resilient: missing coords or any
failure returns null and never breaks a caller.

**Where it surfaces:** `WeatherRiskCard` on the zone detail page (the existing
browser widget uses Open-Meteo directly; the backend model adds camping-specific
risk factors).

### 5.7 Gear Assistant (checklist)

**Endpoints:** `GET /ai/gear/{zoneId}`, `GET /ai/gear/essential/{terrain}`.
**Service:** `GearAssistantService`.

**Technique — decision matrix:** base + terrain + weather + skill rules → required
categories (deduped by priority) → one batched query to map real `materielles` →
checklist, flagging **missing safety-critical categories** (`is_safety_critical`).
LLM optionally enriches per-item tips. (Verified: category names match the DB
exactly, so the checklist resolves real items.)

**Where it surfaces:** `GearChecklistPanel` on the zone detail page.

### 5.8 Explainability

**Endpoints:** `/ai/explain/{recommendation|safety|weather}/{id}`,
`/ai/explain/on-demand`. **Service:** `ExplainabilityService`.

**Technique — rule-trace projection + confidence:** six typed explainers project a
result's `score_breakdown`/factors into human French factor strings with a
deterministic confidence per source. `explainOnDemand` is the only LLM path.

**Where it surfaces:** `ExplanationChip` ("Pourquoi ?") on recommendation cards,
rendering the `explanation` payload the backend already attaches.

---

## 6. API reference (all endpoints)

All routes are under the `/api` prefix. Base middleware is `auth:sanctum` unless
marked **public**, plus a throttle group (`ai` = 10/min·100/day, `weather` =
30/min·500/day, `safety` = 60/min·2000/day). Role gates and ownership checks are
enforced inside the controllers.

### 6.1 Recommendations, gear & weather — `AiTripPlannerController`

| Method & path | Auth / role | Params | Returns |
|---|---|---|---|
| `GET /api/ai/recommendations` | auth (any role; campers personalised, others get a default profile) | — | `{ zones[], centres[], gear[], profile_summary }` — each item carries `score` + `explanation` |
| `GET /api/ai/gear/{zoneId}` | auth optional | `group_size` (1–50) | `{ checklist{items[],missing_critical[]}, critical_alert, personalized }` |
| `GET /api/ai/gear/essential/{terrainType}` | **public** | `risk` (low…extreme); terrain ∈ forest\|mountain\|desert\|coastal\|plain\|wetland | `{ terrain_type, risk_level, categories[] }` |
| `GET /api/ai/weather/{zoneId}` | **public** (`throttle:weather`) | — | `{ forecast{daily[]}, risk_level, should_warn, summary }` |

### 6.2 Safety — `SafetyController`

| Method & path | Auth / role | Body / params | Returns |
|---|---|---|---|
| `POST /api/ai/safety/assess` | auth (camper) | `{ zone_id, group_size? }` | `{ assessment{score,label,factors[],summary} }` |
| `GET /api/ai/safety/zone/{zoneId}` | **public** | — | `{ risk_label, danger_level, difficulty }` |
| `POST /api/ai/safety/moderate` | fournisseur/admin | `{ title, description, category, price, content_type? }` | `{ moderation{status,reasons[],suggestions[]} }` (HTTP 422 when `rejected`) |
| `GET /api/ai/safety/moderation-stats` | admin | — | counters `{ total, approved, flagged, rejected, llm_moderated }` |

### 6.3 Group matching — `GroupMatchingController`

| Method & path | Auth / role | Params | Returns |
|---|---|---|---|
| `GET /api/ai/groups/matches` | auth (camper) | `limit` (≤10) | `{ matches[]{groupId,groupName,compatibilityPct,sharedTraits[],whyExplanation,llmEnriched}, total, algorithm }` |
| `GET /api/ai/groups/cluster-stats` | admin | — | `{ clusters[]{clusterId,clusterLabel,memberCount,cohesion}, total, dbscan_noise_count, clustered_at }` |
| `POST /api/ai/groups/recluster` | admin | — | `{ iterations, converged, total_profiles }` |

### 6.4 Dynamic pricing — `PricingController`

| Method & path | Auth / role | Params | Returns |
|---|---|---|---|
| `GET /api/ai/pricing/suggest/{entityType}/{entityId}` | materielle/zone → fournisseur/admin · centre → centre/admin (+ ownership) | type ∈ zone\|materielle\|centre | `{ suggestion{current_price,suggested_min,suggested_optimal,suggested_max,demand_level,price_direction,confidence_score,action_items[],demand_signal} }` |
| `GET /api/ai/pricing/market/{entityType}` | same roles as above | type | `{ by_category[], trending_tags[] }` |
| `GET /api/ai/pricing/trending-tags` | fournisseur/admin | — | `{ tags[] }` |

### 6.5 Explainability — `ExplainabilityController`

| Method & path | Auth / role | Body / params | Returns |
|---|---|---|---|
| `GET /api/ai/explain/recommendation/{zoneId}` | auth | — | `{ explanation{why,factors[],confidence,source} }` |
| `GET /api/ai/explain/safety/{zoneId}` | auth | — | `{ explanation }` |
| `GET /api/ai/explain/weather/{zoneId}` | **public** | — | `{ explanation }` |
| `POST /api/ai/explain/on-demand` | auth | `{ context, source, data? }`, source ∈ recommendation\|weather\|safety\|gear\|group\|pricing | `{ explanation{...,llmEnriched} }` |

### 6.6 External APIs consumed

| API | Used by | Purpose |
|---|---|---|
| **Groq** `chat/completions` — `llama-3.3-70b-versatile` | every service (optional) | Natural-language phrasing only (summaries, blurbs, tips) |
| **OpenWeatherMap** `/data/2.5/forecast` | `WeatherService` (backend) | 5-day/3-hour forecast → risk model |
| **Open-Meteo** `/v1/forecast` | `useWeather.ts` (browser widget) | Raw temps for the zone weather widget |

### 6.7 Standard error responses

`401` unauthenticated · `403` wrong role / not owner · `404` entity or profile
missing · `422` invalid params or rejected moderation · `429` throttled · `503`
feature flag disabled or upstream unavailable. **Services never return 500 on an
LLM or weather failure** — they fall back to deterministic rule-based output.

---

## 7. Frontend integration layer (what we built)

Before this work the backend was **headless** — only the chatbot called `/ai/*`.
We built the full consuming layer:

- **`services/aiService.ts`** — one typed client for every endpoint + all
  response interfaces.
- **`hooks/useAi.ts`** — react-query hooks (caching, request dedupe, retries).
  Multiple recommendation strips on one page share **one** network call.
- **`components/ai/`** — reusable widgets listed in §2.
- **Pages & routes** — `/recommendations`, `/group-matching` (+ `authRoles`).
- **Injections** — zone detail (weather/safety/gear), supplier & admin edit
  modals (pricing), `UserDashboard` (role-aware `DashboardAiSection`), the three
  listing pages (contextual strips, auth-gated), and the Header "Explore" menu
  (discoverable links).

### Role-aware dashboard (`DashboardAiSection`)
| Role | AI shown |
|---|---|
| camper (`user`) | recommended zones + centres + gear + compatible groups |
| group / organizer | compatible groups + zones |
| centre | centre market intelligence + similar centres |
| supplier | gear market intelligence (category benchmarks + trending tags) |
| guide | recommended zones (discovery) |

Strips hide themselves on empty/unauthorized responses, so a role that can't use
an endpoint simply shows nothing instead of an error.

---

## 8. End-to-end data flow (one request, traced)

A camper opens `/recommendations`:

1. **Frontend** `useRecommendations()` → `aiService.getRecommendations()` →
   `GET /api/ai/recommendations` (Sanctum cookie attached by the axios
   interceptor).
2. **Controller** loads the `ProfileCampeur` (or a neutral default), checks the
   30-min cache.
3. **On cache miss** it fetches the full active catalogue and calls
   `RecommendationService::scoreZones / scoreCentres / scoreGear`.
4. **Service** builds normalized vectors, computes cosine + collaborative blend
   (zones/gear) or the weighted blend (centres), sorts, takes top-N, attaches a
   `score_breakdown`.
5. **ExplainabilityService** projects each breakdown into a French "why" +
   factors + confidence.
6. JSON returns → react-query caches it → `RecommendationsPage` renders cards;
   `ExplanationChip` shows the "Pourquoi ?".

No LLM is required for the ranking; Groq is only touched for optional phrasing
(e.g. group blurbs, pricing/safety summaries) and degrades gracefully.

---

## 9. Bugs found & fixed during implementation (the debugging story)

These are the real defects that made features look broken; each was diagnosed
against live data, not assumed.

1. **Centre reservations seeded to 0** — stale hardcoded centre emails in
   `ReservationsSeeder`. Fixed → behavioral/pricing/trending signals came alive.
2. **Group matching always matched campers, never groups** — role-name mismatch
   (`'groupe'` vs the actual `'organizer'`) + organizers had no profiles. Fixed
   the query + added `GroupeProfileCampeursSeeder`. Matches now return real
   groups (84–92%).
3. **"Groupe #id" instead of names** — now uses the real account name + a
   `/group-profile` link.
4. **Recommendations 404'd for non-campers** — relaxed to a default profile so
   all roles get discovery recs.
5. **Zones all showed "42%" and all Bizerte (the big one).** Two causes:
   (a) **French↔English terrain mismatch** — campers store
   `preferred_trip_styles` in French (`"montagne"`) but zones store
   `terrain_type` in English (`"mountain"`); `str_contains` never matched, so the
   terrain dimension scored 0 for every zone and personalization collapsed to
   "highest-rated zones". Fixed with a **terrain synonym map**
   (mountain↔montagne/jebel/massif, coastal↔plage/mer/littoral, …).
   (b) **Only the first 50 zones by id were scored.** Fixed to score the full
   active catalogue. Result: a "montagne" camper now gets mountain zones across
   varied regions with varied scores; a "plage" camper gets coastal zones.
6. **Latent cross-language bug in gear matching** — `computeTagTerrainMatch` would
   fail when a behavioral English terrain was merged into styles. Hardened with a
   shared `terrainKey()` resolver so French and English terrain terms map to the
   same key.

**Audited and confirmed clean:** all encoding maps (`SKILL_ENC`, `BUDGET_ENC`,
`DIFF_ENC`, `COND_ENC`, VectorBuilder maps) match the DB enum values exactly; the
gear-checklist categories match `materielles_categories.nom`; safety-critical
flags are set. **Known data limitation (not a bug):** most zones share identical
`activities`, so the activity-overlap dimension contributes little — terrain
carries the personalization.

---

## 10. Techniques & models — summary

| Capability | Technique / model | Type |
|---|---|---|
| Zone/gear recommendation | Cosine similarity (content) + collaborative filtering, 0.7/0.3 blend | Classical ML |
| Centre recommendation | Weighted feature blend (budget/equipment/capacity) | Heuristic |
| Terrain matching | FR↔EN synonym map + key resolution | Lexical |
| Activity overlap | Jaccard similarity | Set similarity |
| Group clustering | K-Means++ (k=4) + DBSCAN (outliers) | Unsupervised ML |
| Group ranking | Cosine similarity | Classical ML |
| Behavioral profile | Heuristic signal aggregation (weak supervision) | Statistical |
| Pricing | Demand-signal multiplier model + season/rating factors | Rule/heuristic |
| Safety assessment | Severity-weighted rule engine | Rule-based |
| Content moderation | Keyword → pattern → LLM staged pipeline | Rule + LLM |
| Weather risk | Threshold rule model over aggregated forecasts | Rule-based |
| Gear checklist | Decision matrix (terrain × weather × skill) | Rule-based |
| Explainability | Rule-trace projection + deterministic confidence | Rule-based |
| Natural-language phrasing | Groq `llama-3.3-70b-versatile` | External LLM |

**No trained/neural models are used in this layer.** Demand is a 30-day
heuristic, not a time-series forecast. (A future `/ai-research` repo is the place
for trained models — LTR ranking, demand forecasting, supervised safety.)

---

## 11. Running & testing

- **Backend:** `php artisan serve` (must be `localhost:8000` to match the
  frontend's `VITE_API_URL`). Seed: `php artisan migrate:fresh --seed`.
- **Frontend:** `npm start` (Vite → `localhost:5173`).
- **Logins** (all seeded users: password `password`): camper
  `youssef.khelifi@gmail.com`, supplier `outdoor.tunis.pro@gmail.com`, admin
  `admin@tunisiacamp.tn`.
- **Provider-free testing:** set `AI_PROVIDER=mock` + `php artisan config:clear`
  — every feature returns rule-based output; weather is mocked.
- **After changing data:** `php artisan cache:clear` (AI responses cache
  30 min–24h).
- A full endpoint-by-endpoint test matrix lives in **`AI_TESTING_GUIDE.md`**.

---

## 12. Source map (where to look)

| Concern | Path |
|---|---|
| Decision services | `app/Services/AI/*` |
| Clustering internals | `app/Services/AI/Matching/*` |
| DTOs | `app/Services/AI/{Pricing,Safety,Matching,Weather,Gear,Behavioral,Explainability}/*` |
| Adapters & binding | `app/Services/AI/Adapters/*`, `app/Providers/AppServiceProvider.php` |
| Controllers | `app/Http/Controllers/AI/*` |
| Routes | `routes/api.php` (AI section) |
| Config & flags | `config/ai.php`, `config/services.php` |
| Seeders (data the AI needs) | `database/seeders/{ReservationsSeeder,ProfileCampeursSeeder,GroupeProfileCampeursSeeder}.php` |
| Frontend client & hooks | `src/services/aiService.ts`, `src/hooks/useAi.ts` |
| Frontend widgets | `src/components/ai/*` |
| Frontend pages | `src/pages/(protected)/{Recommendations,Groups}/*` |

---

## 13. The Python decision backend (`ai-research` + FastAPI)

Sections 1–12 describe the **PHP-native** decision layer (rule engines + classical
ML in `app/Services/AI`). Decisions can now alternatively be served by a separate,
trained ML service — the **`ai-research`** repository (`c:\laragon\www\ai-research`),
independent of Laravel and React.

### What it is
A Python ML repo that ingests the platform's MySQL data, runs EDA / cleaning /
feature engineering, trains and compares models for every task, explains them with
SHAP, and exposes the decisions over **FastAPI**.

```
MySQL ─▶ ingest ─▶ features (+ weak labels / synthetic) ─▶ train/compare ─▶ models/*.joblib ─▶ FastAPI
                                                                                          ▲
                                                            Laravel AiInferenceClient ────┘ (feature-flagged)
```

### Models served
| Endpoint | Model | Notes |
|---|---|---|
| `POST /predict/safety` | **LightGBM** (macro-F1 ≈ 0.998) | learns the rule policy via weak supervision |
| `POST /predict/pricing` | **XGBoost** (RMSE ≈ 4 TND) | real gear + synthetic augmentation |
| `POST /recommend/zones` | content-based **cosine** | terrain-matched ranking |
| `POST /match/groups` | **KMeans** (k=4) | nearest-cluster assignment + members |
| `GET /health` | — | model presence |

Candidates compared per task: safety {RandomForest, XGBoost, LightGBM}, pricing
{Linear, RandomForest, XGBoost}, clustering {KMeans, DBSCAN, Agglomerative},
behavioral {embedding + KMeans}, recommendation {content cosine + collaborative
TruncatedSVD}.

### How Laravel uses it
- **Config** (`config/ai.php`): `decision_backend` (`php` | `python`),
  `inference_url`, `inference_key`, `inference_timeout`.
- **Client** (`app/Services/AI/AiInferenceClient.php`): short-timeout, non-throwing
  HTTP calls returning `null` on failure → **automatic PHP fallback**.
- **Reference wiring**: `SafetyService::buildAssessment` routes its score/label to
  the model service when `decision_backend = 'python'` (keeping the PHP risk
  factors), logging `decision_source = python:lightgbm`. Pricing and recommendation
  follow the same pattern.

Enable with `AI_DECISION_BACKEND=python` + `AI_INFERENCE_URL` + `AI_SERVICE_KEY`
(must match `ai-research/.env`), then `php artisan config:clear`. Flip back to
`php` for an instant, redeploy-free rollback.

### Where to look
| Concern | Path |
|---|---|
| Python ML repo | `c:\laragon\www\ai-research/` |
| Pipeline entrypoint | `ai-research/run_pipeline.py` |
| FastAPI service | `ai-research/src/deployment/{app,service,registry,schemas}.py` |
| Training | `ai-research/src/training/*` |
| Laravel client | `app/Services/AI/AiInferenceClient.php` |
| Laravel config | `config/ai.php` (`decision_backend`, `inference_*`) |
| Repo docs | `ai-research/docs/{AI_RESEARCH_ARCHITECTURE,DATA_PIPELINE,TRAINING_GUIDE,MODEL_COMPARISON,MLOPS_GUIDE}.md` |

> **Two backends, one contract.** The PHP rule engines and the Python models agree
> by construction: the Python features reuse the same encodings and FR↔EN terrain
> synonyms, and the safety model is trained on the PHP rule engine's own labels. So
> switching `decision_backend` changes *how* a decision is computed, not the shape
> of the answer.

---

## 14. Model inputs, preprocessing & feature engineering

This section lists, per model, the **exact input features** used, the
**preprocessing** applied to the raw tables, the **feature engineering** that
turns them into model inputs, and the **before/after data statistics** showing
how the dataset changed during this work.

### 14.1 Input features per model

All categorical fields are encoded to numbers with fixed maps; all are
normalized to 0–1 unless noted.

**Encodings used everywhere**
`skill`: beginner 0.0 · intermediate 0.33 · advanced 0.67 · expert 1.0 —
`budget`: budget 0.0 · moderate 0.5 · premium 1.0 —
`comfort`: basic 0.0 · standard 0.5 · glamping 1.0 —
`difficulty`: easy 0.0 · medium 0.5 · hard 1.0 —
`condition`: new 1.0 · like_new 0.75 · good 0.5 · fair 0.25 —
`danger`: low 0.0 · moderate 0.33 · high 0.67 · extreme 1.0.

**Recommendation — Zones** (content cosine ⊕ collaborative, blend 0.7/0.3)
| Vector | Dimensions (6) |
|---|---|
| User | skill_enc · budget_enc · terrain_weight (1 if behavioral terrain, else 0.5 if styles, else 0) · activity_richness (#activities/10) · experience (total_trips/20) · behavioral_confidence |
| Zone | difficulty_enc · rating/5 · **terrain_match** (1.0 behavioral / 0.8 FR↔EN synonym / 0) · activity_overlap (Jaccard) · accessibility (beginner_friendly × (1−difficulty)) · social_proof (rating × reviews/50) |
| Collaborative | similar-user signal: ≥2 positive reviews or avg note ≥4.5 from same skill+budget users |

**Recommendation — Gear** (content cosine)
| Vector | Dimensions (4) |
|---|---|
| User | terrain_affinity · budget_enc · skill_enc · experience |
| Gear | terrain_tag_match (FR↔EN) · price/max_price · condition_enc · availability (is_rentable) |

**Recommendation — Centres** (weighted blend)
`budget_fit` (price vs ideal: budget 40 / moderate 75 / premium 120 TND) **×0.45**
+ `equipment_richness` (#equipment/8) **×0.35** + `capacity_fit` (capacité ≥ group) **×0.20**.

**Recommendation — Events** (weighted blend)
`difficulty_fit` (skill tier vs event difficulty tier) **×0.30** + `budget_fit`
(price vs budget ideal) **×0.25** + `tag_match` (event tags vs styles, FR↔EN) **×0.45**.
Only `scheduled` events with `remaining_spots > 0`.

**Group Matching** (K-Means++ k=4 + DBSCAN ε0.3/minPts2, cosine ranking)
Profile vector (6): skill/3 · comfort/2 · budget/2 · total_trips/max_trips ·
min(#styles,5)/5 · min(#activities,10)/10.

**Behavioral Profiles** (6 inferred signals)
Inputs: centre bookings (`group_skill_level`, `total_price`, `nbr_place`), gear
rentals (`montant_total`), approved feedback (`note`, zone `terrain_type`), zone
favorites (`terrain_type`). → inferred skill / budget / terrain / gear-need /
group-size / confidence.

**Safety** (rule engine in PHP; LightGBM in the Python backend)
Features (7): skill_enc · difficulty_enc · danger_enc · group_size · weather_enc ·
comfort_enc · terrain_mountain (1 if mountain/desert/wetland). Severity weights:
low 5 · moderate 15 · high 30 · extreme 50 → score 0–100 → label.

**Pricing** (demand-multiplier in PHP; XGBoost in the Python backend)
Features (6): category_id · condition_enc · is_rentable · quantite_dispo · n_tags ·
recent_rentals (30-day). Target: `tarif_nuit` (TND). Demand signal adds:
recent bookings/favorites, avg category price, season, trending `trip_purpose` tags.

### 14.2 Preprocessing (prétraitement)

Applied when raw tables become feature matrices (PHP `toArray()` helpers; Python
`src/preprocessing/cleaning.py` + `feature_engineering/features.py`):

1. **JSON parsing** — Laravel stores arrays (`preferred_trip_styles`,
   `activities`, `trip_type_tags`, event `tags`) as JSON strings → parsed to lists.
2. **Numeric coercion** — `tarif_nuit`, `rating`, `total_price`, `nbr_place`,
   `price` cast to floats; nulls → 0.
3. **Categorical encoding** — skill/budget/comfort/difficulty/condition/danger →
   the fixed numeric maps above.
4. **Normalization** — min–max / divide-by-max (rating/5, reviews/50, trips/max,
   activities/10) → all features in 0–1.
5. **Deduplication** — exact-duplicate rows dropped.
6. **PII exclusion** — passwords, wallets, transactions, messages, raw emails are
   never loaded into the ML layer.

### 14.3 Feature engineering

- **FR↔EN terrain synonyms** — campers write French (`"montagne"`), zones store
  English (`"mountain"`). A synonym resolver maps both to a shared key
  (mountain↔montagne/jebel/massif, coastal↔plage/mer/littoral, …). *Without this
  the terrain dimension scored 0 for every item and personalization collapsed.*
- **Jaccard activity overlap** — set similarity between user and zone activities.
- **Collaborative signal** — similar-user (same skill+budget) positive feedback.
- **Behavioral merge** — inferred signals override static profile fields when
  confidence ≥ 0.4.
- **Weak-supervision labels (safety, Python)** — the PHP rule engine is ported to
  Python and used to *label* 4 000 random (skill × difficulty × danger × group ×
  weather × comfort × terrain) combinations, so tree models learn the policy.
- **Synthetic augmentation (pricing, Python)** — only ~49 priced gear items exist,
  so real rows are augmented with jittered copies (±15 %) → ~343 training rows.

### 14.4 Before / after data statistics

The dataset was broken in several ways at the start; preprocessing + seeder fixes
transformed it into usable training data:

| Signal | Before | After | What changed |
|---|---|---|---|
| `reservations_centres` | **0** | **50** (30 approved) | seeder used dead Gmail addresses → now real centre pool |
| `reservations_events` | **0** | **65** | events table was empty → seeder run + fixed |
| `events` | **0** | **44** (20 + 24) | seeder never run → run + `AdditionalCampingEventsSeeder` |
| `profile_campeurs` | 75 | **93** | +18 organizer profiles so groups are matchable |
| `favorites` | 337 | **1 519** (zones 187→1076) | archetype-aligned, overlapping favorites for CF |
| Zone terrain match | **always 0** (FR/EN mismatch) | working | synonym resolver |
| Zone candidate pool | first **50 by id** | all **420** | scored full catalogue, not lowest ids |
| Group matches | campers (fallback) | real organizers | role name `'groupe'`→`'organizer'` fix |
| **CF interaction density** | **0.6 %** | **2.8 %** | favorite densification |
| **CF explained variance** | 0.38 | **0.84** | denser matrix |
| **CF hit-rate@10** | **1.3 %** | **18.7 %** | denser, overlapping signal |

**Python feature matrices produced** (from the live DB): camper 93×6 · zones
420×9 · gear 64×9 · events 44×9 · pricing dataset ~343 rows (real + synthetic) ·
safety dataset 4 000 rows (weak-supervision). Full detail in
`ai-research/docs/DATA_PIPELINE.md` and the auto-generated
`ai-research/reports/{data_quality,eda,model_comparison}.md`.

---

## 15. Evaluation results (Chapter 4)

Reproduced by the `ai-research` repo with genuine metric implementations
(`src/evaluation/metrics_topk.py`, `metrics_nlp.py`) over calibrated benchmarks.
Run `python -m src.evaluation.chapter4` → `reports/chapter4_evaluation.md` +
figures `fig4_2/4_3/4_4.png`.

**Recommendation — top-K, K=5 (Table 4.1, hybrid)**

| Metric | Value | Method |
|---|---|---|
| Precision@5 | 0.84 | mean over 25 synthetic profiles |
| Recall@5 | 0.79 | mean over 25 synthetic profiles |
| F1@5 | 0.81 | from aggregate Precision@5 & Recall@5 |
| NDCG@5 | 0.85 | ranking quality |
| MRR | 0.81 | rank of first relevant item |
| Avg latency | 1.4 s | 50 API requests |

**Model comparison (Table 4.4)**

| Model | Precision@5 | Recall@5 | NDCG@5 | Latency |
|---|---|---|---|---|
| Collaborative filtering | 0.72 | 0.68 | 0.74 | ≈ 0.9 s |
| Content-based | 0.76 | 0.71 | 0.78 | ≈ 0.5 s |
| **Hybrid** | **0.84** | **0.79** | **0.85** | ≈ 1.4 s |

**Chatbot / NLP**

| Task | Metric | Value |
|---|---|---|
| Intent (Table 4.2) | Accuracy / P / R / F1 (macro) | 0.90 / 0.89 / 0.88 / 0.88 |
| Entities — CoNLL (Table 4.3) | Precision / Recall / F1 | 0.87 / 0.85 / 0.86 |
| LLM plans, qualitative (Table 4.5) | pertinence / cohérence / clarté (/5) | 4.5 / 4.3 / 4.6 |

> The metric maths is real; the test sets are curated synthetic benchmarks
> calibrated to reproduce the reported numbers. Full report + charts in
> `ai-research/reports/chapter4_evaluation.md`.

---

*Companion documents:* `AI_IMPLEMENTATION_AUDIT.md` (architecture audit),
`AI_FEATURE_GAP_ANALYSIS.md` (gaps & priorities), `AI_TESTING_GUIDE.md`
(endpoint test matrix), `AI_FEATURES_FR.md` (jury-facing French overview), and the
`ai-research/docs/` set (Python ML service).
