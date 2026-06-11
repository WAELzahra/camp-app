# AI Testing Guide — TunisiaCamp

> **Scope** — Manual testing of the non-conversational AI endpoints
> (Explainability, Group Matching, Dynamic Pricing, Safety, Weather, Gear,
> Recommendations). Trip Planner / Chatbot excluded.
>
> All routes are under the API base (`/api`). Examples use `curl`; adapt the host
> to your environment (`http://localhost:8000` shown).

---

## 0. Prerequisites & Setup

### 0.1 Environment

`.env` keys that affect AI behavior:

```dotenv
AI_PROVIDER=groq            # or "mock" for deterministic, API-free testing
GROQ_API_KEY=...            # required when AI_PROVIDER=groq
GROQ_MODEL=llama-3.3-70b-versatile
OPENWEATHER_API_KEY=...     # required for live weather; mock adapter otherwise
AI_QUEUE_DRIVER=sync

# Feature flags (all default true except content_gen)
AI_WEATHER=true
AI_GEAR_ASSISTANT=true
AI_GROUP_MATCHING=true
AI_PRICING=true
AI_SAFETY=true
AI_EXPLAINABILITY=true
```

> **Tip:** Set `AI_PROVIDER=mock` to test all rule-based logic without spending
> Groq/OpenWeather credits. In mock mode, LLM-enriched fields return
> `llm_enriched: false` and weather returns the Tabarka mock forecast.

### 0.2 Authentication

Most endpoints require `auth:sanctum`. Obtain a token via your normal login flow
and pass it as a Bearer token. Public endpoints (no token) are marked
**PUBLIC**.

```bash
TOKEN="<sanctum token>"
AUTH=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
```

### 0.3 Seed data & caches

```bash
php artisan migrate:fresh --seed     # ensure zones, profiles, materielles exist
php artisan cache:clear              # AI responses are heavily cached
```

> Many endpoints cache for 30 min–24 h. **Clear the cache between test runs** or
> assertions on "fresh" computation will see stale values.

### 0.4 Role requirements

| Endpoint group | Required role |
|---|---|
| Pricing (all) | `fournisseur` or `admin` |
| Safety moderate / stats | `fournisseur` (moderate) · `admin` (stats) |
| Group cluster-stats / recluster | `admin` |
| Everything else | any authenticated `campeur` (profile required) |

---

## 1. Recommendation Engine

### How to trigger
Authenticated camper requests personalized zones + gear.

- **Endpoint:** `GET /api/ai/recommendations`
- **Auth:** required (camper profile must exist)

### Example request
```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/recommendations
```

### Example response (abridged)
```json
{
  "zones": [
    {
      "id": 12, "nom": "Aïn Soltan", "region": "Jendouba",
      "score": 0.83,
      "score_breakdown": {
        "cosine_similarity": 0.79,
        "collaborative_bonus": 0.5,
        "final_score": 0.83,
        "user_vector": [0.67, 0.5, 1.0, 0.4, 0.45, 0.6],
        "zone_vector": [0.5, 0.9, 1.0, 0.33, 0.25, 0.72]
      },
      "explanation": {
        "why": "Cette zone est recommandée car elle correspond à 3 critères de votre profil.",
        "factors": ["Niveau de difficulté adapté...", "Type de terrain..."],
        "confidence": 0.84, "source": "recommendation", "llmEnriched": false
      }
    }
  ],
  "gear": [ { "id": 5, "nom": "Tente 3p", "score": 0.71 } ],
  "profile_summary": { "skill_level": "advanced", "budget_range": "moderate" }
}
```

### Expected DB changes
None (read-only). A cache key `recommendations:{userId}` is written (30 min).

### Expected UI behavior
**None today** — no frontend page consumes this (see gap analysis §1).

### How to verify correctness
- `zones` sorted by `score` desc, ≤5 items; `gear` ≤10.
- `final_score == cosine×0.7 + collaborative×0.3` (recompute from breakdown).
- Change the camper's `skill_level`/`budget_range` in `profile_campeurs`,
  `php artisan cache:clear`, re-call → ordering/vectors should shift.
- Add ≥2 approved feedbacks (note ≥4) from a same-skill+budget user on a zone →
  that zone's `collaborative_bonus` rises.

---

## 2. Behavioral Profiles (internal — verify via recommendations)

### How to trigger
No public endpoint. It runs inside recommendation/trip flows. Test indirectly.

### Procedure
1. Create a camper with `skill_level=beginner` (static).
2. Insert ≥3 **approved** `reservations_centres` with
   `group_skill_level=advanced` for that user.
3. `php artisan cache:clear` (or rely on the observers, which invalidate on
   booking/feedback/favorite changes).
4. Call `GET /api/ai/recommendations`.

### Expected behavior
- With confidence ≥0.4, the inferred `advanced` skill **overrides** the static
  `beginner` in the user vector → recommendations skew to harder terrain.
- Observers (`ReservationCentreObserver`, `ReservationMaterielleObserver`,
  `FeedbackObserver`, `FavoriteObserver`) write
  `behavioral_profile_invalidated` log lines when relevant rows change.

### Expected DB changes
None. Cache key `behavioral_profile:{userId}` written (1 h).

### How to verify correctness
- Check `storage/logs/laravel.log` for `behavioral_profile_computed` with the
  expected `skill`/`budget`/`terrain`/`confidence_score`.
- Confidence math: 0 bookings→0.0; 1–2→0.3; 3–5→0.6; 6–10→0.8; >10→1.0; +0.1
  each for any feedback / any favorites (cap 1.0).

---

## 3. Gear Recommendations

### 3a. Zone checklist
- **Endpoint:** `GET /api/ai/gear/{zoneId}?group_size=3`
- **Auth:** optional (personalized when authenticated)

```bash
curl "${AUTH[@]}" "http://localhost:8000/api/ai/gear/12?group_size=3"
```

```json
{
  "zone_id": 12, "zone_name": "Aïn Soltan", "terrain_type": "forest",
  "group_size": 3,
  "checklist": {
    "items": [
      { "materielle_id": 8, "nom": "Tente 3p", "category": "Tentes",
        "is_critical": false, "is_available": true,
        "reason": "Équipement de base indispensable...",
        "tip": "Vérifiez la capacité — prévoyez 3 places minimum.",
        "priority": 1 }
    ],
    "missing_critical": ["Sécurité"],
    "risk_level": "low", "skill_level": "beginner", "llm_enriched": false
  },
  "critical_alert": "⚠️ Aucun équipement de sécurité disponible...",
  "personalized": true
}
```

### 3b. Essentials lookup (PURE rule, no auth/DB/LLM)
- **Endpoint:** `GET /api/ai/gear/essential/{terrainType}?risk=high`
- `terrainType` ∈ `forest|mountain|desert|coastal|plain|wetland`

```bash
curl -H "Accept: application/json" \
  "http://localhost:8000/api/ai/gear/essential/mountain?risk=high"
```
```json
{ "terrain_type": "mountain", "risk_level": "high",
  "categories": ["Tentes","Sacs de couchage","Cuisine outdoor","Éclairage",
                 "Navigation","Sécurité","Vêtements techniques"] }
```

### Expected DB changes
None. Caches `gear:{hash}` (1 h).

### Expected UI behavior
None today (chatbot surfaces a variant). Intended: zone-detail widget.

### How to verify correctness
- `mountain`/`desert`/`wetland` add safety/navigation categories at priority 1;
  `coastal`/`forest` add at priority 2.
- A zone whose required safety category has **no** `status='up'` materielle →
  that category appears in `missing_critical` + a `critical_alert`.
- `group_size` echoes into the Tentes tip text.

---

## 4. Weather Intelligence

- **Endpoint:** `GET /api/ai/weather/{zoneId}` — **PUBLIC** (`throttle:weather`)
- Requires the zone to have `lat`/`lng`.

```bash
curl -H "Accept: application/json" http://localhost:8000/api/ai/weather/12
```

### Example response (abridged)
```json
{
  "zone_id": 12, "zone_name": "Aïn Soltan",
  "forecast": {
    "location": "Tabarka", "is_mocked": false,
    "daily": [
      { "date": "2026-06-10", "tempMin": 16, "tempMax": 29,
        "windSpeedMax": 6.2, "precipitationMm": 0,
        "mainCondition": "Clear", "riskLevel": "low",
        "riskFactors": ["Conditions favorables au camping"] }
    ]
  },
  "risk_level": "low", "should_warn": false,
  "summary": "Météo prévue (Tabarka) : J+1 16–29°C Clear..."
}
```

### Expected DB changes
None. Cache `weather:forecast:{lat2}:{lng2}` (3 h) + `owm:rate:*` counters.

### Expected UI behavior
The browser weather widget uses **Open-Meteo directly** (`useWeather.ts`), not
this endpoint — so changes here won't show in the current UI.

### How to verify correctness
- With `AI_PROVIDER=mock` → `is_mocked: true`, location "Tabarka (mock)", day 3
  `riskLevel: moderate`.
- Risk thresholds: thunderstorm/wind>20→extreme; precip>20/wind>12/tempMin<2/
  tempMax>40→high; etc.
- Zone with null `lat`/`lng` → `503 Weather data unavailable` (or null forecast).
- Hammer the endpoint past 50/min → rate-limit `RuntimeException` logged.

---

## 5. Safety Engine

### 5a. Trip assessment
- **Endpoint:** `POST /api/ai/safety/assess` — auth required

```bash
curl "${AUTH[@]}" -X POST http://localhost:8000/api/ai/safety/assess \
  -H "Content-Type: application/json" \
  -d '{"zone_id": 12, "group_size": 1}'
```
```json
{
  "zone_id": 12, "zone_name": "Aïn Soltan",
  "assessment": {
    "score": 60, "label": "warning",
    "factors": [
      { "code": "SKILL_MISMATCH_CRITICAL", "severity": "extreme",
        "label": "Niveau insuffisant", "source": "zone",
        "suggestion": "Choisissez une zone classée 'facile'..." },
      { "code": "SOLO_HIGH_RISK", "severity": "high", "source": "profile" }
    ],
    "summary": "Cette sortie présente des risques notables...",
    "blocks_booking": false, "llm_enriched": false
  }
}
```

### 5b. Quick risk (PUBLIC, for listing cards)
```bash
curl -H "Accept: application/json" http://localhost:8000/api/ai/safety/zone/12
```
```json
{ "zone_id": 12, "risk_label": "warning", "danger_level": "high", "difficulty": "hard" }
```

### 5c. Content moderation (fournisseur/admin)
```bash
curl "${AUTH[@]}" -X POST http://localhost:8000/api/ai/safety/moderate \
  -H "Content-Type: application/json" \
  -d '{"title":"Tente neuve","description":"Description complète et honnête de cette tente 3 places en excellent état.","category":"Tentes","price":35,"content_type":"listing"}'
```
```json
{ "moderation": { "status": "approved", "reasons": [], "confidence": 0.9,
  "llm_moderated": false, "content_hash": "..." } }
```
Returns HTTP **422** when `status: rejected`.

### 5d. Moderation stats (admin)
```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/safety/moderation-stats
```

### Expected DB changes
None (advisory). Caches: `safety:trip:{hash}` (30 m), `safety:quick:{id}` (1 h),
`moderation:{hash}` (24 h), and `moderation:stats:*` counters increment.

### Expected UI behavior
None today (no risk badges / safety panel / moderation hook).

### How to verify correctness
- Score = Σ severity weights (low 5/mod 15/high 30/extreme 50), capped 100;
  label ≤15 safe / ≤35 caution / ≤65 warning / else danger. Reproduce by hand
  from `factors`.
- Moderation: a description containing `arnaque`/`scam` → `rejected` (422);
  description <50 chars or price 0/>500 → `flagged` (or LLM verdict if live);
  clean content → `approved` with **no** LLM call (`llm_moderated:false`).
- Re-POST identical content → served from 24 h cache (stats don't double-count
  beyond the first compute within TTL).

---

## 6. Dynamic Pricing

### 6a. Price suggestion (fournisseur/admin; fournisseur = own materielle only)
- **Endpoint:** `GET /api/ai/pricing/suggest/{entityType}/{entityId}`
  (`entityType` ∈ `zone|materielle`)

```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/pricing/suggest/materielle/5
```
```json
{
  "suggestion": {
    "entity_id": 5, "entity_type": "materielle",
    "current_price": 30, "suggested_min": 28.5, "suggested_max": 34.2,
    "suggested_optimal": 31.35, "demand_level": "moderate",
    "confidence_score": 0.6, "price_direction": "maintain",
    "explanation": "Votre prix actuel est bien positionné...",
    "action_items": ["Analysez les réservations des 7 prochains jours..."],
    "llm_enriched": false,
    "demand_signal": { "recent_bookings": 3, "season": "summer", "...": "..." }
  }
}
```

### 6b. Market overview
```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/pricing/market/materielle
curl "${AUTH[@]}" http://localhost:8000/api/ai/pricing/market/zone
```

### 6c. Trending tags
```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/pricing/trending-tags
```

### Expected DB changes
None. Caches: `pricing:suggestion:{type}:{id}` (1 h),
`pricing:market_overview:{type}` (2 h), `pricing:trending_tags` (1 h).

### Expected UI behavior
None today (no supplier pricing panel).

### How to verify correctness
- `optimal = base × demandMult × seasonMult × ratingMult`; verify against the
  `demand_signal` and current month season.
- A fournisseur requesting **another** fournisseur's materielle → **403**.
- `entityType=zone` → `current_price: 0` and `price_direction: maintain` (known
  data gap — zones have no price column).
- Add several recent `reservations_materielles` for the item, clear cache →
  `demand_level` rises (`score = bookings×3 + favorites×1`).

---

## 7. Group Matching

### 7a. My matches
- **Endpoint:** `GET /api/ai/groups/matches?limit=5` — auth required

```bash
curl "${AUTH[@]}" "http://localhost:8000/api/ai/groups/matches?limit=5"
```
```json
{
  "matches": [
    { "groupId": 42, "groupName": "Groupe #42", "clusterId": 1,
      "similarityScore": 0.92, "compatibilityPct": 92.0,
      "sharedTraits": ["Même niveau d'expérience","Budget similaire"],
      "whyExplanation": "Vous partagez le même niveau...",
      "llmEnriched": true, "memberProfiles": [ { "skill": "advanced" } ] }
  ],
  "total": 1, "algorithm": "kmeans-cosine-similarity"
}
```

### 7b. Cluster stats (admin)
```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/groups/cluster-stats
```
```json
{ "clusters": [ { "clusterId": 0, "clusterLabel": "Campeurs Aventuriers",
  "memberCount": 7, "cohesion": 0.21 } ],
  "total": 25, "algorithm": "kmeans-4-euclidean", "dbscan_noise_count": 3 }
```

### 7c. Recluster (admin)
```bash
curl "${AUTH[@]}" -X POST http://localhost:8000/api/ai/groups/recluster
```

### Expected DB changes
None. Caches: `group_matching:kmeans_assignments`,
`group_matching:dbscan_assignments`, `group_matching:vectors`,
`group_matching:clustered_at` (1 h). `recluster` busts and recomputes them.

### Expected UI behavior
The visible `ZoneClustersModal` is **mock data** and does not reflect these
results.

### How to verify correctness
- `matches` sorted by `similarityScore` desc, ≤`limit`.
- Only same-cluster candidates appear (unless the caller is a DBSCAN outlier).
- `compatibilityPct == round(similarityScore×100,1)`.
- Run `recluster` twice → `clustered_at` updates; with k-means++ seeding,
  `iterations`/`converged` reported; cluster labels come from centroid position.
- Requires `groupe`-role users with `ProfileCampeur` (else falls back to
  `campeur`); verify the pool exists or matches will be empty.

---

## 8. Explainability

### 8a. Recommendation explanation
```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/explain/recommendation/12
```

### 8b. Safety explanation
```bash
curl "${AUTH[@]}" http://localhost:8000/api/ai/explain/safety/12
```

### 8c. Weather explanation (PUBLIC)
```bash
curl -H "Accept: application/json" http://localhost:8000/api/ai/explain/weather/12
```

### 8d. On-demand (LLM)
```bash
curl "${AUTH[@]}" -X POST http://localhost:8000/api/ai/explain/on-demand \
  -H "Content-Type: application/json" \
  -d '{"context":"Pourquoi cette zone est recommandée?","source":"recommendation","data":{"skill":"advanced"}}'
```
```json
{ "explanation": { "why": "...", "factors": ["..."], "confidence": 0.8,
  "source": "recommendation", "llmEnriched": true } }
```

### Expected DB changes
None. On-demand caches `explain:ondemand:{hash}` (1 h).

### Expected UI behavior
None today (no "Why?" affordances).

### How to verify correctness
- Confidence per source matches §5 of the audit (weather 0.9 live / 0.6 mock;
  safety `1 − score/100`; on-demand fixed 0.8).
- `source` must be one of `recommendation|weather|safety|gear|group|pricing`
  (on-demand validates this; invalid → 422).
- With `AI_PROVIDER=mock`, on-demand returns
  `Explanation::unavailable()` (`why` empty / detail false).

---

## 9. Cross-Cutting Test Checklist

- [ ] **Mock mode** (`AI_PROVIDER=mock`): every endpoint returns valid,
      rule-based output with `llm_enriched:false` / `is_mocked:true`.
- [ ] **Feature flags off**: e.g. `AI_PRICING=false` → pricing suggest returns
      `503`; `AI_GEAR_ASSISTANT=false` → gear returns `503`.
- [ ] **Auth**: protected routes without a token → `401`; wrong role → `403`.
- [ ] **Throttling**: exceed `ai` (10/min) / `weather` (30/min) / `safety`
      (60/min) → `429`.
- [ ] **Caching**: second identical call is served from cache (check absence of
      a fresh `*_computed`/`*_suggestion` log line, or timing).
- [ ] **Resilience**: with a bad `GROQ_API_KEY`, endpoints still return
      rule-based results (LLM fallback), not `500`.
- [ ] **Logs**: `storage/logs/laravel.log` shows structured decision logs
      (`safety_assessment`, `pricing_suggestion`, `group_matching_query`,
      `behavioral_profile_computed`, `weather_fetch`, etc.).

## NEEDS CLARIFICATION
- No automated test suite for these AI services was found in scope. If one
  exists (`tests/Feature/AI/*`), the assertions above should be encoded there;
  otherwise this manual guide is the current source of truth.
