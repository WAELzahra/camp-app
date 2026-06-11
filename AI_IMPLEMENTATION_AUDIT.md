# AI Implementation Audit — TunisiaCamp

> **Scope** — This audit covers the non-conversational AI modules of TunisiaCamp:
> Explainability, Group Matching, Dynamic Pricing, Safety, Weather Intelligence,
> Gear Recommendations, the Recommendation Engine, and Behavioral Profiles.
> The **AI Trip Planner** and **Chatbot / conversational planning** flows are
> explicitly **out of scope** and are referenced only where another module
> depends on shared infrastructure.
>
> **Method** — Source-level inspection of `app/Http/Controllers/AI`,
> `app/Services/AI` (and all sub-namespaces), service-provider bindings,
> routes, config, migrations, observers, and the React frontend at
> `C:\Users\deadx\OneDrive\Desktop\TunisiaCamp\Camp-app-front`.
> No code was modified.

---

## 1. Executive Summary

TunisiaCamp ships a surprisingly mature, **deterministic-first** AI layer. The
defining architectural decision across every module is that **PHP makes every
decision** (which zone, which gear, what price, what risk score) using explicit
rule engines and classical ML math (cosine similarity, K-Means, DBSCAN), and the
**LLM is used only to phrase the result in natural French**. The LLM never picks
an entity, never computes a number, and never decides whether something is safe.
This makes the system testable, reproducible, and resilient: every service has a
rule-based fallback and a `mock` provider mode, and a failed LLM/API call
degrades to a still-correct rule-based answer rather than an error.

**Backend maturity is high.** Eight AI capabilities are implemented as
well-structured services with readonly DTOs, aggressive caching, rate limiting,
feature flags, and structured logging. The classical-ML components (the
recommendation cosine engine, the K-Means++/DBSCAN clusterers, the behavioral
inference pipeline) are genuine implementations, not stubs.

**The critical weakness is frontend integration.** With the exception of the
chatbot (out of scope) and the camper-profile data-entry form, **none of the
in-scope AI endpoints are consumed by the React frontend**. Specifically:

- The only `/ai/*` calls in the entire frontend are the four trip-planner
  endpoints. `/ai/recommendations`, `/ai/groups/*`, `/ai/pricing/*`,
  `/ai/safety/*`, and `/ai/explain/*` are **never called**.
- `ZoneClustersModal.tsx` (the one UI that looks like group matching) renders
  **hardcoded mock data** and its action button only `console.log`s.
- `useWeather.ts` calls the **Open-Meteo public API directly** from the browser,
  bypassing the backend `WeatherService` / OpenWeatherMap integration entirely.
- `PricingBreakdown.tsx` is a **static commission calculator** fed by
  `/settings/public`, unrelated to `DynamicPricingService`.

So the platform today has a **headless AI backend**: production-quality services
reachable by API but not surfaced to end users (outside the chatbot). Closing
that gap is overwhelmingly a frontend effort, not a backend one.

**Headline readiness:** backend services average **production-ready**; the
end-to-end features average **partially shipped** because the UI layer is
missing.

---

## 2. Current AI Features

Production-readiness scoring (0–10) weighs: backend completeness, data
dependencies, caching/limits/observability, error handling, and **end-to-end
usability** (is it actually reachable by a user?).

### 2.1 Recommendation Engine
- **Purpose** — Rank camping zones and gear for a camper using content-based
  cosine similarity blended with collaborative filtering.
- **Business value** — Core personalization; drives discovery and booking
  conversion.
- **Implementation status** — Backend complete (`RecommendationService`,
  endpoint `GET /api/ai/recommendations`). Consumes static + behavioral profile.
  No dedicated frontend page (chatbot surfaces a variant).
- **Readiness** — **Backend 9 / End-to-end 4**

### 2.2 Behavioral Profiles
- **Purpose** — Replace stale self-declared profile fields with preferences
  inferred from real activity (bookings, rentals, feedback, favorites).
- **Business value** — Keeps recommendations fresh without asking the user;
  improves all downstream personalization.
- **Implementation status** — Complete (`BehavioralProfileService`), 6 inferred
  signals + confidence, cached with observer-driven invalidation. Internal
  service (no public endpoint by design); feeds the recommendation engine.
- **Readiness** — **Backend 9 / End-to-end 7** (it transparently improves
  recommendations; no UI needed, but also no visibility).

### 2.3 Gear Recommendations (Gear Assistant)
- **Purpose** — Generate a terrain/weather/skill-aware packing checklist mapped
  to real marketplace items, flagging missing safety-critical categories.
- **Business value** — Safety + marketplace cross-sell.
- **Implementation status** — Complete (`GearAssistantService`), endpoints
  `GET /api/ai/gear/{zoneId}` and `GET /api/ai/gear/essential/{terrainType}`.
  No dedicated frontend page (chatbot surfaces it).
- **Readiness** — **Backend 9 / End-to-end 4**

### 2.4 Weather Intelligence
- **Purpose** — Fetch and risk-assess 3-day forecasts for zone coordinates to
  warn campers and feed safety/gear logic.
- **Business value** — Safety, trust, gear relevance.
- **Implementation status** — Backend complete (`WeatherService` +
  `OpenWeatherAdapter`, 3-hour-slot → daily aggregation, 4-level risk model,
  rate-limited, 3-hour cache). Endpoint `GET /api/ai/weather/{zoneId}`.
  **Frontend does not use it** — `useWeather.ts` calls Open-Meteo directly.
- **Readiness** — **Backend 9 / End-to-end 5** (weather is shown, but via a
  different, parallel implementation).

### 2.5 Safety Engine
- **Purpose** — (A) Assess trip risk from profile×zone×weather×group; (B)
  moderate user-generated listing content.
- **Business value** — Liability reduction, marketplace quality, trust.
- **Implementation status** — Complete (`SafetyService`), 5 rule engines +
  severity-weighted scoring + LLM summary; moderation with keyword/pattern/LLM
  stages. Endpoints: `POST /ai/safety/assess`, `GET /ai/safety/zone/{id}`
  (public), `POST /ai/safety/moderate`, `GET /ai/safety/moderation-stats`.
  No frontend integration.
- **Readiness** — **Backend 9 / End-to-end 3**

### 2.6 Dynamic Pricing Intelligence
- **Purpose** — Suggest optimal price ranges for gear/zones from demand signals,
  season, ratings, and category benchmarks; provide market overviews.
- **Business value** — Supplier revenue optimization, marketplace liquidity.
- **Implementation status** — Complete (`DynamicPricingService`), endpoints
  `GET /ai/pricing/suggest/{type}/{id}`, `GET /ai/pricing/market/{type}`,
  `GET /ai/pricing/trending-tags`. Role-gated (fournisseur/admin). No frontend.
  **Known data limitation:** zones have no price column, so zone pricing is
  partially inert (see §7).
- **Readiness** — **Backend 8 / End-to-end 3**

### 2.7 Group Matching
- **Purpose** — Cluster camper profiles (K-Means++ + DBSCAN) and recommend
  compatible groups via cosine similarity, with LLM-written compatibility blurbs.
- **Business value** — Social engagement, group bookings, retention.
- **Implementation status** — Backend complete (`GroupMatchingService` + custom
  `KMeansClusterer`, `DBSCANClusterer`, `VectorBuilder`). Endpoints
  `GET /ai/groups/matches`, `GET /ai/groups/cluster-stats` (admin),
  `POST /ai/groups/recluster` (admin). **Frontend `ZoneClustersModal` is mock
  data**, not wired to these endpoints.
- **Readiness** — **Backend 8 / End-to-end 2**

### 2.8 Explainability
- **Purpose** — Produce user-facing "why" explanations + factor lists +
  confidence for every other AI output; plus an on-demand LLM explainer.
- **Business value** — Trust, transparency, differentiation, academic value.
- **Implementation status** — Complete (`ExplainabilityService`), 7 explainers
  (recommendation, weather, safety, gear, group, pricing, on-demand). Endpoints
  under `/ai/explain/*`. Explanations are embedded in some service responses
  (e.g. recommendations) but the dedicated endpoints are not called by the UI.
- **Readiness** — **Backend 9 / End-to-end 4**

| Feature | Backend | End-to-end | Primary gap |
|---|---|---|---|
| Recommendation Engine | 9 | 4 | No UI page |
| Behavioral Profiles | 9 | 7 | No visibility (works silently) |
| Gear Recommendations | 9 | 4 | No UI page (chatbot only) |
| Weather Intelligence | 9 | 5 | Frontend uses parallel API |
| Safety Engine | 9 | 3 | No UI integration |
| Dynamic Pricing | 8 | 3 | No UI; zone price data missing |
| Group Matching | 8 | 2 | UI is mock data |
| Explainability | 9 | 4 | Dedicated endpoints unused |

---

## 3. Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│ FRONTEND  (React + TypeScript)                                             │
│                                                                            │
│  ✅ ProfileTab.tsx ........ writes skill/comfort/budget/styles (AI INPUT)   │
│  ✅ campeur.types.ts ...... AI-ready type definitions                       │
│  ⚠️ useWeather.ts ......... calls Open-Meteo DIRECTLY (bypasses backend)    │
│  ❌ ZoneClustersModal ..... HARDCODED mock clusters (not wired)             │
│  ❌ PricingBreakdown ...... static commission calc (not AI pricing)         │
│  🟡 CamperAssistant ....... chatbot (OUT OF SCOPE) — only consumer of       │
│                              explanation/safety/gear payloads               │
│                                                                            │
│  ❌ No calls to: /ai/recommendations, /ai/groups/*, /ai/pricing/*,          │
│                  /ai/safety/*, /ai/explain/*                                │
└───────────────────────────────┬────────────────────────────────────────────┘
                                 │  HTTPS  (Sanctum auth + throttle middleware)
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ CONTROLLERS  (app/Http/Controllers/AI)                                     │
│   ExplainabilityController   GroupMatchingController                        │
│   PricingController          SafetyController                               │
│   AiTripPlannerController (recommendations / gear / weather endpoints)      │
│                                                                            │
│   Middleware: auth:sanctum + throttle:{ai|weather|safety}                  │
│   Role gates: admin / fournisseur enforced in-controller                   │
└───────────────────────────────┬────────────────────────────────────────────┘
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ SERVICES  (app/Services/AI)            ← ALL DECISIONS HAPPEN HERE (PHP)    │
│                                                                            │
│  RecommendationService      cosine + collaborative blend                   │
│  BehavioralProfileService   6 inferred signals from activity               │
│  GearAssistantService       gear matrix → checklist                        │
│  WeatherService             forecast + 4-level risk model                  │
│  SafetyService              5 rule engines + moderation                    │
│  DynamicPricingService      demand signal → multiplier model               │
│  GroupMatchingService ──▶ Matching/{KMeansClusterer, DBSCANClusterer,       │
│                                     VectorBuilder, ProfileVector...}        │
│  ExplainabilityService      factor extraction + confidence                 │
│                                                                            │
│  DTOs (readonly): Safety/*, Pricing/*, Matching/*, Weather/*, Gear/*,       │
│                   Behavioral/*, Explainability/Explanation                  │
│                                                                            │
│  Adapters (Strategy pattern, bound in AppServiceProvider):                 │
│    LLMAdapterInterface  → GroqAdapter | MockAdapter                        │
│    WeatherAdapterInterface → OpenWeatherAdapter | MockWeatherAdapter        │
│    RateLimitService (token-bucket-ish counters in Cache)                   │
└──────────┬───────────────────────────────────────────────┬─────────────────┘
           │ (LLM: phrasing only)                           │ (data: all decisions)
           ▼                                                ▼
┌────────────────────────────┐              ┌──────────────────────────────────┐
│ EXTERNAL APIS              │              │ DATABASE (MySQL)                 │
│  • Groq  (chat/completions)│              │  profile_campeurs                │
│  • OpenWeatherMap /forecast│              │  camping_zones (+AI fields)      │
│  (frontend also: Open-Meteo│              │  materielles, materielles_       │
│   directly for the widget) │              │      categories (+is_safety_…)   │
└────────────────────────────┘              │  reservations_centres            │
                                            │      (+group_skill_level,        │
   Cache (Redis/file): forecasts,           │       trip_purpose)              │
   suggestions, cluster assignments,        │  reservations_materielles        │
   behavioral profiles, moderation stats,   │  feedbacks, favorites            │
   rate-limit counters                      │  users / profiles / roles        │
                                            └──────────────────────────────────┘
```

---

## 4. AI Data Flow

The pipeline is identical in shape for every module: **Input → Feature
Engineering → Deterministic Rules/Models → (optional) LLM phrasing → Output
DTO**.

**Input**
- Authenticated user → `ProfileCampeur` (static: skill, comfort, budget,
  preferred styles/activities, total_trips).
- Behavioral activity → bookings, gear rentals, approved feedback, favorites.
- Contextual entities → `CampingZone` (terrain, difficulty, danger, rating,
  reviews, lat/lng), `Materielles` (tarif, condition, tags, category).
- Request params → group size, entity id/type, zone id, terrain.

**Feature Engineering**
- `VectorBuilder` / `RecommendationService` normalize categoricals to numeric
  vectors (skill 0–1, budget 0–1, etc.).
- `BehavioralProfileService` aggregates raw activity into 6 inferred signals +
  a confidence score; `BehavioralProfile::mergeWithStatic()` overrides static
  fields when confidence ≥ 0.4.
- `DynamicPricingService` builds a `DemandSignal` (30-day bookings/favorites,
  rating, category average price, season, trending tags).
- `OpenWeatherAdapter` aggregates 3-hour slots into `DailyWeather` with derived
  risk factors.

**Rules / Models (the actual decision)**
- **Cosine similarity** → zone & gear scoring (content-based), blended 0.7/0.3
  with collaborative filtering.
- **K-Means++ & DBSCAN** → profile clustering for group matching.
- **Severity-weighted rule engines** → safety score (0–100) → label.
- **Multiplier model** → pricing (demand × season × rating).
- **Decision matrix** → gear categories (base + terrain + weather + skill).

**LLM (phrasing only, always optional)**
- Groq turns structured results into a French sentence/paragraph: pricing advice,
  safety summary, group-compatibility blurb, gear tips, on-demand explanation,
  content-moderation verdict for suspicious listings.
- Every LLM call is wrapped in try/catch with a rule-based fallback and is
  skipped entirely under `AI_PROVIDER=mock` or when the feature flag is off.

**Output**
- Strongly-typed readonly DTOs serialized via `toArray()` → JSON. Each carries
  provenance flags (`llm_enriched` / `llm_moderated`, `is_mocked`, `confidence`,
  `generated_at`) so the consumer knows how the answer was produced.

---

## 5. Explainability System

**Service:** `app/Services/AI/ExplainabilityService.php`
**DTO:** `Explainability/Explanation` (`why`, `factors[]`, `confidence`,
`source`, `detailAvailable`, `llmEnriched`).
**Controller/Routes:** `ExplainabilityController` →
`/ai/explain/recommendation/{zoneId}`, `/ai/explain/safety/{zoneId}`,
`/ai/explain/weather/{zoneId}` (weather is public), `/ai/explain/on-demand`.

**How explanations are generated**
- **Rule-trace based (the default).** Six typed explainers each read a structured
  result and emit human factors deterministically:
  - `explainRecommendation()` inspects the zone `score_breakdown` keys
    (`difficulty_match`, `terrain_match`, `beginner_friendly`,
    `activity_overlap`, `rating_bonus`, `similar_users_booked`,
    `similar_users_rating`) and pushes a French factor string per active key.
  - `explainSafetyAssessment()` maps each `RiskFactor->description` to a factor.
  - `explainWeatherWarning()` extracts risk factors from the highest-risk day.
  - `explainGearSuggestion()`, `explainGroupMatch()`,
    `explainPricingSuggestion()` similarly project their DTOs.
- **LLM-based (on-demand only).** `explainOnDemand(context, source, data)` calls
  Groq with a strict JSON contract (`{why, factors[]}`), cached 1 hour by
  `md5(context+source+data)`. Disabled under mock/flag-off, returning
  `Explanation::unavailable()`.

**Confidence scoring** — source-specific, deterministic:
- Recommendation: passed-in normalized score (default 0.84), clamped [0.1, 1.0].
- Weather: 0.9 live / 0.6 mocked.
- Safety: `1 − score/100` (higher risk → lower confidence in "it's fine").
- Gear: 0.9 if LLM-enriched else 0.75.
- Group: the cosine similarity itself.
- Pricing: the pricing-suggestion confidence.
- On-demand LLM: fixed 0.8.

**LLM involvement** — None for the six embedded explainers (pure rule trace).
Only `on-demand` uses the LLM, and even then with a deterministic fallback.

---

## 6. Group Matching

**Service:** `GroupMatchingService` + `Matching/` (`KMeansClusterer`,
`DBSCANClusterer`, `VectorBuilder`, `ProfileVector`, `GroupMatch`,
`ClusterResult`).
**Routes:** `GET /ai/groups/matches`, `GET /ai/groups/cluster-stats` (admin),
`POST /ai/groups/recluster` (admin).

**User profiling → feature vectors** — `VectorBuilder` maps each `ProfileCampeur`
to a **6-dimensional vector**, every dimension normalized to 0–1:
| Dim | Source | Encoding |
|---|---|---|
| 0 | skill_level | beginner 0 / intermediate .33 / advanced .67 / expert 1.0 (÷3) |
| 1 | comfort_level | basic 0 / standard .5 / glamping 1.0 (÷2) |
| 2 | budget_range | budget 0 / moderate .5 / premium 1.0 (÷2) |
| 3 | total_trips | trips ÷ max_trips_in_population (capped 1.0) |
| 4 | #preferred_trip_styles | min(count,5)/5 |
| 5 | #preferred_activities | min(count,10)/10 |

**Clustering**
- **K-Means** (`k=4`, k-means++ seeding, max 100 iters, convergence threshold
  0.001 on total centroid movement, Euclidean distance). Empty clusters are
  re-seeded to a random vector.
- **DBSCAN** (`epsilon=0.3`, `minPoints=2`) runs in parallel purely to flag
  **outliers** (cluster `-1`). Outliers are allowed to match across clusters.
- Assignments, centroids, and vectors are cached 1 hour
  (`group_matching:*`); `recluster` busts the cache and recomputes.

**Similarity metric** — Matching ranks candidates by **cosine similarity** of the
6-dim vectors (separate from the Euclidean distance used during clustering).
Candidates are filtered to the querying user's K-Means cluster (or any cluster if
DBSCAN marks them an outlier), sorted desc, top-N returned.

**Scoring & enrichment**
- `compatibilityPct = similarity × 100`.
- `sharedTraits` computed by rule (same skill / budget / comfort / overlapping
  styles / both experienced).
- `whyExplanation` is rule-based by default; for the **top 3** candidates with
  **similarity > 0.7** and a live provider, an LLM writes a one-sentence French
  blurb.
- `ClusterResult` carries a derived French label (`deriveClusterLabel` —
  "Campeurs Aventuriers", "Campeurs Glamping", "Campeurs Premium", "Campeurs
  Budget Actifs", "Campeurs Polyvalents") and a **cohesion** metric (mean
  intra-cluster pairwise Euclidean distance; lower = tighter).

**Candidate source caveat** — matches are drawn from users with role `groupe`
that have a `ProfileCampeur`; if none exist it falls back to `campeur`-role
users.

---

## 7. Pricing Engine

**Service:** `DynamicPricingService` + `Pricing/{DemandSignal, PricingSuggestion}`.
**Routes:** `GET /ai/pricing/suggest/{entityType}/{entityId}`,
`GET /ai/pricing/market/{entityType}`, `GET /ai/pricing/trending-tags`.
**Access:** `fournisseur` or `admin`; fournisseurs may only price their **own**
`materielle` listings (ownership check).

**Inputs (the `DemandSignal`)**
- `recentBookings` — reservations in the last 30 days.
- `recentFavorites` — favorites in last 30 days (for `materielle`, proxied as
  `bookings × 2` since gear has no favorites table).
- `avgRating`, `reviewCount` — zone rating/reviews (gear has none → 0).
- `avgCategoryPrice` — average `tarif_nuit` of same-category items.
- `currentPrice` — `materielle.tarif_nuit` (**zones have no price column →
  0.0**, see caveat).
- `season` — derived from current month.
- `trendingTags` — top-5 `trip_purpose` values from recent centre reservations,
  cached 1h, with seasonal defaults.

**Demand signal → level** — `score = bookings×3 + favorites×1` →
`peak ≥20 / high ≥10 / moderate ≥4 / low`.

**Rules (multiplier model)** — `optimal = base × demandMult × seasonMult ×
ratingMult`:
- demand: peak 1.20–1.35 / high 1.10–1.20 / moderate 0.95–1.10 / low 0.80–0.95.
- season: summer 1.10 / spring 1.05 / autumn 1.00 / winter 0.95.
- rating: ≥4.5 → 1.05, ≥4.0 → 1.02, <3.0 (and >0) → 0.90.
- `priceDirection` = increase/decrease/maintain vs current ±5%.
- `confidenceScore` built from booking volume, review count, category data
  presence (clamped 0.1–1.0).
- Rule-based `actionItems[]` for overpriced/underpriced/peak/low/low-rating.

**Forecasting** — There is **no time-series forecasting**. "Demand" is a
30-day-lookback heuristic, not a predictive model. Season is calendar-derived.

**LLM** — Optionally rewrites the rule explanation into a 2–3 sentence French
supplier recommendation; falls back to a deterministic template.

**Caveat (data gap)** — Because `camping_zones` has no price column, zone pricing
suggestions operate on a base of `0.0 → max(.,1.0)` and emit `maintain`. Zone
pricing is effectively a placeholder until a price/`prix` column exists. Suggestions
are cached 1h; market overviews 2h.

---

## 8. Safety Engine

**Service:** `SafetyService` + `Safety/{SafetyAssessment, RiskFactor,
ModerationResult}`.
**Routes:** `POST /ai/safety/assess`, `GET /ai/safety/zone/{zoneId}` (public,
cached 1h), `POST /ai/safety/moderate` (fournisseur/admin),
`GET /ai/safety/moderation-stats` (admin).

**Sub-system A — Trip Safety Assessment.** Five rule engines append
`RiskFactor`s:
1. **Skill mismatch** — hard×beginner = extreme; hard×intermediate = moderate;
   medium×beginner = low.
2. **Danger level** — zone `danger_level` extreme/high.
3. **Solo risk** — group size 1 in a high/extreme zone.
4. **Weather risk** — highest-risk forecast day → extreme/high/moderate factor.
5. **Comfort mismatch** — glamping comfort on mountain/desert/wetland.

**Risk factors & scoring** — each factor has a severity; weights
`low 5 / moderate 15 / high 30 / extreme 50`, summed and capped at 100.
**Label thresholds:** `≤15 safe / ≤35 caution / ≤65 warning / else danger`.
`blocks_booking` is currently always `false` (advisory only). A quick public
label (`getQuickRiskLabel`) derives safe/caution/warning straight from
`danger_level`/`difficulty` for listing cards. Assessments cached 30 min.

**Moderation logic** — staged pipeline (cached 24h by content hash):
1. **Rejected-keyword check** (arnaque, faux, illégal, scam, fraud…) → immediate
   `rejected`.
2. **Suspicious-pattern detection** (short description/title, price 0, price
   >500, no spaces).
3. **Clean content** → `approved` without spending an LLM call.
4. **Suspicious** → LLM moderation (strict JSON `status/reasons/suggestions/
   confidence`), else rule-based `flagged`.
Stats counters (`total/approved/flagged/rejected/llm_moderated`) increment in
Cache and back the admin stats endpoint.

**Weather integration** — `SafetyController::assess` pulls a forecast via
`WeatherService` (when the weather flag is on) and passes it into the assessment
so weather risk factors fold into the same score.

---

## 9. Weather Intelligence

**Service:** `WeatherService` + `Weather/{OpenWeatherAdapter,
MockWeatherAdapter, WeatherForecast, DailyWeather, WeatherAdapterInterface}`.
**Route (backend):** `GET /ai/weather/{zoneId}` (public, `throttle:weather`).

**APIs used**
- **Backend:** OpenWeatherMap `/forecast` (5-day/3-hour free tier), keyed via
  `config('services.openweather.*')`, metric units.
- **Frontend (parallel):** `useWeather.ts` calls **Open-Meteo**
  (`api.open-meteo.com`) directly with WMO-code mapping — it does **not** use
  the backend endpoint. This is a notable duplication/divergence.

**Forecast processing** — `OpenWeatherAdapter` groups 3-hour slots by date,
then per day computes tempMin/Max, max wind, summed precipitation, avg humidity,
and the modal weather condition (noon slot used for description/icon). Cached 3h
at ~1 km coordinate precision (`round(lat/lng,2)`).

**Alert generation** — `assessRisk()` derives a per-day level + French factors:
- **extreme:** thunderstorm, wind >20 m/s.
- **high:** precip >20 mm, wind >12, tempMin <2 (frost), tempMax >40.
- **moderate:** precip >5, wind >8, tempMin <8, tempMax >35, snow.
- Overall risk = highest day; `shouldWarnUser()` true for high/extreme.
`getWeatherSummaryForPrompt()` builds a ≤300-char string for LLM prompts.

**Resilience** — Missing coordinates or any adapter exception returns `null`; a
weather failure must never break a caller. Rate limits enforced via
`RateLimitService` (50/min, 900/day soft caps with 80% warnings).

---

## 10. Recommendation Engine

**Service:** `RecommendationService`.
**Endpoint:** `GET /api/ai/recommendations` (via `AiTripPlannerController`,
cached 30 min per user). Also invoked internally by the chatbot and the
explainability recommendation endpoint.

**Recommendation logic**
- **Content-based (cosine).** A 6-dim **user vector** (skill, budget, terrain
  weight, activity richness, experience, behavioral confidence) is compared to a
  6-dim **zone vector** (difficulty, rating, user-relative terrain match,
  Jaccard activity overlap, accessibility, social proof). Gear uses a 4-dim
  user vector vs a 4-dim item vector (terrain tag match, normalized price,
  condition, availability).
- **Collaborative filtering.** Finds profiles with the same skill+budget, pulls
  their approved zone feedback, and rewards zones with ≥2 positive reviews or
  avg note ≥4.5 from similar users.
- **Blend:** `final = cosine×0.7 + collaborative×0.3`. Zones sorted desc, top 5;
  gear top 10.

**User personalization** — The vector is built from the **behaviorally-merged**
profile when available: `BehavioralProfileService` infers skill/budget/terrain
from real activity and `mergeWithStatic()` overrides the self-declared fields
when confidence ≥0.4, with terrain preference prepended to trip styles. Observers
invalidate the behavioral cache when bookings/feedback/favorites change, so the
next recommendation reflects fresh behavior.

**Ranking system** — Pure score sort (no diversity/exploration term).
`score_breakdown` (cosine, collaborative, full user & zone vectors) is attached
to every zone and consumed by `ExplainabilityService::explainRecommendation`.
The recommendations endpoint attaches a per-zone `explanation` payload.

---

## Appendix A — Infrastructure & Cross-Cutting Concerns

- **Provider strategy** — `config('ai.provider')` (`groq` | `mock`).
  `AppServiceProvider` binds `LLMAdapterInterface` and
  `WeatherAdapterInterface` accordingly; all AI services depend on the
  interface, never the concrete adapter.
- **Feature flags** — `config/ai.php → features.*`
  (`trip_planner, weather, gear_assistant, group_matching, content_gen,
  pricing, safety, explainability`). `content_gen` is **off** by default.
- **Rate limiting** — App-level `RateLimitService` (Groq 30/min·6000/day; OWM
  50/min·900/day) **plus** Laravel route throttles: `ai` 10/min·100/day,
  `weather` 30/min·500/day, `safety` 60/min·2000/day.
- **Caching** — Pervasive `Cache::remember`: forecasts (3h), pricing
  suggestions (1h)/market (2h), safety assessments (30m)/moderation (24h),
  cluster assignments (1h), behavioral profiles (1h), recommendations (30m),
  on-demand explanations (1h).
- **Observability** — Structured `Log::info/warning/error` on every decision
  (scores, labels, `llm_enriched`, response times, rate warnings).
- **Resilience** — Every public service method is non-throwing with a typed
  fallback DTO; LLM/weather failures degrade to deterministic output.
- **Async** — `AI_QUEUE_DRIVER` (`sync`|`database`); the trip planner can
  dispatch to an `ai` queue (out of scope here, but shares the infrastructure).

## Appendix B — NEEDS CLARIFICATION

1. **Is the headless backend intentional for this release?** Were the
   Explainability / Group Matching / Pricing / Safety endpoints meant to ship
   with UI, or are they a backend-first / API/academic deliverable?
2. **Weather duplication** — Should the frontend `useWeather` (Open-Meteo) be
   replaced by the backend `/ai/weather/{zoneId}` (OpenWeatherMap), or is the
   direct browser call the intended production path?
3. **`ZoneClustersModal`** — Should it be wired to `/ai/groups/cluster-stats`,
   or is the mock intentional placeholder UI?
4. **Zone pricing** — Is a price/`prix` column planned for `camping_zones`?
   Without it, zone pricing suggestions cannot produce meaningful numbers.
5. **`content_gen` feature flag** — what feature does it gate? No service in
   scope references it.
6. **Group-matching candidate pool** — is the `groupe`-role-with-`ProfileCampeur`
   assumption correct for production data, or should it always match against
   `campeur` profiles?
