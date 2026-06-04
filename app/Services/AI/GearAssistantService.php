<?php

namespace App\Services\AI;

use App\Models\CampingZone;
use App\Models\Materielles;
use App\Models\Materielles_categories;
use App\Models\ProfileCampeur;
use App\Services\AI\Adapters\LLMAdapterInterface;
use App\Services\AI\Gear\GearChecklist;
use App\Services\AI\Gear\GearChecklistItem;
use App\Services\AI\Weather\WeatherForecast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GearAssistantService
{
    // ── Gear matrix ───────────────────────────────────────────────────────────
    private const GEAR_MATRIX = [
        'base' => [
            'categories' => ['Tentes', 'Sacs de couchage', 'Cuisine outdoor', 'Éclairage'],
            'priority'   => 1,
        ],
        'terrain_rules' => [
            'mountain' => [
                'categories' => ['Navigation', 'Sécurité', 'Vêtements techniques'],
                'priority'   => 1,
            ],
            'desert' => [
                'categories' => ['Navigation', 'Sécurité', 'Transport & stockage'],
                'priority'   => 1,
            ],
            'coastal' => [
                'categories' => ['Transport & stockage'],
                'priority'   => 2,
            ],
            'forest' => [
                'categories' => ['Navigation'],
                'priority'   => 2,
            ],
            'wetland' => [
                'categories' => ['Vêtements techniques', 'Sécurité'],
                'priority'   => 1,
            ],
        ],
        'weather_rules' => [
            'high' => [
                'categories' => ['Vêtements techniques', 'Sécurité'],
                'priority'   => 1,
            ],
            'extreme' => [
                'categories' => ['Vêtements techniques', 'Sécurité', 'Navigation'],
                'priority'   => 1,
            ],
            'moderate' => [
                'categories' => ['Vêtements techniques'],
                'priority'   => 2,
            ],
        ],
        'skill_rules' => [
            'beginner' => [
                'extra_tip' => 'En tant que débutant, privilégiez des équipements légers et faciles à monter.',
            ],
            'expert' => [
                'extra_tip' => "Équipement technique recommandé pour ce niveau d'expérience.",
            ],
        ],
    ];

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly LLMAdapterInterface $llm,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function generateChecklist(
        ProfileCampeur   $profile,
        CampingZone      $zone,
        ?WeatherForecast $forecast = null,
        int              $groupSize = 1,
    ): GearChecklist {
        try {
            return $this->buildChecklist($profile, $zone, $forecast, $groupSize);
        } catch (\Throwable $e) {
            Log::error('gear_checklist_failed', [
                'zone_id' => $zone->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this->fallbackChecklist($profile, $zone);
        }
    }

    public function getEssentialItems(string $terrainType, string $riskLevel): array
    {
        $categories = self::GEAR_MATRIX['base']['categories'];

        $terrainRule = self::GEAR_MATRIX['terrain_rules'][$terrainType] ?? null;
        if ($terrainRule && ($terrainRule['priority'] ?? 2) === 1) {
            $categories = array_unique(array_merge($categories, $terrainRule['categories']));
        }

        $weatherRule = self::GEAR_MATRIX['weather_rules'][$riskLevel] ?? null;
        if ($weatherRule && ($weatherRule['priority'] ?? 2) === 1) {
            $categories = array_unique(array_merge($categories, $weatherRule['categories']));
        }

        return array_values($categories);
    }

    public function getCriticalMissingAlert(?GearChecklist $checklist): ?string
    {
        if ($checklist === null || empty($checklist->missing_critical)) {
            return null;
        }

        $list = implode(', ', $checklist->missing_critical);
        return "⚠️ Aucun équipement de sécurité disponible dans le marketplace pour"
            . " cette sortie ({$list}). Procurez-vous ce matériel avant de partir.";
    }

    // ── Private build pipeline ────────────────────────────────────────────────

    private function buildChecklist(
        ProfileCampeur   $profile,
        CampingZone      $zone,
        ?WeatherForecast $forecast,
        int              $groupSize,
    ): GearChecklist {
        $riskLevel  = $this->resolveRiskLevel($forecast);
        $tripStyle  = $this->resolveTripStyle($profile);
        $skillLevel = $profile->skill_level ?? 'beginner';
        $terrain    = $zone->terrain_type   ?? 'plain';
        $tempMin    = $this->resolveMinTemp($forecast);

        $cacheKey = 'gear:' . md5(
            ($profile->profile_id ?? 0) . $zone->id
            . ($forecast?->fetchedAt ?? 'no-weather')
            . $groupSize
        );

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $profile, $zone, $forecast, $groupSize,
            $riskLevel, $tripStyle, $skillLevel, $terrain, $tempMin
        ) {
            // Step 1 — Determine required categories
            $requiredCategories = $this->resolveRequiredCategories($terrain, $riskLevel);

            // Step 2 — Batch-fetch all matching Materielles in ONE query (no N+1)
            $allCategoryNames = array_keys($requiredCategories);
            $allItems = Materielles::whereHas(
                'category',
                fn ($q) => $q->whereIn('nom', $allCategoryNames)
            )
                ->where('status', 'up')
                ->with('category:id,nom,is_safety_critical')
                ->orderByDesc('quantite_dispo')
                ->orderBy('tarif_nuit')
                ->get()
                ->groupBy('category.nom');

            // Step 3 — Fetch safety-critical category names for missing-detection
            $criticalCategoryNames = Materielles_categories::where('is_safety_critical', true)
                ->pluck('nom')
                ->toArray();

            // Step 4 — Build checklist items + detect missing critical categories
            $checklistItems  = [];
            $missingCritical = [];

            foreach ($requiredCategories as $categoryName => $rule) {
                $priority   = $rule['priority'];
                $isCritical = in_array($categoryName, $criticalCategoryNames, true);
                $itemsForCat = $allItems->get($categoryName, collect());

                if ($itemsForCat->isEmpty()) {
                    if ($isCritical) {
                        $missingCritical[] = $categoryName;
                    }
                    // Add a placeholder item so the category still appears
                    $checklistItems[] = new GearChecklistItem(
                        materielle_id: 0,
                        nom:           $categoryName . ' (non disponible)',
                        brand:         '',
                        category:      $categoryName,
                        tarif_nuit:    0.0,
                        url:           '',
                        is_available:  false,
                        is_critical:   $isCritical,
                        reason:        $this->buildReason($categoryName, $rule['source'], $terrain, $riskLevel),
                        tip:           $this->buildTip($categoryName, $groupSize, $tempMin, $skillLevel),
                        priority:      $priority,
                    );
                    continue;
                }

                // Up to 2 items per category
                foreach ($itemsForCat->take(2) as $mat) {
                    $checklistItems[] = new GearChecklistItem(
                        materielle_id: $mat->id,
                        nom:           $mat->nom,
                        brand:         $mat->brand ?? '',
                        category:      $categoryName,
                        tarif_nuit:    (float) ($mat->tarif_nuit ?? 0),
                        url:           '/marketplace/materielle/' . $mat->id,
                        is_available:  (($mat->quantite_dispo ?? 0) > 0),
                        is_critical:   $isCritical,
                        reason:        $this->buildReason($categoryName, $rule['source'], $terrain, $riskLevel),
                        tip:           $this->buildTip($categoryName, $groupSize, $tempMin, $skillLevel),
                        priority:      $priority,
                    );
                }
            }

            // Step 5 — LLM enrichment (optional, non-blocking)
            $llmEnriched = false;
            if (
                config('ai.features.gear_assistant') &&
                config('ai.provider') !== 'mock' &&
                ! empty($checklistItems)
            ) {
                [$checklistItems, $llmEnriched] = $this->enrichWithLLM(
                    $checklistItems, $profile, $zone, $forecast
                );
            }

            $checklist = new GearChecklist(
                items:            $checklistItems,
                missing_critical: $missingCritical,
                risk_level:       $riskLevel,
                trip_style:       $tripStyle,
                skill_level:      $skillLevel,
                llm_enriched:     $llmEnriched,
                generated_at:     now()->toIso8601String(),
            );

            Log::info('gear_checklist_generated', [
                'zone_id'          => $zone->id,
                'terrain_type'     => $terrain,
                'items_count'      => count($checklist->items),
                'missing_critical' => $checklist->missing_critical,
                'llm_enriched'     => $checklist->llm_enriched,
                'risk_level'       => $checklist->risk_level,
                'cached'           => false,
            ]);

            return $checklist;
        });
    }

    // ── Rule resolution ───────────────────────────────────────────────────────

    /**
     * Returns ['CategoryName' => ['priority' => int, 'source' => string], ...]
     * Deduplicates, keeping the highest priority (lowest int) per category.
     */
    private function resolveRequiredCategories(string $terrain, string $riskLevel): array
    {
        $categories = [];

        $merge = function (array $names, int $priority, string $source) use (&$categories) {
            foreach ($names as $name) {
                if (
                    ! isset($categories[$name]) ||
                    $priority < $categories[$name]['priority']
                ) {
                    $categories[$name] = ['priority' => $priority, 'source' => $source];
                }
            }
        };

        // Base (always required)
        $merge(self::GEAR_MATRIX['base']['categories'], 1, 'base');

        // Terrain rules
        $terrainRule = self::GEAR_MATRIX['terrain_rules'][$terrain] ?? null;
        if ($terrainRule) {
            $merge($terrainRule['categories'], $terrainRule['priority'], 'terrain');
        }

        // Weather rules
        $weatherRule = self::GEAR_MATRIX['weather_rules'][$riskLevel] ?? null;
        if ($weatherRule) {
            $merge($weatherRule['categories'], $weatherRule['priority'], 'weather');
        }

        return $categories;
    }

    private function resolveRiskLevel(?WeatherForecast $forecast): string
    {
        if ($forecast === null) {
            return 'low';
        }
        $priority = ['extreme' => 4, 'high' => 3, 'moderate' => 2, 'low' => 1];
        $max = 'low';
        foreach ($forecast->daily as $day) {
            if (($priority[$day->riskLevel] ?? 0) > ($priority[$max] ?? 0)) {
                $max = $day->riskLevel;
            }
        }
        return $max;
    }

    private function resolveTripStyle(ProfileCampeur $profile): string
    {
        $raw = $profile->preferred_trip_styles;
        if (is_array($raw)) {
            $styles = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $styles  = is_array($decoded) ? $decoded : [];
        } else {
            $styles = [];
        }
        return $styles[0] ?? 'camping';
    }

    private function resolveMinTemp(?WeatherForecast $forecast): ?float
    {
        if ($forecast === null || empty($forecast->daily)) {
            return null;
        }
        return min(array_map(fn ($d) => $d->tempMin, $forecast->daily));
    }

    // ── Reason + tip builders ─────────────────────────────────────────────────

    private function buildReason(string $category, string $source, string $terrain, string $riskLevel): string
    {
        return match ($source) {
            'terrain' => match ($terrain) {
                'mountain' => "Nécessaire pour la navigation en montagne",
                'desert'   => "Indispensable en terrain désertique",
                'wetland'  => "Obligatoire en zone humide",
                default    => "Recommandé pour ce type de terrain",
            },
            'weather' => match ($riskLevel) {
                'extreme' => "Obligatoire en raison des conditions météo extrêmes",
                'high'    => "Recommandé en raison des conditions météo défavorables",
                default   => "Conseillé compte tenu des prévisions météo",
            },
            default => "Équipement de base indispensable pour tout camping",
        };
    }

    private function buildTip(string $category, int $groupSize, ?float $tempMin, string $skill): string
    {
        $tip = match ($category) {
            'Tentes'           => "Vérifiez la capacité — prévoyez {$groupSize} places minimum.",
            'Sacs de couchage' => $tempMin !== null
                ? "Choisissez un sac adapté aux températures {$tempMin}°C."
                : "Choisissez un sac de couchage 3 saisons polyvalent.",
            'Navigation'       => "Emportez toujours une boussole en plus du GPS.",
            'Sécurité'         => "Vérifiez l'état de votre trousse de premiers secours avant le départ.",
            'Éclairage'        => "Prévoyez des piles de rechange ou une lampe rechargeable.",
            'Cuisine outdoor'  => "Vérifiez la disponibilité du gaz en zone isolée.",
            'Vêtements techniques' => "Privilégiez des couches superposables (système en 3 couches).",
            'Transport & stockage' => "Imperméabilisez vos sacs — humidité = risque matériel.",
            default            => "Vérifiez l'état de l'équipement avant le départ.",
        };

        $skillTip = self::GEAR_MATRIX['skill_rules'][$skill]['extra_tip'] ?? null;
        return $skillTip ? $tip . ' ' . $skillTip : $tip;
    }

    // ── LLM enrichment ────────────────────────────────────────────────────────

    /**
     * @param  GearChecklistItem[]  $items
     * @return array{GearChecklistItem[], bool}
     */
    private function enrichWithLLM(
        array            $items,
        ProfileCampeur   $profile,
        CampingZone      $zone,
        ?WeatherForecast $forecast,
    ): array {
        try {
            $snapshot = array_slice(
                array_map(fn ($i) => [
                    'id'       => $i->materielle_id,
                    'category' => $i->category,
                    'nom'      => mb_substr($i->nom, 0, 60),
                ], $items),
                0, 8
            );

            $context = json_encode([
                'skill_level'   => $profile->skill_level,
                'zone_terrain'  => $zone->terrain_type,
                'weather_risk'  => $forecast ? $this->resolveRiskLevel($forecast) : 'unknown',
                'items'         => $snapshot,
            ], JSON_UNESCAPED_UNICODE);

            $systemPrompt = "You are a camping gear expert. For each item in the checklist, "
                . "provide a 1-sentence personalized tip in French based on the user profile and "
                . "conditions. Respond ONLY with a JSON array of {\"id\": int, \"tip\": string} objects.";

            $raw  = $this->llm->complete($systemPrompt, $context, 500);
            $raw  = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
            $raw  = preg_replace('/\s*```$/', '', $raw ?? '');
            $tips = json_decode(trim($raw ?? ''), true, 512, JSON_THROW_ON_ERROR);

            // Build id → tip map
            $tipMap = [];
            foreach ($tips as $t) {
                if (isset($t['id'], $t['tip'])) {
                    $tipMap[(int) $t['id']] = (string) $t['tip'];
                }
            }

            // Rebuild items with enriched tips where available
            $enriched = array_map(function (GearChecklistItem $item) use ($tipMap) {
                $newTip = $tipMap[$item->materielle_id] ?? null;
                if ($newTip === null) {
                    return $item;
                }
                return new GearChecklistItem(
                    materielle_id: $item->materielle_id,
                    nom:           $item->nom,
                    brand:         $item->brand,
                    category:      $item->category,
                    tarif_nuit:    $item->tarif_nuit,
                    url:           $item->url,
                    is_available:  $item->is_available,
                    is_critical:   $item->is_critical,
                    reason:        $item->reason,
                    tip:           $newTip,
                    priority:      $item->priority,
                );
            }, $items);

            return [$enriched, true];

        } catch (\Throwable $e) {
            Log::warning('gear_llm_enrichment_failed', [
                'error' => $e->getMessage(),
            ]);
            return [$items, false];
        }
    }

    // ── Fallback ──────────────────────────────────────────────────────────────

    private function fallbackChecklist(ProfileCampeur $profile, CampingZone $zone): GearChecklist
    {
        return new GearChecklist(
            items:            [],
            missing_critical: [],
            risk_level:       'unknown',
            trip_style:       $this->resolveTripStyle($profile),
            skill_level:      $profile->skill_level ?? 'beginner',
            llm_enriched:     false,
            generated_at:     now()->toIso8601String(),
        );
    }
}
