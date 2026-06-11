# AI Feature Gap Analysis — TunisiaCamp

> **Scope** — Non-conversational AI modules only (Explainability, Group Matching,
> Dynamic Pricing, Safety, Weather Intelligence, Gear Recommendations,
> Recommendation Engine, Behavioral Profiles). Trip Planner & Chatbot excluded.
> Companion to `AI_IMPLEMENTATION_AUDIT.md`.

## Legend

- ✅ **Implemented** — present, wired, and functional end-to-end.
- 🟡 **Partial** — exists but incomplete, degraded, or not integrated.
- ❌ **Missing** — not present.

**Priority** — Critical (blocks the feature being usable) · High (needed for a
credible release) · Medium (quality/robustness) · Low (nice-to-have).

---

## 0. Cross-Cutting Headline

The single dominant gap is **frontend integration**. Every in-scope backend
service is ✅ or 🟡, but the **UI layer is ❌ for all of them** except the
profile-input form and the (out-of-scope) chatbot. The backend is effectively
**headless**. Prioritize building the consuming UI before adding new AI logic.

| Layer | State |
|---|---|
| Backend services & DTOs | ✅ Strong (8/8 implemented) |
| API endpoints & routing | ✅ Complete & role-gated |
| Auth / throttle / caching / logging | ✅ Production-grade |
| Frontend integration | ❌ Largely absent (mock data / parallel APIs) |
| AI/ML models | 🟡 Classical ML present; no trained/forecasting models |
| Automated tests | ❓ NEEDS CLARIFICATION (none observed in scope) |

---

## 1. Recommendation Engine

**Status: 🟡 Partial (backend ✅ / frontend ❌)**

| Aspect | State | Notes |
|---|---|---|
| Cosine scoring (zones+gear) | ✅ | `RecommendationService` |
| Collaborative filtering | ✅ | similar-user feedback blend 0.7/0.3 |
| Behavioral merge | ✅ | via `BehavioralProfile::mergeWithStatic` |
| Endpoint | ✅ | `GET /api/ai/recommendations` (cached 30m) |
| Explanations attached | ✅ | per-zone `explanation` payload |
| **Dedicated frontend page** | ❌ | not called anywhere in React |
| Diversity / exploration in ranking | ❌ | pure score sort, no de-dup/variety |

- **Missing frontend:** a "Recommended for you" zones/gear page or home-feed
  section calling `/ai/recommendations`. **(Critical)**
- **Missing logic:** diversity/novelty term to avoid near-duplicate top results.
  **(Low)**
- **Missing APIs/DB/models:** none — backend is complete.

---

## 2. Behavioral Profiles

**Status: ✅ Implemented (backend) — silent by design**

| Aspect | State | Notes |
|---|---|---|
| 6 inferred signals | ✅ | skill, budget, terrain, gear, group, confidence |
| Confidence + threshold | ✅ | merges when ≥0.4 |
| Cache + observer invalidation | ✅ | 4 observers wired in `AppServiceProvider` |
| Public endpoint | ❌ (by design) | internal only |
| **User-facing visibility** | ❌ | user can't see/override inferred prefs |

- **Missing frontend (optional):** a "your inferred camping style" panel for
  transparency/trust. **(Low/Medium)**
- **Missing DB:** none. **Missing models:** none.
- **Data dependency:** quality scales with booking/feedback volume; cold-start
  users fall back to static profile (handled).

---

## 3. Gear Recommendations (Gear Assistant)

**Status: 🟡 Partial (backend ✅ / frontend ❌ outside chatbot)**

| Aspect | State | Notes |
|---|---|---|
| Gear matrix (terrain/weather/skill) | ✅ | `GearAssistantService` |
| Marketplace item mapping | ✅ | single batched query, no N+1 |
| Missing-critical detection | ✅ | uses `is_safety_critical` categories |
| LLM tip enrichment | ✅ | optional, falls back |
| Endpoints | ✅ | `/ai/gear/{zoneId}`, `/ai/gear/essential/{terrain}` |
| **Frontend page** | ❌ | not called (chatbot surfaces a variant) |

- **Missing frontend:** a gear-checklist widget on the **zone detail page**
  calling `/ai/gear/{zoneId}` + an essentials lookup. **(High)**
- **Missing DB:** depends on `materielles_categories.is_safety_critical` and
  `trip_contexts` being **seeded** correctly — verify seed coverage.
  **(Medium)** — NEEDS CLARIFICATION on data completeness.
- **Missing models:** none.

---

## 4. Weather Intelligence

**Status: 🟡 Partial (backend ✅ / frontend uses a different API)**

| Aspect | State | Notes |
|---|---|---|
| OpenWeatherMap adapter + risk model | ✅ | `OpenWeatherAdapter` |
| Daily aggregation + 4-level risk | ✅ | derived French factors |
| Rate limiting + cache | ✅ | 50/min·900/day, 3h cache |
| Backend endpoint | ✅ | `GET /ai/weather/{zoneId}` |
| **Frontend uses backend** | ❌ | `useWeather.ts` calls Open-Meteo directly |
| Risk factors shown in UI | ❌ | frontend widget shows raw forecast only |

- **Gap (divergence):** two weather sources. The browser widget (Open-Meteo)
  shows temps but **none of the backend's risk intelligence** (frost/heat/wind
  warnings). **(High)**
- **Resolution options:** (a) point `useWeather` at `/ai/weather/{zoneId}` to
  reuse risk factors, or (b) document Open-Meteo as the intended widget and the
  backend as the safety/gear feed. NEEDS CLARIFICATION.
- **Missing DB/models:** none.

---

## 5. Safety Engine

**Status: 🟡 Partial (backend ✅ / frontend ❌)**

| Aspect | State | Notes |
|---|---|---|
| 5 trip-risk rule engines | ✅ | skill/danger/solo/weather/comfort |
| Severity scoring + labels | ✅ | 0–100 → safe/caution/warning/danger |
| Content moderation pipeline | ✅ | keyword→pattern→LLM, 24h cache |
| Public quick-risk | ✅ | `GET /ai/safety/zone/{id}` |
| Auth endpoints | ✅ | assess / moderate / stats |
| **Frontend integration** | ❌ | no risk badges, no assess call, no mod UI |
| `blocks_booking` enforcement | 🟡 | always `false` (advisory only) |
| Moderation wired into listing creation | ❌ | not called on zone/gear submit |

- **Missing frontend:** (1) risk badge on zone cards via quick-risk; (2) a full
  safety panel on zone detail via `/ai/safety/assess`; (3) supplier-side
  moderation feedback. **(High)**
- **Missing integration:** moderation is not invoked automatically when a
  listing/zone/event is created or edited — it's an isolated endpoint.
  **(Critical for the moderation feature to have any effect)**
- **Missing logic:** `blocks_booking` is never set true, so safety can't gate a
  booking even when `danger`. Decide if it should. **(Medium)**
- **Missing models:** none.

---

## 6. Dynamic Pricing

**Status: 🟡 Partial (backend ✅ / frontend ❌ / zone data missing)**

| Aspect | State | Notes |
|---|---|---|
| Demand signal builder | ✅ | bookings/favorites/rating/category/season |
| Multiplier model + confidence | ✅ | `DynamicPricingService` |
| Market overview + trending tags | ✅ | cached 1–2h |
| Role gating + ownership | ✅ | fournisseur/admin |
| Endpoints | ✅ | suggest / market / trending-tags |
| **Frontend (supplier dashboard)** | ❌ | not called anywhere |
| **Zone pricing data** | ❌ | `camping_zones` has no price column |
| Time-series forecasting | ❌ | 30-day heuristic only, not predictive |

- **Missing DB:** a `prix` / `price_per_night` column on `camping_zones` (or a
  zone-pricing source). Without it, zone suggestions are inert. **(Critical for
  zone pricing; gear pricing already works)**
- **Missing frontend:** a supplier "Pricing Intelligence" panel on the gear-edit
  page calling `/ai/pricing/suggest/materielle/{id}`, plus a market-overview
  card. **(High)**
- **Missing model:** real demand **forecasting** (e.g. moving-average/seasonal
  model) to replace the lookback heuristic. **(Medium)**
- **Favorites proxy for gear** (`bookings×2`) is a heuristic — a real gear
  favorites/bookmarks signal would improve accuracy. **(Low)**

---

## 7. Group Matching

**Status: 🟡 Partial (backend ✅ / frontend is MOCK)**

| Aspect | State | Notes |
|---|---|---|
| 6-dim profile vectors | ✅ | `VectorBuilder` |
| K-Means++ clustering | ✅ | k=4, convergence + empty-cluster reseed |
| DBSCAN outlier detection | ✅ | eps 0.3, minPts 2 |
| Cosine match ranking + traits | ✅ | top-3 LLM blurbs if sim>0.7 |
| Cluster labels + cohesion | ✅ | French labels |
| Endpoints | ✅ | matches / cluster-stats / recluster |
| **Frontend** | ❌ | `ZoneClustersModal` = hardcoded mock, button no-ops |
| Recluster scheduling | ❌ | manual admin call only, no cron/queue |
| Candidate pool assumption | 🟡 | relies on `groupe`-role profiles |

- **Missing frontend:** a real "Find your camping group" page calling
  `/ai/groups/matches`, and an admin cluster dashboard calling
  `/ai/groups/cluster-stats` (replace the mock modal). **(High)**
- **Missing automation:** scheduled reclustering (cron/queue) so clusters stay
  fresh as the population grows. Currently cache-expiry + manual recluster only.
  **(Medium)**
- **Missing DB:** none — but the **candidate pool** depends on enough
  `groupe`-role users having `ProfileCampeur` rows. Verify seed/real data.
  **(Medium)** NEEDS CLARIFICATION.
- **Missing models:** none (custom K-Means/DBSCAN are sufficient at this scale).

---

## 8. Explainability

**Status: 🟡 Partial (backend ✅ / dedicated endpoints unused)**

| Aspect | State | Notes |
|---|---|---|
| 6 rule-trace explainers | ✅ | recommendation/weather/safety/gear/group/pricing |
| On-demand LLM explainer | ✅ | strict JSON, cached 1h |
| Confidence per source | ✅ | deterministic |
| Embedded in recommendations | ✅ | `explanation` attached server-side |
| **Dedicated `/ai/explain/*` used by UI** | ❌ | never called |
| "Why?" affordance in UI | ❌ | no info/why buttons surfacing explanations |

- **Missing frontend:** "Why this?" expanders on recommendation/safety/pricing
  cards, calling `/ai/explain/*` or rendering the embedded `explanation`.
  **(Medium — depends on §1/§5/§6 UIs existing first)**
- **Missing DB/models/APIs:** none.

---

## Consolidated Backlog (by priority)

### Critical
1. **Listing moderation integration** — call `SafetyService::moderateContent`
   on zone/gear/event create+update; surface `rejected`/`flagged` to the user.
   (Today moderation has no effect because nothing invokes it automatically.)
2. **Zone price column** — add pricing data to `camping_zones` so zone pricing
   suggestions are meaningful (or formally scope zone pricing out).
3. **Recommendations UI** — a page/feed consuming `/ai/recommendations`; without
   it the flagship personalization is invisible.

### High
4. **Group Matching UI** — replace `ZoneClustersModal` mock with real
   `/ai/groups/matches` + admin `/ai/groups/cluster-stats`.
5. **Pricing Intelligence UI** — supplier panel on gear edit
   (`/ai/pricing/suggest/...`, `/ai/pricing/market/...`).
6. **Safety UI** — zone risk badges (quick-risk) + zone safety panel (assess).
7. **Gear checklist UI** — zone-detail widget (`/ai/gear/{zoneId}`).
8. **Weather convergence** — decide Open-Meteo vs backend; surface risk factors.

### Medium
9. **Scheduled reclustering** (cron/queue) for Group Matching.
10. **Pricing forecasting model** to replace the 30-day heuristic.
11. **`blocks_booking` policy** — decide whether `danger` should gate bookings.
12. **Explainability "Why?" affordances** across AI surfaces.
13. **Verify seed coverage** for `is_safety_critical`, `trip_contexts`,
    zone `terrain_type`/`danger_level`, and `groupe` profiles.

### Low
14. Recommendation diversity/exploration term.
15. Behavioral-profile transparency panel.
16. Real gear favorites signal (replace `bookings×2` proxy).

---

## Missing AI Models (explicit)

| Desired model | Present? | Note |
|---|---|---|
| Content-based similarity (cosine) | ✅ | implemented |
| Collaborative filtering | ✅ | rule-thresholded, not matrix-factorization |
| K-Means / DBSCAN clustering | ✅ | custom PHP implementations |
| Behavioral inference | ✅ | heuristic aggregation |
| **Demand forecasting (time-series)** | ❌ | only 30-day lookback |
| **Trained ranking model (LTR)** | ❌ | linear blend only |
| **Embedding-based semantic matching** | ❌ | categorical vectors only (the
  Qdrant/embedding stack exists but belongs to the out-of-scope chatbot/RAG) |

> Note: a vector DB + embedding pipeline (`KnowledgeIndexer`,
> `PlatformKnowledgeService`) exists in the codebase but serves the
> **out-of-scope** chatbot RAG, not these modules.

---

## NEEDS CLARIFICATION

1. Is the headless backend intended for this milestone (API/academic), or is UI
   expected before release?
2. Should the frontend weather widget consume the backend risk model or remain
   on Open-Meteo?
3. Should listing moderation run automatically on submit? On which entities?
4. Is a zone price column planned? If not, should zone pricing be removed from
   scope?
5. Should `blocks_booking=true` actually prevent bookings for `danger` trips?
6. Are there automated tests for these AI services anywhere in the repo? None
   were found in the inspected scope.
7. Is the `groupe`-role candidate pool populated in production, or should
   matching always use `campeur` profiles?
