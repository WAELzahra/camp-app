<?php

namespace App\Services\AI;

use App\Models\ProfileCampeur;
use App\Services\AI\Adapters\LLMAdapterInterface;
use Illuminate\Support\Facades\Log;

/**
 * CALL 1 of the trip-planner pipeline.
 *
 * Turns a free-text user message into a structured intent. The LLM does the
 * extraction when a real provider is configured; a deterministic rule-based
 * engine is always computed first and used as the authoritative fallback so the
 * planner keeps working with zero API access (and under the mock provider,
 * which never returns intent-shaped JSON).
 *
 * PHP makes every downstream decision from this intent — the LLM never picks a
 * zone/centre, gear, or cost.
 */
class IntentExtractorService
{
    private const ACCOMMODATION_TYPES = ['zone', 'centre', 'any'];
    private const BUDGETS             = ['budget', 'moderate', 'premium'];

    public function __construct(
        private readonly LLMAdapterInterface $llm,
    ) {}

    /**
     * @return array{
     *   destination:string, budget:string, group_size:int,
     *   duration_nights:int, trip_style:string, accommodation_type:string
     * }
     */
    public function extract(string $message, array $history = [], ?ProfileCampeur $profile = null): array
    {
        $fallback = $this->ruleBasedIntent($message, $history, $profile);

        // The mock provider cannot produce intent-shaped JSON, and a disabled
        // planner must not spend tokens — use the deterministic extraction.
        if (config('ai.provider') === 'mock' || ! config('ai.features.trip_planner')) {
            return $fallback;
        }

        try {
            $raw = $this->llm->complete(
                $this->systemPrompt(),
                $this->userPayload($message, $history),
                300,
                $history,
            );
            $decoded = $this->decodeJson($raw);
            if (is_array($decoded)) {
                return $this->reconcile($fallback, $decoded);
            }
        } catch (\Throwable $e) {
            Log::warning('intent_extraction_failed', ['error' => $e->getMessage()]);
        }

        return $fallback;
    }

    // ── CALL 1 prompt ───────────────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<PROMPT
You extract structured trip intent for a Tunisian camping assistant.
Respond with ONLY a raw JSON object — no markdown, no prose. First char {, last char }.

Schema (all keys required):
{
  "destination": "Tunisian region/city mentioned, or empty string",
  "budget": "budget | moderate | premium",
  "group_size": <integer, number of people, default 2>,
  "duration_nights": <integer, number of nights, default 2>,
  "trip_style": "desert | nature | coastal | famille | aventure | détente",
  "accommodation_type": "zone | centre | any",
  "post_recommendation_action": null
}

accommodation_type detection rules:
- "centre": user mentions centre, center, hébergement, équipé, bungalow, auberge,
  douches, wifi, names a known centre, or answers with partner/external choice.
- "zone": user mentions sauvage, wild, nature, bivouac, "tente sous les étoiles".
- "any": nothing accommodation-specific is said.

Map weekend → 2 nights, semaine/week → 7 nights. Never invent a destination.

Tunisia has five camping regions (use the region name as destination):
  Nord:        Jendouba, Tabarka, Ain Draham, Béja, Siliana
  Grand Tunis: Tunis, Ariana, Ben Arous, Manouba, Zaghouan
  Côte Est:    Sousse, Nabeul, Hammamet, Monastir, Mahdia
  Centre:      Kairouan, Sfax, Sidi Bouzid, Gafsa
  Sud:         Tozeur, Douz, Djerba, Gabès, Médenine, Tataouine, Kébili
PROMPT;
    }

    private function userPayload(string $message, array $history): string
    {
        $context = '';
        if (! empty($history)) {
            $turns = array_map(
                fn ($h) => ($h['role'] ?? '') . ': ' . ($h['content'] ?? ''),
                array_slice($history, -6),
            );
            $context = "Conversation so far:\n" . implode("\n", $turns) . "\n\n";
        }

        return $context . 'Current message: ' . $message;
    }

    // ── Rule-based extraction (always-on fallback) ──────────────────────────────

    private function ruleBasedIntent(string $message, array $history, ?ProfileCampeur $profile): array
    {
        $combined = mb_strtolower(
            implode(' ', array_column($history, 'content')) . ' ' . $message
        );
        $lowerMsg = mb_strtolower($message);

        return [
            'destination'                => '', // authoritative region detection lives in TripPlannerService
            'budget'                     => $this->detectBudget($combined, $profile),
            'group_size'                 => $this->detectGroupSize($lowerMsg),
            'duration_nights'            => $this->detectDurationNights($lowerMsg),
            'trip_style'                 => $this->detectTripStyle($combined),
            'accommodation_type'         => $this->detectAccommodation($combined),
            'post_recommendation_action' => null,  // set by ConfirmationClassifierService, never by LLM
        ];
    }

    private function detectGroupSize(string $text): int
    {
        if (preg_match('/(\d+)\s*(personnes?|people|amis?|friends?|adultes?|adults?)/i', $text, $m)) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('/(?:groupe de|group of)\s*(\d+)/i', $text, $m)) {
            return max(1, (int) $m[1]);
        }
        return 2;
    }

    private function detectDurationNights(string $text): int
    {
        if (preg_match('/(\d+)\s*(nuits?|nights?|jours?|days?|soirs?)/i', $text, $m)) {
            return max(1, (int) $m[1]);
        }
        if (str_contains($text, 'weekend') || str_contains($text, 'week-end')) {
            return 2;
        }
        if (str_contains($text, 'semaine') || str_contains($text, 'week')) {
            return 7;
        }
        return 2;
    }

    private function detectBudget(string $text, ?ProfileCampeur $profile): string
    {
        $premium = ['premium', 'luxe', 'luxury', 'haut de gamme', 'glamping', 'confort maximum'];
        $budget  = ['pas cher', 'économique', 'economique', 'petit budget', 'cheap', 'low cost', 'budget'];

        foreach ($premium as $kw) {
            if (str_contains($text, $kw)) {
                return 'premium';
            }
        }
        foreach ($budget as $kw) {
            if (str_contains($text, $kw)) {
                return 'budget';
            }
        }
        if (str_contains($text, 'modéré') || str_contains($text, 'moderate') || str_contains($text, 'standard')) {
            return 'moderate';
        }

        return $profile->budget_range ?? 'moderate';
    }

    private function detectTripStyle(string $text): string
    {
        $map = [
            'desert'  => ['desert', 'désert', 'sahara', 'dune'],
            'nature'  => ['forêt', 'foret', 'forest', 'montagne', 'mountain'],
            'coastal' => ['plage', 'beach', 'côtier', 'cotier', 'coastal', 'mer', 'balnéaire', 'balneaire'],
            'famille' => ['famille', 'family', 'enfants', 'kids'],
            'aventure'=> ['aventure', 'adventure'],
            'détente' => ['détente', 'detente', 'relax', 'repos'],
        ];

        foreach ($map as $style => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    return $style;
                }
            }
        }

        return 'aventure';
    }

    private function detectAccommodation(string $text): string
    {
        // Wild-zone signals take priority when explicit.
        $zone = ['sauvage', 'wild', 'bivouac', 'tente sous les étoiles', 'tente sous les etoiles', 'pleine nature'];
        foreach ($zone as $kw) {
            if (str_contains($text, $kw)) {
                return 'zone';
            }
        }

        $centre = [
            'centre', 'center', 'hébergement', 'hebergement', 'équipé', 'equipe',
            'bungalow', 'auberge', 'douche', 'wifi',
            // partner-choice continuations from the clarification flow
            'partenaire', 'réservable', 'reservable', 'externe',
            'partner_only', 'external_only', 'any_centre',
        ];
        foreach ($centre as $kw) {
            if (str_contains($text, $kw)) {
                return 'centre';
            }
        }

        // Default to wild-zone planning (the platform's core flow); the LLM may
        // still emit "any" for genuinely ambiguous messages.
        return 'zone';
    }

    // ── LLM response reconciliation ─────────────────────────────────────────────

    private function reconcile(array $fallback, array $decoded): array
    {
        $accommodation = $decoded['accommodation_type'] ?? null;
        if (! in_array($accommodation, self::ACCOMMODATION_TYPES, true)) {
            $accommodation = $fallback['accommodation_type'];
        }

        $budget = $decoded['budget'] ?? null;
        if (! in_array($budget, self::BUDGETS, true)) {
            $budget = $fallback['budget'];
        }

        $groupSize = isset($decoded['group_size']) ? (int) $decoded['group_size'] : 0;
        $nights    = isset($decoded['duration_nights']) ? (int) $decoded['duration_nights'] : 0;

        $tripStyle = $decoded['trip_style'] ?? '';
        $tripStyle = is_string($tripStyle) && $tripStyle !== '' ? $tripStyle : $fallback['trip_style'];

        $destination = $decoded['destination'] ?? '';
        $destination = is_string($destination) ? trim($destination) : '';

        return [
            'destination'                => $destination !== '' ? $destination : $fallback['destination'],
            'budget'                     => $budget,
            'group_size'                 => $groupSize >= 1 ? $groupSize : $fallback['group_size'],
            'duration_nights'            => $nights >= 1 ? $nights : $fallback['duration_nights'],
            'trip_style'                 => $tripStyle,
            'accommodation_type'         => $accommodation,
            'post_recommendation_action' => null,  // always null; classifier sets this, not LLM
        ];
    }

    private function decodeJson(string $raw): ?array
    {
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $content = preg_replace('/\s*```$/', '', $content ?? '');
        $content = trim($content ?? '');

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $first = strpos($content, '{');
            $last  = strrpos($content, '}');
            if ($first !== false && $last !== false && $last > $first) {
                $decoded = json_decode(substr($content, $first, $last - $first + 1), true);
            }
        }

        return is_array($decoded) ? $decoded : null;
    }
}
