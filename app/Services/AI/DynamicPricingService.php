<?php

namespace App\Services\AI;

use App\Models\CampingZone;
use App\Models\Favorite;
use App\Models\Materielles;
use App\Models\Reservations_centre;
use App\Models\Reservations_materielles;
use App\Services\AI\Adapters\LLMAdapterInterface;
use App\Services\AI\Pricing\DemandSignal;
use App\Services\AI\Pricing\PricingSuggestion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicPricingService
{
    public function __construct(
        private readonly LLMAdapterInterface $llm,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function analyzeDemand(int $entityId, string $entityType): DemandSignal
    {
        try {
            return $this->buildDemandSignal($entityId, $entityType);
        } catch (\Throwable $e) {
            Log::warning('pricing_demand_analysis_failed', [
                'entity_id'   => $entityId,
                'entity_type' => $entityType,
                'error'       => $e->getMessage(),
            ]);
            return $this->fallbackDemandSignal($entityId, $entityType);
        }
    }

    public function suggestPrice(int $entityId, string $entityType): PricingSuggestion
    {
        try {
            return Cache::remember(
                "pricing:suggestion:{$entityType}:{$entityId}",
                3600,
                fn () => $this->buildSuggestion($entityId, $entityType)
            );
        } catch (\Throwable $e) {
            Log::error('pricing_suggestion_failed', [
                'entity_id'   => $entityId,
                'entity_type' => $entityType,
                'error'       => $e->getMessage(),
            ]);
            return $this->fallbackSuggestion($entityId, $entityType);
        }
    }

    public function getMarketOverview(string $entityType = 'materielle'): array
    {
        return Cache::remember("pricing:market_overview:{$entityType}", 7200, function () use ($entityType) {
            if ($entityType === 'zone') {
                return $this->buildZoneMarketOverview();
            }
            return $this->buildMaterielleMarketOverview();
        });
    }

    // ── Core private methods ──────────────────────────────────────────────────

    private function buildDemandSignal(int $entityId, string $entityType): DemandSignal
    {
        $trendingTags = $this->getTrendingTripTags();
        $season       = $this->currentSeason();

        if ($entityType === 'zone') {
            return $this->buildZoneDemandSignal($entityId, $trendingTags, $season);
        }
        return $this->buildMaterielleDemandSignal($entityId, $trendingTags, $season);
    }

    private function buildZoneDemandSignal(int $entityId, array $trendingTags, string $season): DemandSignal
    {
        $zone = CampingZone::find($entityId);

        // Zone has no price column — use 0.0 as placeholder
        $currentPrice = 0.0;
        $avgRating    = (float) ($zone?->rating ?? 0.0);
        $reviewCount  = (int)   ($zone?->reviews_count ?? 0);

        $recentBookings = 0;
        try {
            $recentBookings = (int) Reservations_centre::where('centre_id', $entityId)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
        } catch (\Throwable) {}

        // Favorites for zones use favoritable_type = 'zone'
        $recentFavorites = 0;
        try {
            $recentFavorites = (int) Favorite::where('favoritable_type', 'zone')
                ->where('favoritable_id', $entityId)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
        } catch (\Throwable) {}

        // Avg price of zones with same terrain_type (no prix column — use 0)
        $avgCategoryPrice = 0.0;

        return new DemandSignal(
            entityId:         $entityId,
            entityType:       'zone',
            recentBookings:   $recentBookings,
            recentFavorites:  $recentFavorites,
            avgRating:        $avgRating,
            reviewCount:      $reviewCount,
            avgCategoryPrice: $avgCategoryPrice,
            currentPrice:     $currentPrice,
            demandLevel:      $this->calcDemandLevel($recentBookings, $recentFavorites),
            trendingTags:     $trendingTags,
            season:           $season,
        );
    }

    private function buildMaterielleDemandSignal(int $entityId, array $trendingTags, string $season): DemandSignal
    {
        $item         = Materielles::with('category:id,nom')->find($entityId);
        $currentPrice = (float) ($item?->tarif_nuit ?? 0.0);
        $avgRating    = 0.0;
        $reviewCount  = 0;

        $recentBookings = 0;
        try {
            $recentBookings = (int) Reservations_materielles::where('materielle_id', $entityId)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
        } catch (\Throwable) {}

        // Materielles have no favorites — use bookings * 2 as proxy
        $recentFavorites = $recentBookings * 2;

        $avgCategoryPrice = $currentPrice;
        try {
            $avg = Materielles::where('category_id', $item?->category_id)
                ->where('id', '!=', $entityId)
                ->where('status', 'up')
                ->whereNotNull('tarif_nuit')
                ->avg('tarif_nuit');
            if ($avg !== null) {
                $avgCategoryPrice = (float) $avg;
            }
        } catch (\Throwable) {}

        return new DemandSignal(
            entityId:         $entityId,
            entityType:       'materielle',
            recentBookings:   $recentBookings,
            recentFavorites:  $recentFavorites,
            avgRating:        $avgRating,
            reviewCount:      $reviewCount,
            avgCategoryPrice: $avgCategoryPrice,
            currentPrice:     $currentPrice,
            demandLevel:      $this->calcDemandLevel($recentBookings, $recentFavorites),
            trendingTags:     $trendingTags,
            season:           $season,
        );
    }

    private function buildSuggestion(int $entityId, string $entityType): PricingSuggestion
    {
        $signal = $this->analyzeDemand($entityId, $entityType);

        // ── Demand multiplier range ───────────────────────────────────────────
        [$multMin, $multMax] = match ($signal->demandLevel) {
            'peak'     => [1.20, 1.35],
            'high'     => [1.10, 1.20],
            'moderate' => [0.95, 1.10],
            default    => [0.80, 0.95],
        };

        // ── Season adjustment ─────────────────────────────────────────────────
        $seasonMult = match ($signal->season) {
            'summer' => 1.10,
            'spring' => 1.05,
            'autumn' => 1.00,
            default  => 0.95,
        };

        // ── Rating adjustment ─────────────────────────────────────────────────
        $ratingMult = 1.0;
        if ($signal->avgRating >= 4.5)      { $ratingMult = 1.05; }
        elseif ($signal->avgRating >= 4.0)  { $ratingMult = 1.02; }
        elseif ($signal->avgRating > 0 && $signal->avgRating < 3.0) { $ratingMult = 0.90; }

        // ── Base price for calculations (min 1.0 to avoid div-by-zero) ───────
        $base = max($signal->currentPrice, 1.0);

        $rawMin = $base * $multMin * $seasonMult * $ratingMult;
        $rawMax = $base * $multMax * $seasonMult * $ratingMult;

        $suggestedMin     = round(max($rawMin, 1.0), 2);
        $suggestedMax     = round($rawMax, 2);
        $suggestedOptimal = round(($suggestedMin + $suggestedMax) / 2, 2);

        // ── Price direction ───────────────────────────────────────────────────
        $priceDirection = 'maintain';
        if ($signal->currentPrice > 0) {
            if ($suggestedOptimal > $signal->currentPrice * 1.05) {
                $priceDirection = 'increase';
            } elseif ($suggestedOptimal < $signal->currentPrice * 0.95) {
                $priceDirection = 'decrease';
            }
        }

        // ── Confidence score ──────────────────────────────────────────────────
        $confidence = 0.5;
        if ($signal->recentBookings > 5)     { $confidence += 0.2; }
        if ($signal->reviewCount > 10)        { $confidence += 0.1; }
        if ($signal->avgCategoryPrice > 0 && $signal->avgCategoryPrice !== $signal->currentPrice) {
            $confidence += 0.1;
        }
        if ($signal->recentBookings === 0)    { $confidence -= 0.2; }
        $confidence = round(min(max($confidence, 0.1), 1.0), 2);

        // ── Competitor gap ────────────────────────────────────────────────────
        $isOverpriced  = $signal->avgCategoryPrice > 0 &&
                         $signal->currentPrice > $signal->avgCategoryPrice * 1.3;
        $isUnderpriced = $signal->avgCategoryPrice > 0 &&
                         $signal->currentPrice < $signal->avgCategoryPrice * 0.7;

        // ── Rule-based action items ───────────────────────────────────────────
        $actionItems = ['Analysez les réservations des 7 prochains jours avant d\'ajuster.'];

        if ($isOverpriced) {
            $actionItems[] = 'Votre prix est supérieur à la moyenne de votre catégorie — envisagez une réduction.';
        }
        if ($isUnderpriced) {
            $actionItems[] = 'Votre prix est inférieur à la moyenne — vous pouvez augmenter sans risque.';
        }
        if ($signal->demandLevel === 'peak') {
            $actionItems[] = 'Demande élevée détectée — c\'est le bon moment pour augmenter légèrement.';
        }
        if ($signal->demandLevel === 'low') {
            $actionItems[] = 'Demande faible — une promotion temporaire pourrait attirer plus de réservations.';
        }
        if ($signal->avgRating > 0 && $signal->avgRating < 3.5) {
            $actionItems[] = 'Améliorez votre note en répondant aux avis négatifs avant d\'augmenter le prix.';
        }

        // ── Explanation (LLM or rule-based) ───────────────────────────────────
        $llmEnriched = false;
        $explanation = $this->buildRuleExplanation($priceDirection, $signal, $suggestedOptimal);

        $useProvider = config('ai.provider') !== 'mock';
        $featureOn   = config('ai.features.pricing', true);

        if ($featureOn && $useProvider) {
            $enriched = $this->enrichWithLLM($signal, $suggestedOptimal, $priceDirection);
            if ($enriched !== null) {
                $explanation = $enriched;
                $llmEnriched = true;
            }
        }

        $suggestion = new PricingSuggestion(
            entityId:         $entityId,
            entityType:       $entityType,
            currentPrice:     $signal->currentPrice,
            suggestedMin:     $suggestedMin,
            suggestedMax:     $suggestedMax,
            suggestedOptimal: $suggestedOptimal,
            demandLevel:      $signal->demandLevel,
            confidenceScore:  $confidence,
            priceDirection:   $priceDirection,
            explanation:      $explanation,
            actionItems:      $actionItems,
            llmEnriched:      $llmEnriched,
            demandSignal:     $signal,
            generatedAt:      now()->toISOString(),
        );

        Log::info('pricing_suggestion', [
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'current_price' => $signal->currentPrice,
            'suggested'     => $suggestion->suggestedOptimal,
            'direction'     => $suggestion->priceDirection,
            'demand_level'  => $signal->demandLevel,
            'confidence'    => $suggestion->confidenceScore,
            'llm_enriched'  => $suggestion->llmEnriched,
        ]);

        return $suggestion;
    }

    // ── Market overview ───────────────────────────────────────────────────────

    private function buildMaterielleMarketOverview(): array
    {
        $rows = Materielles::with('category:id,nom')
            ->where('status', 'up')
            ->whereNotNull('tarif_nuit')
            ->select(
                'category_id',
                DB::raw('ROUND(AVG(tarif_nuit), 2) as avg_price'),
                DB::raw('MIN(tarif_nuit) as min_price'),
                DB::raw('MAX(tarif_nuit) as max_price'),
                DB::raw('COUNT(*) as item_count')
            )
            ->groupBy('category_id')
            ->get();

        $categories = \App\Models\Materielles_categories::pluck('nom', 'id')->toArray();

        return [
            'entity_type' => 'materielle',
            'by_category' => $rows->map(fn ($r) => [
                'category_id'   => $r->category_id,
                'category_name' => $categories[$r->category_id] ?? "Catégorie {$r->category_id}",
                'avg_price'     => (float) $r->avg_price,
                'min_price'     => (float) $r->min_price,
                'max_price'     => (float) $r->max_price,
                'item_count'    => (int)   $r->item_count,
            ])->values()->toArray(),
            'trending_tags' => $this->getTrendingTripTags(),
            'generated_at'  => now()->toISOString(),
        ];
    }

    private function buildZoneMarketOverview(): array
    {
        $rows = CampingZone::where('status', 1)
            ->select(
                'terrain_type',
                DB::raw('ROUND(AVG(rating), 2) as avg_rating'),
                DB::raw('MIN(rating) as min_rating'),
                DB::raw('MAX(rating) as max_rating'),
                DB::raw('COUNT(*) as zone_count')
            )
            ->groupBy('terrain_type')
            ->get();

        return [
            'entity_type'  => 'zone',
            'by_terrain'   => $rows->map(fn ($r) => [
                'terrain_type' => $r->terrain_type ?? 'unknown',
                'avg_rating'   => (float) $r->avg_rating,
                'min_rating'   => (float) $r->min_rating,
                'max_rating'   => (float) $r->max_rating,
                'zone_count'   => (int)   $r->zone_count,
            ])->values()->toArray(),
            'trending_tags' => $this->getTrendingTripTags(),
            'generated_at'  => now()->toISOString(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getTrendingTripTags(): array
    {
        return Cache::remember('pricing:trending_tags', 3600, function () {
            try {
                $trending = Reservations_centre::where('created_at', '>=', now()->subDays(30))
                    ->whereNotNull('trip_purpose')
                    ->select('trip_purpose', DB::raw('count(*) as cnt'))
                    ->groupBy('trip_purpose')
                    ->orderByDesc('cnt')
                    ->limit(5)
                    ->pluck('cnt', 'trip_purpose')
                    ->toArray();

                if (! empty($trending)) {
                    return array_keys($trending);
                }
            } catch (\Throwable) {}

            return $this->seasonalDefaultTags();
        });
    }

    private function seasonalDefaultTags(): array
    {
        return match ($this->currentSeason()) {
            'summer' => ['plage', 'coastal', 'famille', 'randonnée'],
            'spring' => ['randonnée', 'montagne', 'aventure', 'forêt'],
            'autumn' => ['randonnée', 'forêt', 'desert', 'solo'],
            default  => ['desert', 'montagne', 'glamping', 'groupe'],
        };
    }

    private function currentSeason(): string
    {
        $month = (int) now()->format('n');
        return match (true) {
            in_array($month, [6, 7, 8])   => 'summer',
            in_array($month, [3, 4, 5])   => 'spring',
            in_array($month, [9, 10, 11]) => 'autumn',
            default                        => 'winter',
        };
    }

    private function calcDemandLevel(int $bookings, int $favorites): string
    {
        $score = ($bookings * 3) + ($favorites * 1);
        return match (true) {
            $score >= 20 => 'peak',
            $score >= 10 => 'high',
            $score >= 4  => 'moderate',
            default      => 'low',
        };
    }

    private function buildRuleExplanation(string $direction, DemandSignal $signal, float $optimal): string
    {
        return match ($direction) {
            'increase' => "La demande actuelle ({$signal->demandLevel}) et la saison ({$signal->season}) justifient une augmentation vers {$optimal} TND. C'est le bon moment pour optimiser vos revenus.",
            'decrease' => "La demande est actuellement faible. Réduire temporairement votre prix à {$optimal} TND pourrait stimuler les réservations.",
            default    => "Votre prix actuel est bien positionné par rapport au marché. Maintenez-le et surveillez l'évolution de la demande.",
        };
    }

    private function enrichWithLLM(DemandSignal $signal, float $suggestedOptimal, string $priceDirection): ?string
    {
        try {
            $systemPrompt = 'You are a pricing advisor for TunisiaCamp, a Tunisian outdoor camping marketplace. '
                . 'Given demand signals for a listing, write a 2-3 sentence pricing recommendation in French '
                . 'addressed directly to the supplier. Be specific about the suggested price and why. '
                . 'Be encouraging and professional. '
                . 'Respond with ONLY the explanation text — no JSON, no markdown.';

            $userMsg = json_encode([
                'demand_signal'    => $signal->toArray(),
                'current_price'    => $signal->currentPrice,
                'suggested_optimal'=> $suggestedOptimal,
                'price_direction'  => $priceDirection,
            ], JSON_UNESCAPED_UNICODE);

            $raw = trim($this->llm->complete($systemPrompt, $userMsg, 350));
            return $raw !== '' ? $raw : null;
        } catch (\Throwable $e) {
            Log::warning('pricing_llm_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Fallback DTOs (never throw) ───────────────────────────────────────────

    private function fallbackDemandSignal(int $entityId, string $entityType): DemandSignal
    {
        return new DemandSignal(
            entityId:         $entityId,
            entityType:       $entityType,
            recentBookings:   0,
            recentFavorites:  0,
            avgRating:        0.0,
            reviewCount:      0,
            avgCategoryPrice: 0.0,
            currentPrice:     0.0,
            demandLevel:      'low',
            trendingTags:     $this->seasonalDefaultTags(),
            season:           $this->currentSeason(),
        );
    }

    private function fallbackSuggestion(int $entityId, string $entityType): PricingSuggestion
    {
        $signal = $this->fallbackDemandSignal($entityId, $entityType);
        return new PricingSuggestion(
            entityId:         $entityId,
            entityType:       $entityType,
            currentPrice:     0.0,
            suggestedMin:     0.0,
            suggestedMax:     0.0,
            suggestedOptimal: 0.0,
            demandLevel:      'low',
            confidenceScore:  0.1,
            priceDirection:   'maintain',
            explanation:      'Données insuffisantes pour générer une suggestion fiable.',
            actionItems:      ['Ajoutez des informations supplémentaires pour améliorer les suggestions.'],
            llmEnriched:      false,
            demandSignal:     $signal,
            generatedAt:      now()->toISOString(),
        );
    }
}
