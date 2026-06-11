<?php

namespace App\Services\AI;

use App\Models\CampingZone;
use App\Models\ProfileCampeur;
use App\Services\AI\Adapters\LLMAdapterInterface;
use App\Services\AI\Safety\ModerationResult;
use App\Services\AI\Safety\RiskFactor;
use App\Services\AI\Safety\SafetyAssessment;
use App\Services\AI\Weather\WeatherForecast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SafetyService
{
    private const SEVERITY_WEIGHTS = [
        'low'      => 5,
        'moderate' => 15,
        'high'     => 30,
        'extreme'  => 50,
    ];

    private const REJECTED_KEYWORDS = [
        'arnaque', 'faux', 'illégal', 'contrebande', 'frauduleux',
        'scam', 'fake', 'illegal', 'fraud',
    ];

    public function __construct(
        private readonly LLMAdapterInterface $llm,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════════
    // Sub-system A — Trip Safety Assessment
    // ═══════════════════════════════════════════════════════════════════════════

    public function assessTripSafety(
        ProfileCampeur   $profile,
        CampingZone      $zone,
        ?WeatherForecast $forecast = null,
        int              $groupSize = 1,
    ): SafetyAssessment {
        try {
            return $this->buildAssessment($profile, $zone, $forecast, $groupSize);
        } catch (\Throwable $e) {
            Log::error('safety_assessment_failed', [
                'zone_id'    => $zone->id,
                'profile_id' => $profile->profile_id ?? null,
                'error'      => $e->getMessage(),
            ]);
            return new SafetyAssessment(
                score:          0,
                label:          'safe',
                factors:        [],
                summary:        'Évaluation de sécurité indisponible.',
                blocks_booking: false,
                llm_enriched:   false,
                assessed_at:    now()->toIso8601String(),
            );
        }
    }

    public function getQuickRiskLabel(CampingZone $zone): string
    {
        $danger     = $zone->danger_level ?? 'low';
        $difficulty = $zone->difficulty   ?? 'easy';

        if ($danger === 'extreme' || $difficulty === 'hard') {
            return 'warning';
        }
        if ($danger === 'high') {
            return 'caution';
        }
        return 'safe';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Sub-system B — Content Moderation
    // ═══════════════════════════════════════════════════════════════════════════

    public function moderateContent(
        string $title,
        string $description,
        string $category,
        float  $price,
        string $contentType = 'listing',
    ): ModerationResult {
        try {
            return $this->runModeration($title, $description, $category, $price, $contentType);
        } catch (\Throwable $e) {
            Log::error('moderation_failed', ['error' => $e->getMessage()]);
            return new ModerationResult(
                status:        'flagged',
                reasons:       ['Erreur système — vérification manuelle requise'],
                suggestions:   [],
                confidence:    0.0,
                llm_moderated: false,
                content_hash:  md5($title . $description . $price),
                moderated_at:  now()->toIso8601String(),
            );
        }
    }

    public function getModerationStats(): array
    {
        return [
            'total_moderated' => (int) Cache::get('moderation:stats:total',        0),
            'approved'        => (int) Cache::get('moderation:stats:approved',     0),
            'flagged'         => (int) Cache::get('moderation:stats:flagged',      0),
            'rejected'        => (int) Cache::get('moderation:stats:rejected',     0),
            'llm_moderated'   => (int) Cache::get('moderation:stats:llm_moderated', 0),
        ];
    }

    // ── Private: assessment pipeline ─────────────────────────────────────────

    private function buildAssessment(
        ProfileCampeur   $profile,
        CampingZone      $zone,
        ?WeatherForecast $forecast,
        int              $groupSize,
    ): SafetyAssessment {
        $cacheKey = 'safety:trip:' . md5(
            ($profile->profile_id ?? 0) . $zone->id
            . ($forecast?->fetchedAt ?? 'no-weather')
            . $groupSize
        );

        return Cache::remember($cacheKey, 1800, function () use ($profile, $zone, $forecast, $groupSize) {
            $factors = [];

            $this->applySkillMismatchRules($profile, $zone, $factors);
            $this->applyDangerLevelRules($zone, $factors);
            $this->applySoloRiskRules($zone, $groupSize, $factors);
            $this->applyWeatherRiskRules($forecast, $factors);
            $this->applyComfortMismatchRules($profile, $zone, $factors);

            $score = $this->calculateScore($factors);
            $label = $this->scoreToLabel($score);

            // ── Optional: delegate the score/label to the Python model service ──
            // When ai.decision_backend = 'python', the trained safety classifier
            // (LightGBM) makes the call; the PHP factors/summary are kept. Any
            // failure falls back to the rule-based score above.
            $modelSource = 'rule_engine';
            $client = app(\App\Services\AI\AiInferenceClient::class);
            if ($client->enabled()) {
                $weather = $this->resolveWeatherLevel($forecast);
                $pred = $client->safety([
                    'skill'      => $profile->skill_level ?? 'beginner',
                    'difficulty' => $zone->difficulty ?? 'easy',
                    'danger'     => $zone->danger_level ?? 'low',
                    'group_size' => $groupSize,
                    'weather'    => $weather,
                    'comfort'    => $profile->comfort_level ?? 'standard',
                    'terrain'    => $zone->terrain_type ?? 'plain',
                ]);
                if ($pred !== null && isset($pred['label'], $pred['score'])) {
                    $label       = $pred['label'];
                    $score       = (int) $pred['score'];
                    $modelSource = 'python:' . ($pred['model'] ?? 'model');
                }
            }

            [$summary, $llmEnriched] = $this->buildSummary($label, $factors);

            $assessment = new SafetyAssessment(
                score:          $score,
                label:          $label,
                factors:        $factors,
                summary:        $summary,
                blocks_booking: false,
                llm_enriched:   $llmEnriched,
                assessed_at:    now()->toIso8601String(),
            );

            Log::info('safety_assessment', [
                'zone_id'         => $zone->id,
                'profile_id'      => $profile->profile_id ?? null,
                'score'           => $assessment->score,
                'label'           => $assessment->label,
                'factors'         => count($assessment->factors),
                'llm_enriched'    => $assessment->llm_enriched,
                'decision_source' => $modelSource,
                'cached'          => false,
            ]);

            return $assessment;
        });
    }

    // ── Rule engines ──────────────────────────────────────────────────────────

    private function applySkillMismatchRules(ProfileCampeur $profile, CampingZone $zone, array &$factors): void
    {
        $skill      = $profile->skill_level ?? 'beginner';
        $difficulty = $zone->difficulty     ?? 'easy';

        if ($difficulty === 'hard' && $skill === 'beginner') {
            $factors[] = new RiskFactor(
                code:        'SKILL_MISMATCH_CRITICAL',
                label:       'Niveau insuffisant',
                description: "Ce terrain est classé difficile. Votre niveau débutant représente un risque sérieux.",
                severity:    'extreme',
                source:      'zone',
                suggestion:  "Choisissez une zone classée 'facile' ou améliorez votre expérience avec des sorties encadrées.",
            );
        } elseif ($difficulty === 'hard' && $skill === 'intermediate') {
            $factors[] = new RiskFactor(
                code:        'SKILL_MISMATCH_MODERATE',
                label:       'Niveau limite',
                description: "Ce terrain difficile dépasse légèrement votre niveau actuel.",
                severity:    'moderate',
                source:      'zone',
                suggestion:  "Partez accompagné d'un guide ou d'un campeur expérimenté.",
            );
        } elseif ($difficulty === 'medium' && $skill === 'beginner') {
            $factors[] = new RiskFactor(
                code:        'SKILL_MISMATCH_LOW',
                label:       'Terrain intermédiaire',
                description: "Ce terrain est classé moyen. Restez prudent en tant que débutant.",
                severity:    'low',
                source:      'zone',
                suggestion:  "Prévenez quelqu'un de votre itinéraire avant de partir.",
            );
        }
    }

    private function applyDangerLevelRules(CampingZone $zone, array &$factors): void
    {
        $danger = $zone->danger_level ?? 'low';

        if ($danger === 'extreme') {
            $factors[] = new RiskFactor(
                code:        'ZONE_EXTREME_DANGER',
                label:       'Zone extrêmement dangereuse',
                description: "Cette zone est classée comme extrêmement dangereuse par les autorités locales.",
                severity:    'extreme',
                source:      'zone',
                suggestion:  "Cette sortie nécessite une expérience avancée et un équipement professionnel.",
            );
        } elseif ($danger === 'high') {
            $factors[] = new RiskFactor(
                code:        'ZONE_HIGH_DANGER',
                label:       'Zone à risque élevé',
                description: "Cette zone présente des risques naturels importants.",
                severity:    'high',
                source:      'zone',
                suggestion:  "Ne partez jamais seul. Informez les secours locaux de votre itinéraire.",
            );
        }
    }

    private function applySoloRiskRules(CampingZone $zone, int $groupSize, array &$factors): void
    {
        $danger = $zone->danger_level ?? 'low';

        if ($groupSize === 1 && in_array($danger, ['high', 'extreme'], true)) {
            $factors[] = new RiskFactor(
                code:        'SOLO_HIGH_RISK',
                label:       'Sortie solitaire dangereuse',
                description: "Partir seul dans une zone à risque élevé est fortement déconseillé.",
                severity:    'high',
                source:      'profile',
                suggestion:  "Rejoignez un groupe ou partagez votre localisation en temps réel.",
            );
        }
    }

    private function applyWeatherRiskRules(?WeatherForecast $forecast, array &$factors): void
    {
        if ($forecast === null) {
            return;
        }

        $priority = ['extreme' => 4, 'high' => 3, 'moderate' => 2, 'low' => 1];
        $maxLevel = 'low';
        $mainCondition = '';

        foreach ($forecast->daily as $day) {
            if (($priority[$day->riskLevel] ?? 0) > ($priority[$maxLevel] ?? 0)) {
                $maxLevel      = $day->riskLevel;
                $mainCondition = $day->mainCondition;
            }
        }

        if ($maxLevel === 'extreme') {
            $factors[] = new RiskFactor(
                code:        'WEATHER_EXTREME',
                label:       'Météo extrême',
                description: "Les prévisions météo indiquent des conditions extrêmes ({$mainCondition}) pendant votre séjour.",
                severity:    'extreme',
                source:      'weather',
                suggestion:  "Reportez votre sortie jusqu'à l'amélioration des conditions météo.",
            );
        } elseif ($maxLevel === 'high') {
            $factors[] = new RiskFactor(
                code:        'WEATHER_HIGH',
                label:       'Météo défavorable',
                description: "Des conditions météo défavorables ({$mainCondition}) sont prévues.",
                severity:    'high',
                source:      'weather',
                suggestion:  "Prévoyez un équipement imperméable et un plan d'évacuation d'urgence.",
            );
        } elseif ($maxLevel === 'moderate') {
            $factors[] = new RiskFactor(
                code:        'WEATHER_MODERATE',
                label:       'Météo à surveiller',
                description: "Quelques perturbations météo ({$mainCondition}) sont possibles.",
                severity:    'moderate',
                source:      'weather',
                suggestion:  "Emportez un imperméable et consultez les prévisions la veille.",
            );
        }
    }

    private function applyComfortMismatchRules(ProfileCampeur $profile, CampingZone $zone, array &$factors): void
    {
        $comfort = $profile->comfort_level ?? 'standard';
        $terrain = $zone->terrain_type     ?? 'plain';

        if ($comfort === 'glamping' && in_array($terrain, ['mountain', 'desert', 'wetland'], true)) {
            $factors[] = new RiskFactor(
                code:        'COMFORT_MISMATCH',
                label:       'Confort non adapté',
                description: "Vos préférences de confort (glamping) ne correspondent pas au type de terrain.",
                severity:    'low',
                source:      'profile',
                suggestion:  "Vérifiez que des hébergements adaptés sont disponibles dans cette zone.",
            );
        }
    }

    // ── Score + label ─────────────────────────────────────────────────────────

    private function calculateScore(array $factors): int
    {
        $sum = 0;
        foreach ($factors as $factor) {
            $sum += self::SEVERITY_WEIGHTS[$factor->severity] ?? 0;
        }
        return min($sum, 100);
    }

    private function scoreToLabel(int $score): string
    {
        if ($score <= 15) return 'safe';
        if ($score <= 35) return 'caution';
        if ($score <= 65) return 'warning';
        return 'danger';
    }

    /** Highest weather risk level across the forecast (for the Python model). */
    private function resolveWeatherLevel(?WeatherForecast $forecast): string
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

    // ── Summary: LLM or rule-based fallback ──────────────────────────────────

    private function buildSummary(string $label, array $factors): array
    {
        if (
            config('ai.features.safety') &&
            config('ai.provider') !== 'mock' &&
            ! empty($factors)
        ) {
            try {
                $factorJson = json_encode(
                    array_map(fn (RiskFactor $f) => $f->toArray(), $factors),
                    JSON_UNESCAPED_UNICODE
                );

                $systemPrompt = "You are a safety advisor for outdoor camping in Tunisia. "
                    . "Given the following risk factors, write a 2-3 sentence safety summary in French "
                    . "addressed directly to the camper. Be honest but not alarmist. End with one "
                    . "concrete action they should take before departing. "
                    . "Respond with ONLY the summary text — no JSON, no markdown.";

                $summary = trim($this->llm->complete($systemPrompt, $factorJson, 300));

                if (! empty($summary)) {
                    return [$summary, true];
                }
            } catch (\Throwable $e) {
                Log::warning('safety_llm_summary_failed', ['error' => $e->getMessage()]);
            }
        }

        return [$this->fallbackSummary($label), false];
    }

    private function fallbackSummary(string $label): string
    {
        return match ($label) {
            'safe'    => "Votre sortie semble bien adaptée à votre profil. Bonne randonnée !",
            'caution' => "Quelques points d'attention ont été détectés. Consultez les recommandations ci-dessous.",
            'warning' => "Cette sortie présente des risques notables. Prenez le temps de vous préparer soigneusement.",
            default   => "Attention : cette sortie est déconseillée dans votre configuration actuelle. Lisez attentivement les avertissements.",
        };
    }

    // ── Private: moderation pipeline ─────────────────────────────────────────

    private function runModeration(
        string $title,
        string $description,
        string $category,
        float  $price,
        string $contentType,
    ): ModerationResult {
        $contentHash = md5($title . $description . $price);
        $cacheKey    = 'moderation:' . $contentHash;

        return Cache::remember($cacheKey, 86400, function () use (
            $title, $description, $category, $price, $contentType, $contentHash
        ) {
            // Step 1 — Rejected keyword check
            $combinedText = strtolower($title . ' ' . $description);
            foreach (self::REJECTED_KEYWORDS as $keyword) {
                if (str_contains($combinedText, $keyword)) {
                    $result = new ModerationResult(
                        status:        'rejected',
                        reasons:       ['Contenu interdit détecté'],
                        suggestions:   [],
                        confidence:    1.0,
                        llm_moderated: false,
                        content_hash:  $contentHash,
                        moderated_at:  now()->toIso8601String(),
                    );
                    $this->incrementStats($result);
                    $this->logModeration($result, $contentType);
                    return $result;
                }
            }

            // Step 2 — Suspicious pattern detection
            $suspiciousFlags = [];

            if (mb_strlen($description) < 50) {
                $suspiciousFlags[] = 'Description trop courte';
            }
            if (mb_strlen($title) < 5) {
                $suspiciousFlags[] = 'Titre trop court';
            }
            if ($price === 0.0) {
                $suspiciousFlags[] = 'Prix invalide (zéro)';
            }
            if ($price > 500) {
                $suspiciousFlags[] = 'Prix suspect — vérification requise';
            }
            if (mb_strlen($description) > 0 && ! str_contains($description, ' ')) {
                $suspiciousFlags[] = 'Description invalide';
            }

            // Step 3 — Clean content: skip LLM entirely
            if (empty($suspiciousFlags)) {
                $result = new ModerationResult(
                    status:        'approved',
                    reasons:       [],
                    suggestions:   [],
                    confidence:    0.9,
                    llm_moderated: false,
                    content_hash:  $contentHash,
                    moderated_at:  now()->toIso8601String(),
                );
                $this->incrementStats($result);
                $this->logModeration($result, $contentType);
                return $result;
            }

            // Step 4 — LLM moderation for suspicious content
            if (config('ai.features.safety') && config('ai.provider') !== 'mock') {
                try {
                    $result = $this->runLLMModeration(
                        $title, $description, $category, $price,
                        $suspiciousFlags, $contentHash, $contentType
                    );
                    $this->incrementStats($result);
                    $this->logModeration($result, $contentType);
                    return $result;
                } catch (\Throwable $e) {
                    Log::warning('moderation_llm_failed', ['error' => $e->getMessage()]);
                }
            }

            // Fallback: return suspicious flags as rule-based flagged result
            $result = new ModerationResult(
                status:        'flagged',
                reasons:       $suspiciousFlags,
                suggestions:   ['Complétez la description et vérifiez les informations soumises.'],
                confidence:    0.5,
                llm_moderated: false,
                content_hash:  $contentHash,
                moderated_at:  now()->toIso8601String(),
            );
            $this->incrementStats($result);
            $this->logModeration($result, $contentType);
            return $result;
        });
    }

    private function runLLMModeration(
        string $title,
        string $description,
        string $category,
        float  $price,
        array  $suspiciousFlags,
        string $contentHash,
        string $contentType,
    ): ModerationResult {
        $systemPrompt = "You are a content moderator for TunisiaCamp, a Tunisian outdoor "
            . "camping marketplace. Review the following listing and respond ONLY with valid JSON:\n"
            . "{\n"
            . "  \"status\": \"approved|flagged|rejected\",\n"
            . "  \"reasons\": [\"French reason 1\"],\n"
            . "  \"suggestions\": [\"French improvement suggestion\"],\n"
            . "  \"confidence\": 0.0\n"
            . "}\n"
            . "Criteria for flagging: misleading descriptions, missing safety information for "
            . "outdoor equipment, incomplete details, unverifiable claims.\n"
            . "Criteria for rejection: prohibited content, fraud indicators, dangerous advice.\n"
            . "All reasons and suggestions must be in French.";

        $userMessage = json_encode([
            'title'           => mb_substr($title, 0, 200),
            'description'     => mb_substr($description, 0, 800),
            'category'        => $category,
            'price'           => $price,
            'suspicious_flags' => $suspiciousFlags,
        ], JSON_UNESCAPED_UNICODE);

        $raw     = $this->llm->complete($systemPrompt, $userMessage, 400);
        $raw     = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw     = preg_replace('/\s*```$/', '', $raw ?? '');
        $decoded = json_decode(trim($raw ?? ''), true, 512, JSON_THROW_ON_ERROR);

        $status = $decoded['status'] ?? 'flagged';
        if (! in_array($status, ['approved', 'flagged', 'rejected'], true)) {
            $status = 'flagged';
        }

        return new ModerationResult(
            status:        $status,
            reasons:       (array) ($decoded['reasons']     ?? $suspiciousFlags),
            suggestions:   (array) ($decoded['suggestions'] ?? []),
            confidence:    (float) ($decoded['confidence']  ?? 0.7),
            llm_moderated: true,
            content_hash:  $contentHash,
            moderated_at:  now()->toIso8601String(),
        );
    }

    // ── Observability helpers ─────────────────────────────────────────────────

    private function incrementStats(ModerationResult $result): void
    {
        Cache::increment('moderation:stats:total');
        Cache::increment('moderation:stats:' . $result->status);
        if ($result->llm_moderated) {
            Cache::increment('moderation:stats:llm_moderated');
        }
    }

    private function logModeration(ModerationResult $result, string $contentType): void
    {
        Log::info('content_moderation', [
            'status'        => $result->status,
            'content_type'  => $contentType,
            'llm_moderated' => $result->llm_moderated,
            'confidence'    => $result->confidence,
            'content_hash'  => $result->content_hash,
        ]);
    }
}
