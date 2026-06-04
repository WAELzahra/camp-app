<?php

namespace App\Services\AI;

use App\Services\AI\Adapters\LLMAdapterInterface;
use App\Services\AI\Explainability\Explanation;
use App\Services\AI\Gear\GearChecklist;
use App\Services\AI\Matching\GroupMatch;
use App\Services\AI\Pricing\PricingSuggestion;
use App\Services\AI\Safety\SafetyAssessment;
use App\Services\AI\Weather\WeatherForecast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExplainabilityService
{
    public function __construct(
        private readonly LLMAdapterInterface $llm,
    ) {}

    public function explainRecommendation(
        array  $scoreBreakdown,
        string $zoneName,
        string $skillLevel,
        float  $confidence = 0.84,
    ): Explanation {
        try {
            $factors = [];

            if (($scoreBreakdown['difficulty_match'] ?? 0) > 0) {
                $factors[] = "Niveau de difficulté adapté à votre expérience ({$skillLevel})";
            }
            if (($scoreBreakdown['terrain_match'] ?? 0) > 0) {
                $factors[] = "Type de terrain correspond à vos styles de voyage préférés";
            }
            if (($scoreBreakdown['beginner_friendly'] ?? 0) > 0) {
                $factors[] = "Zone recommandée pour les débutants";
            }
            if (($scoreBreakdown['activity_overlap'] ?? 0) > 0) {
                $factors[] = "Activités disponibles correspondent à vos préférences";
            }
            if (($scoreBreakdown['rating_bonus'] ?? 0) > 0) {
                $factors[] = "Note élevée de la communauté ({$zoneName})";
            }
            if (($scoreBreakdown['similar_users_booked'] ?? 0) > 0) {
                $factors[] = "Des campeurs avec un profil similaire ont réservé cette zone";
            }
            if (($scoreBreakdown['similar_users_rating'] ?? 0) > 0) {
                $factors[] = "Très bien noté par des campeurs ayant votre profil";
            }

            $count = count($factors);
            $why   = $count > 0
                ? "Cette zone est recommandée car elle correspond à {$count} critères de votre profil."
                : "Cette zone correspond à votre profil de campeur.";

            return new Explanation(
                why: $why,
                factors: $factors,
                confidence: max(0.1, min(1.0, $confidence)),
                source: 'recommendation',
                detailAvailable: ! empty($factors),
                llmEnriched: false,
            );
        } catch (\Throwable) {
            return Explanation::unavailable('recommendation');
        }
    }

    public function explainWeatherWarning(
        ?WeatherForecast $forecast,
        string           $riskLevel,
    ): Explanation {
        try {
            if ($forecast === null) {
                return Explanation::unavailable('weather');
            }

            $factors = [];
            foreach ($forecast->daily as $day) {
                if (in_array($day->riskLevel, ['high', 'extreme'], true)) {
                    $factors = $day->riskFactors;
                    break;
                }
            }

            $why = match ($riskLevel) {
                'extreme' => "Conditions météo dangereuses prévues — sortie fortement déconseillée.",
                'high'    => "Conditions météo défavorables prévues — équipement adapté obligatoire.",
                'moderate'=> "Quelques conditions météo à surveiller pour cette sortie.",
                default   => "Conditions météo favorables pour votre sortie.",
            };

            return new Explanation(
                why: $why,
                factors: $factors,
                confidence: $forecast->isMocked ? 0.6 : 0.9,
                source: 'weather',
                detailAvailable: ! empty($factors),
                llmEnriched: false,
            );
        } catch (\Throwable) {
            return Explanation::unavailable('weather');
        }
    }

    public function explainSafetyAssessment(SafetyAssessment $assessment): Explanation
    {
        try {
            $factors = array_map(
                fn ($f) => $f->description,
                $assessment->factors
            );

            return new Explanation(
                why: $assessment->summary,
                factors: $factors,
                confidence: max(0.0, 1.0 - ($assessment->score / 100)),
                source: 'safety',
                detailAvailable: ! empty($factors),
                llmEnriched: $assessment->llm_enriched,
            );
        } catch (\Throwable) {
            return Explanation::unavailable('safety');
        }
    }

    public function explainGearSuggestion(
        GearChecklist $checklist,
        string        $terrainType,
        string        $riskLevel,
    ): Explanation {
        try {
            $factors = ["Équipements sélectionnés pour terrain {$terrainType}"];

            if (in_array($riskLevel, ['high', 'extreme'], true)) {
                $factors[] = "Équipement de sécurité renforcé recommandé vu les conditions météo";
            }
            if (! empty($checklist->missing_critical)) {
                $factors[] = "⚠️ Certains équipements critiques ne sont pas disponibles";
            }
            $factors[] = "Liste personnalisée selon votre niveau {$checklist->skill_level}";

            return new Explanation(
                why: "Cette liste d'équipements a été générée spécifiquement pour votre profil et les conditions de la zone.",
                factors: $factors,
                confidence: $checklist->llm_enriched ? 0.9 : 0.75,
                source: 'gear',
                detailAvailable: true,
                llmEnriched: $checklist->llm_enriched,
            );
        } catch (\Throwable) {
            return Explanation::unavailable('gear');
        }
    }

    public function explainGroupMatch(GroupMatch $match): Explanation
    {
        try {
            return new Explanation(
                why: $match->whyExplanation,
                factors: $match->sharedTraits,
                confidence: $match->similarityScore,
                source: 'group',
                detailAvailable: ! empty($match->sharedTraits),
                llmEnriched: $match->llmEnriched,
            );
        } catch (\Throwable) {
            return Explanation::unavailable('group');
        }
    }

    public function explainPricingSuggestion(PricingSuggestion $suggestion): Explanation
    {
        try {
            return new Explanation(
                why: $suggestion->explanation,
                factors: $suggestion->actionItems,
                confidence: $suggestion->confidenceScore,
                source: 'pricing',
                detailAvailable: ! empty($suggestion->actionItems),
                llmEnriched: $suggestion->llmEnriched,
            );
        } catch (\Throwable) {
            return Explanation::unavailable('pricing');
        }
    }

    public function explainOnDemand(
        string $context,
        string $source,
        array  $data,
    ): Explanation {
        if (! config('ai.features.explainability') || config('ai.provider') === 'mock') {
            return Explanation::unavailable($source);
        }

        $cacheKey = 'explain:ondemand:' . md5($context . $source . json_encode($data));

        return Cache::remember($cacheKey, 3600, function () use ($context, $source, $data) {
            try {
                $systemPrompt = <<<PROMPT
You are an AI explainability assistant for TunisiaCamp, a Tunisian outdoor
camping platform. Given data about an AI decision, explain in 1-2 sentences
in French why this result was produced. Be clear, honest, and helpful.
Address the user directly (use "vous").
Respond ONLY with a JSON object:
{
  "why": "1 sentence main explanation",
  "factors": ["factor 1", "factor 2", "factor 3"]
}
PROMPT;

                $userMessage = "Context: {$context}\nSource: {$source}\nData: " . json_encode($data);

                $raw      = $this->llm->complete($systemPrompt, $userMessage, 300);
                $decoded  = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                $why     = $decoded['why'] ?? '';
                $factors = $decoded['factors'] ?? [];

                if (empty($why)) {
                    return Explanation::unavailable($source);
                }

                Log::info('explanation_generated', [
                    'source'       => $source,
                    'llm_enriched' => true,
                    'cached'       => false,
                ]);

                return new Explanation(
                    why: $why,
                    factors: is_array($factors) ? $factors : [],
                    confidence: 0.8,
                    source: $source,
                    detailAvailable: ! empty($factors),
                    llmEnriched: true,
                );
            } catch (\Throwable) {
                return Explanation::unavailable($source);
            }
        });
    }
}
