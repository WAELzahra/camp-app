<?php

namespace App\Services\AI;

use App\Models\ProfileCampeur;
use App\Services\AI\Adapters\LLMAdapterInterface;
use Illuminate\Support\Facades\Log;

/**
 * LLM-driven dialogue manager (slot-filling state machine).
 *
 * Instead of regex-gating raw history every turn, this asks the LLM to read the
 * whole conversation and return the CURRENT structured state (slots) plus the
 * next action. The LLM natively handles typos ("touzer" → Tozeur), language,
 * "I changed my mind", and context — things a keyword tree cannot.
 *
 * PHP then enforces hard invariants (region resolution, what's required to plan)
 * so the flow is deterministic and never silently guesses a destination.
 */
class ConversationManager
{
    private const ACCOMMODATIONS = ['zone', 'centre', 'any'];
    private const PARTNER_CHOICES = ['partner_only', 'external_only', 'any_centre'];
    private const BUDGETS = ['budget', 'moderate', 'premium'];
    private const TERRAINS = ['forest', 'mountain', 'desert', 'coastal', 'wetland', 'plain'];

    /** keyword (lowercase) → canonical region label used for DB filtering. */
    private const REGION_MAP = [
        'ain draham' => 'Jendouba', 'aindraham' => 'Jendouba', 'kroumirie' => 'Jendouba',
        'tabarka' => 'Jendouba', 'tabarca' => 'Jendouba', 'jendouba' => 'Jendouba',
        'jandouba' => 'Jendouba', 'bni mtir' => 'Jendouba', 'bnimtir' => 'Jendouba',
        'hammamet' => 'Nabeul', 'nabeul' => 'Nabeul', 'kelibia' => 'Nabeul', 'kélibia' => 'Nabeul',
        'ichkeul' => 'Bizerte', 'bizerte' => 'Bizerte', 'binzert' => 'Bizerte', 'benzert' => 'Bizerte',
        'sousse' => 'Sousse', 'monastir' => 'Monastir', 'mahdia' => 'Mahdia', 'sfax' => 'Sfax',
        'kairouan' => 'Kairouan', 'zaghouan' => 'Zaghouan', 'siliana' => 'Siliana',
        'beja' => 'Béja', 'béja' => 'Béja', 'kasserine' => 'Kasserine', 'sidi bouzid' => 'Sidi Bouzid',
        'gafsa' => 'Gafsa', 'gabes' => 'Gabès', 'gabès' => 'Gabès', 'tataouine' => 'Tataouine',
        'kebili' => 'Kébili', 'kébili' => 'Kébili', 'douz' => 'Kébili', 'tozeur' => 'Tozeur',
        'nefta' => 'Tozeur', 'djerba' => 'Médenine', 'jerba' => 'Médenine', 'medenine' => 'Médenine',
        'médenine' => 'Médenine', 'ariana' => 'Ariana', 'manouba' => 'Manouba',
        'ben arous' => 'Ben Arous', 'tunis' => 'Tunis', 'ksar ghilane' => 'Tataouine',
        // Arabic
        'جندوبة' => 'Jendouba', 'طبرقة' => 'Jendouba', 'عين دراهم' => 'Jendouba',
        'بنزرت' => 'Bizerte', 'تونس' => 'Tunis', 'نابل' => 'Nabeul', 'سوسة' => 'Sousse',
        'صفاقس' => 'Sfax', 'القيروان' => 'Kairouan', 'توزر' => 'Tozeur', 'دوز' => 'Kébili',
        'قابس' => 'Gabès', 'مدنين' => 'Médenine', 'جربة' => 'Médenine', 'باجة' => 'Béja',
        'زغوان' => 'Zaghouan',
    ];

    public function __construct(
        private readonly LLMAdapterInterface $llm,
    ) {}

    /**
     * Returns the full dialogue state for this turn.
     *
     * @return array{
     *   language:string, intent:string, destination:?string, region:?string,
     *   accommodation_type:?string, partner_choice:?string, group_size:?int,
     *   nights:?int, budget:?string, terrain:?string, gear_wanted:?bool,
     *   details_provided:bool, next_action:string, ask:?string, reply:?string
     * }
     */
    /**
     * Understand the current user turn and return a turn-state array.
     *
     * $persistedState is the caller's canonical per-user conversation state.
     * It is used inside finalize() so next_action can account for slots that
     * were set in earlier turns without scanning raw history text.
     *
     * The returned array contains:
     *   - per-turn slots (destination, group_size, nights, …) — null when not
     *     mentioned this turn, so the caller can build a non-destructive delta
     *   - is_new_trip: true when the user explicitly signals a fresh start
     *   - region: resolved canonical region (turn value ?? persisted value)
     *   - next_action / ask / reply — ephemeral, not persisted
     */
    public function understand(
        ProfileCampeur $profile,
        string         $message,
        array          $history        = [],
        array          $persistedState = [],
    ): array {
        $state = $this->ruleBasedUnderstand($message, $history);

        if (config('ai.provider') !== 'mock' && config('ai.features.trip_planner', true)) {
            try {
                $raw     = $this->llm->complete($this->systemPrompt(), $this->payload($message, $history), 450, $history);
                $decoded = $this->decodeJson($raw);
                if (is_array($decoded)) {
                    $state = $this->mergeLlm($state, $decoded);
                }
            } catch (\Throwable $e) {
                Log::warning('conversation_understand_failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->finalize($state, $profile, $persistedState);
    }

    // ── LLM understanding ───────────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are the dialogue manager for TunisiaCamp, a camping trip planner in Tunisia.
Read the conversation and the latest user message, then output ONLY a JSON object — no markdown, no prose.

Known Tunisian regions (correct misspellings to these): Jendouba (Tabarka, Aïn Draham), Bizerte,
Tunis, Nabeul (Hammamet, Kélibia), Zaghouan, Béja, Sousse, Monastir, Mahdia, Sfax, Kairouan,
Kasserine, Sidi Bouzid, Gafsa, Tozeur (Nefta), Kébili (Douz), Gabès, Médenine (Djerba),
Tataouine (Ksar Ghilane), Siliana, Ariana, Manouba, Ben Arous.

Output JSON:
{
  "language": "fr|en|ar",
  "intent": "plan_trip|question|greeting|smalltalk|other",
  "destination": "<Tunisian region/city the user wants, spelling-corrected, or null>",
  "accommodation_type": "zone|centre|any|null",
  "partner_choice": "partner_only|external_only|any_centre|null",
  "group_size": <int or null>,
  "nights": <int or null>,
  "budget": "budget|moderate|premium|null",
  "terrain": "forest|mountain|desert|coastal|wetland|plain|null",
  "gear_wanted": true | false | null,
  "question_target": "<exact name of the zone or centre the user is asking ABOUT, or 'current' for the one just recommended, or null>",
  "is_new_trip": true | false,
  "next_action": "ask|plan|chat|answer",
  "reply": "<short friendly message in the user's language, ONLY when next_action is ask or chat>"
}

Meaning:
- accommodation_type: "zone" = wild/nature camping spot; "centre" = equipped camping centre.
- partner_choice only applies when accommodation_type = "centre".
- intent "question" = the user asks ABOUT a place ("tell me about X", "what about that centre", "c'est quoi cette zone") rather than asking to plan a trip.

Rules:
1. Correct typos: "touzer"->Tozeur, "binzert"->Bizerte, "jandouba"->Jendouba, "gamping"->camping.
2. If the user changes destination or mind, REPLACE old slots with the newest request.
2b. is_new_trip=true ONLY when the user EXPLICITLY signals a fresh start: "nouveau voyage", "new trip", "recommencer", "start over", "start fresh", "another trip", "repartir de zéro", "رحلة جديدة". Changing destination alone is NOT a new trip.
3. If the user ASKS ABOUT a specific zone/centre -> intent="question", set question_target (use the exact name they mention, or "current" if they say "that one"/"it"), next_action="answer". Do NOT plan.
4. To plan you need destination AND accommodation_type (zone or centre). If centre, also partner_choice.
5. group_size/nights/budget are optional — never block planning on them.
6. Missing destination -> next_action="ask" (ask where in Tunisia).
7. Missing accommodation_type -> next_action="ask" (wild nature zone or equipped centre? you may also ask terrain/equipment in one short message).
8. accommodation_type="centre" and partner_choice missing -> next_action="ask" (partner centre, bookable, vs external, info only).
9. Greetings / thanks / small talk -> next_action="chat" with a brief friendly reply.
10. Everything needed is present -> next_action="plan".
11. Always write "reply" in the user's language. Keep it to one or two sentences.
PROMPT;
    }

    private function payload(string $message, array $history): string
    {
        $lines = [];
        foreach (array_slice($history, -8) as $h) {
            $role = ($h['role'] ?? '') === 'assistant' ? 'Assistant' : 'User';
            $lines[] = $role . ': ' . ($h['content'] ?? '');
        }
        $convo = $lines ? "Conversation so far:\n" . implode("\n", $lines) . "\n\n" : '';

        return $convo . 'Latest user message: ' . $message;
    }

    private function mergeLlm(array $fallback, array $d): array
    {
        $pickEnum = function ($v, array $allowed, $default) {
            return in_array($v, $allowed, true) ? $v : $default;
        };

        $intLike = fn ($v) => (is_int($v) || (is_string($v) && ctype_digit($v))) && (int) $v >= 1 ? (int) $v : null;

        return [
            'language'           => $pickEnum($d['language'] ?? null, ['fr', 'en', 'ar'], $fallback['language']),
            'intent'             => $pickEnum($d['intent'] ?? null, ['plan_trip', 'question', 'greeting', 'smalltalk', 'other'], $fallback['intent']),
            'question_target'    => isset($d['question_target']) && is_string($d['question_target']) && trim($d['question_target']) !== ''
                                        ? trim($d['question_target']) : ($fallback['question_target'] ?? null),
            'destination'        => isset($d['destination']) && is_string($d['destination']) && trim($d['destination']) !== ''
                                        ? trim($d['destination']) : $fallback['destination'],
            'accommodation_type' => $pickEnum($d['accommodation_type'] ?? null, self::ACCOMMODATIONS, $fallback['accommodation_type']),
            'partner_choice'     => $pickEnum($d['partner_choice'] ?? null, self::PARTNER_CHOICES, $fallback['partner_choice']),
            'group_size'         => $intLike($d['group_size'] ?? null) ?? $fallback['group_size'],
            'nights'             => $intLike($d['nights'] ?? null) ?? $fallback['nights'],
            'budget'             => $pickEnum($d['budget'] ?? null, self::BUDGETS, $fallback['budget']),
            'terrain'            => $pickEnum($d['terrain'] ?? null, self::TERRAINS, $fallback['terrain']),
            'gear_wanted'        => array_key_exists('gear_wanted', $d) && is_bool($d['gear_wanted']) ? $d['gear_wanted'] : $fallback['gear_wanted'],
            'is_new_trip'        => isset($d['is_new_trip']) && $d['is_new_trip'] === true ? true : ($fallback['is_new_trip'] ?? false),
            'next_action'        => $pickEnum($d['next_action'] ?? null, ['ask', 'plan', 'chat'], $fallback['next_action']),
            'reply'              => isset($d['reply']) && is_string($d['reply']) && trim($d['reply']) !== '' ? trim($d['reply']) : null,
            'details_provided'   => $fallback['details_provided'],
        ];
    }

    // ── Deterministic invariants ────────────────────────────────────────────────

    /**
     * Apply hard invariants for next_action using both the turn state and the
     * persisted state so turns that only partially specify the intent (e.g. just
     * "with gear please" with no destination) correctly inherit earlier values.
     *
     * This is the ONLY place that decides next_action — the LLM's suggestion is
     * over-written here, so the pipeline stays deterministic regardless of LLM output.
     */
    private function finalize(array $s, ProfileCampeur $profile, array $persisted = []): array
    {
        // Region: turn wins if it has one, else fall back to persisted destination
        // (which is already a resolved canonical region).
        $turnRegion  = $this->resolveRegion($s['destination']);
        $s['region'] = $turnRegion ?? ($persisted['destination'] ?? null);
        $s['budget'] = $s['budget'] ?? ($profile->budget_range ?: 'moderate');
        $s['ask']    = null;

        // Effective accommodation: turn value (if concrete) overrides persisted.
        $turnAccom  = in_array($s['accommodation_type'] ?? null, ['zone', 'centre'], true)
            ? $s['accommodation_type']
            : null;
        $effectiveAccom = $turnAccom
            ?? (in_array($persisted['accommodation_type'] ?? 'any', ['zone', 'centre'], true)
                ? $persisted['accommodation_type']
                : null);

        // Effective partner choice: turn value overrides persisted.
        $effectivePartner = $s['partner_choice']
            ?? ($persisted['partner_choice'] ?? null);
        // ── Platform FAQ detection ──────────────────────────────────────────────
        if ($s['intent'] === 'other' || $s['intent'] === 'question' || $s['intent'] === 'plan_trip') {
            $faq = $this->isPlatformFaq($s, $persisted);
            if ($faq) {
                $s['next_action'] = 'platform_faq';
                $s['reply'] = null;
                return $s;  // skip all destination/accommodation logic
            }
        }
        // Hard invariants — in priority order.
        if ($s['intent'] === 'question' && ($s['question_target'] ?? null) !== null) {
            $s['next_action'] = 'answer';
        } elseif (in_array($s['intent'], ['greeting', 'smalltalk'], true)) {
            $s['next_action'] = 'chat';
        } elseif ($s['region'] === null) {
            $s['next_action'] = 'ask';
            $s['ask']         = 'destination';
        } elseif ($effectiveAccom === null) {
            $s['next_action'] = 'ask';
            $s['ask']         = 'accommodation';
        } elseif ($effectiveAccom === 'centre' && $effectivePartner === null) {
            $s['next_action'] = 'ask';
            $s['ask']         = 'partner_choice';
        } else {
            $s['next_action'] = 'plan';
            $s['ask']         = null;
        }

        return $s;
    }
    private function isPlatformFaq(array $s, array $persisted): bool
    {
        $destination = $s['destination'] ?? $persisted['destination'] ?? null;
        
        // If user specified a destination, it's a trip plan, not a FAQ
        if ($destination !== null) {
            return false;
        }
        
        // If user wants to camp or plan, let the normal flow handle it
        if ($s['intent'] === 'plan_trip' && $s['destination'] !== null) {
            return false;
        }
        
        return true; 
    }
    /**
     * Resolve a free-text destination to a canonical region. Exact/substring
     * first, then a fuzzy pass (Levenshtein) so typos like "touzer"/"binzert"
     * still land — the safety net behind the LLM's own correction.
     */
    public function resolveRegion(?string $destination): ?string
    {
        if ($destination === null) {
            return null;
        }
        $needle = mb_strtolower(trim($destination));
        if ($needle === '') {
            return null;
        }

        foreach (self::REGION_MAP as $keyword => $region) {
            if (str_contains($needle, $keyword) || str_contains($keyword, $needle)) {
                return $region;
            }
        }

        // Fuzzy: compare each whitespace token against known keywords. Kept tight
        // (≤2 edits, near-equal length) and stop-listed so ordinary words like
        // "center", "camping" or "nature" never get mistaken for a region.
        $stop = ['center', 'centre', 'camping', 'zone', 'zones', 'nature', 'naturelle',
                 'equipped', 'about', 'that', 'this', 'with', 'want', 'people', 'nights',
                 'budget', 'gear', 'plan', 'wild', 'tente', 'tent'];

        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach (preg_split('/\s+/', $needle) as $token) {
            if (mb_strlen($token) < 4 || in_array($token, $stop, true)) {
                continue;
            }
            foreach (self::REGION_MAP as $keyword => $region) {
                if (! preg_match('/^[a-z]/', $keyword)) {
                    continue; // skip Arabic keys for Latin fuzzy
                }
                if (abs(mb_strlen($token) - mb_strlen($keyword)) > 1) {
                    continue; // lengths must be close
                }
                $dist = levenshtein($token, $keyword);
                $tol  = mb_strlen($keyword) <= 5 ? 1 : 2;
                if ($dist <= $tol && $dist < $bestScore) {
                    $bestScore = $dist;
                    $best      = $region;
                }
            }
        }

        return $best;
    }

    // ── Rule-based fallback (mock provider / LLM failure) ───────────────────────

    private function ruleBasedUnderstand(string $message, array $history): array
    {
        // ALL extraction is from the CURRENT message only.
        // Prior-turn values live in ConversationStateService — never re-derive
        // them by scanning history text, which causes stale-destination bugs.
        $lowerMsg = mb_strtolower($message);
        $userText = $this->userText($message, $history); // for language detection only

        $destination   = $this->resolveRegion($message);  // null if not mentioned this turn
        $accommodation = $this->detectAccommodation($lowerMsg);
        $partnerChoice = $this->detectPartnerChoice($message, []);  // current message only
        $isNewTrip     = $this->detectIsNewTrip($lowerMsg);

        $questionTarget = $this->detectQuestionTarget($message);
        $intent = $questionTarget !== null
            ? 'question'
            : ($this->isGreeting($lowerMsg) ? 'greeting' : 'other');

        return [
            'language'           => $this->detectLanguage($userText),
            'intent'             => $intent,
            'question_target'    => $questionTarget,
            'destination'        => $destination,     // null → persisted value used in finalize()
            'accommodation_type' => $accommodation,   // null → persisted value used in finalize()
            'partner_choice'     => $partnerChoice,   // null → persisted value used in finalize()
            'is_new_trip'        => $isNewTrip,
            'group_size'         => $this->detectInt($lowerMsg, '/(\d+)\s*(personnes?|people|amis?|friends?|adultes?|adults?)/i')
                                        ?? $this->detectInt($lowerMsg, '/(?:groupe de|group of)\s*(\d+)/i'),
            'nights'             => $this->detectNights($lowerMsg),
            'budget'             => $this->detectBudget($lowerMsg),
            'terrain'            => $this->detectTerrain($lowerMsg),
            'gear_wanted'        => $this->detectGear($lowerMsg),
            'next_action'        => 'ask',
            'reply'              => null,
            'details_provided'   => (bool) preg_match('/\d/', $lowerMsg),
        ];
    }

    private function detectIsNewTrip(string $lowerMsg): bool
    {
        foreach ([
            'nouveau voyage', 'new trip', 'recommencer', 'repartir de', 'fresh start',
            'start fresh', 'start over', 'autre voyage', 'nouvelle aventure',
            'planifier un nouveau', 'repartir à zéro', 'from scratch',
            'رحلة جديدة', 'وجهة جديدة', 'من جديد',
        ] as $kw) {
            if (str_contains($lowerMsg, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function detectQuestionTarget(string $message): ?string
    {
        $l = mb_strtolower($message);

        $isQuestion = false;
        foreach (['tell me about', 'what can you tell', 'what about', 'more about', 'about the ',
                  'c\'est quoi', 'qu\'est-ce que', 'détails sur', 'details sur', 'parle moi de',
                  'parle-moi de', 'info sur', 'information sur', 'أخبرني عن', 'ما هي', 'ما هو'] as $p) {
            if (str_contains($l, $p)) {
                $isQuestion = true;
                break;
            }
        }
        if (! $isQuestion) {
            return null;
        }

        foreach (['that center', 'that centre', 'that zone', 'this center', 'this centre', 'this zone',
                  'that one', 'ce centre', 'cette zone', 'ce camping', 'celui', 'celle'] as $ref) {
            if (str_contains($l, $ref)) {
                return 'current';
            }
        }
        if (preg_match('/(?:about|sur|de|عن)\s+(.{3,70})/iu', $message, $m)) {
            return trim($m[1], " ?.!\"'");
        }
        return 'current';
    }

    private function hasPlanningVerb(string $lower): bool
    {
        foreach (['camp', 'gamping', 'voyage', 'trip', 'séjour', 'veux', 'want', 'go ',
                  'aller', 'تخييم', 'مخيم', 'أريد'] as $s) {
            if (str_contains($lower, $s)) {
                return true;
            }
        }
        return false;
    }

    private function detectAccommodation(string $t): ?string
    {
        foreach (['sauvage', 'wild', 'bivouac', 'nature zone', 'zone naturelle', 'zone de camping naturelle'] as $k) {
            if (str_contains($t, $k)) {
                return 'zone';
            }
        }
        foreach (['centre', 'center', 'équipé', 'equipe', 'bungalow', 'auberge', 'partenaire', 'externe',
                  'partner_only', 'external_only', 'any_centre'] as $k) {
            if (str_contains($t, $k)) {
                return 'centre';
            }
        }
        return null;
    }

    private function detectPartnerChoice(string $message, array $history): ?string
    {
        $candidates = [mb_strtolower($message)];
        foreach ($history as $h) {
            if (($h['role'] ?? '') === 'user') {
                $candidates[] = mb_strtolower($h['content'] ?? '');
            }
        }
        foreach ($candidates as $t) {
            if (str_contains($t, 'any_centre') || str_contains($t, 'les deux') || str_contains($t, 'both')) {
                return 'any_centre';
            }
            if (str_contains($t, 'external_only') || str_contains($t, 'externe') || str_contains($t, 'info seulement')) {
                return 'external_only';
            }
            if (str_contains($t, 'partner_only') || str_contains($t, 'partenaire') || str_contains($t, 'réservable')) {
                return 'partner_only';
            }
        }
        return null;
    }

    private function detectInt(string $t, string $re): ?int
    {
        return preg_match($re, $t, $m) ? max(1, (int) $m[1]) : null;
    }

    private function detectNights(string $t): ?int
    {
        if ($n = $this->detectInt($t, '/(\d+)\s*(nuits?|nights?|jours?|days?|soirs?|ليال|ليلة)/iu')) {
            return $n;
        }
        if (str_contains($t, 'weekend') || str_contains($t, 'week-end')) {
            return 2;
        }
        if (str_contains($t, 'semaine') || str_contains($t, 'week')) {
            return 7;
        }
        return null;
    }

    private function detectBudget(string $t): ?string
    {
        foreach (['premium', 'luxe', 'luxury', 'glamping', 'haut de gamme'] as $k) {
            if (str_contains($t, $k)) {
                return 'premium';
            }
        }
        foreach (['pas cher', 'économique', 'economique', 'petit budget', 'cheap', 'budget'] as $k) {
            if (str_contains($t, $k)) {
                return 'budget';
            }
        }
        if (str_contains($t, 'modéré') || str_contains($t, 'moderate') || str_contains($t, 'standard')) {
            return 'moderate';
        }
        return null;
    }

    private function detectTerrain(string $t): ?string
    {
        $map = [
            'desert'  => ['desert', 'désert', 'sahara', 'dune'],
            'coastal' => ['plage', 'beach', 'mer', 'côt', 'balnéaire', 'seaside'],
            'mountain'=> ['montagne', 'mountain', 'jebel'],
            'forest'  => ['forêt', 'foret', 'forest', 'bois'],
            'wetland' => ['lac', 'lake', 'barrage', 'oued', 'marais'],
        ];
        foreach ($map as $terrain => $kws) {
            foreach ($kws as $k) {
                if (str_contains($t, $k)) {
                    return $terrain;
                }
            }
        }
        return null;
    }

    private function detectGear(string $t): ?bool
    {
        // User opts OUT of gear recommendations (has own or doesn't want suggestions)
        $noGear = [
            'sans équipement', 'sans equipement', 'no gear', 'without gear', 'without equipment',
            'propre matériel', 'propre materiel', 'propre équipement', 'propre equipement',
            "j'ai mon propre", "j'ai mes propres", 'have my own gear', 'have my own equipment',
            'own gear', 'own equipment', 'own kit', 'pas besoin d\'équipement', 'pas besoin de matériel',
            "j'apporte", "apporte mon", "j'ai tout", "i have my",
            'معدات خاصة', 'أملك معدات',
        ];
        foreach ($noGear as $kw) {
            if (str_contains($t, $kw)) {
                return false;
            }
        }

        // User wants gear recommendations
        $wantsGear = [
            'avec recommand', 'with gear', 'recommend gear', 'avec équipement', 'avec equipement',
            'recommande-moi du matériel', 'recommend equipment', 'suggest gear',
            'أوصِ بالمعدات',
        ];
        foreach ($wantsGear as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return null;
    }

    private function isGreeting(string $m): bool
    {
        $m = trim($m);
        $exact = ['hi', 'hello', 'hey', 'yo', 'salut', 'bonjour', 'bonsoir', 'coucou',
                  'merci', 'thanks', 'thank you', 'bye', 'ciao', 'مرحبا', 'السلام عليكم', 'شكرا'];
        if (in_array($m, $exact, true)) {
            return true;
        }
        foreach (['how are you', 'who are you', 'what can you', 'qui es-tu', 'que peux-tu', 'comment ça'] as $p) {
            if (str_starts_with($m, $p)) {
                return true;
            }
        }
        return false;
    }

    private function detectLanguage(string $userText): string
    {
        if (preg_match('/\p{Arabic}/u', $userText)) {
            return 'ar';
        }
        $lower = ' ' . $userText . ' ';
        $fr = 0;
        foreach ([' je ', ' veux ', ' un ', ' une ', ' avec ', ' pour ', ' où ', ' bonjour ', ' nuits ',
                  ' personnes ', ' centre ', ' montagne ', ' plage ', ' forêt '] as $w) {
            if (str_contains($lower, $w)) {
                $fr++;
            }
        }
        if (preg_match('/[éèêàâçùôîï]/u', $lower)) {
            $fr++;
        }
        $en = 0;
        foreach ([' i ', ' want ', ' the ', ' to ', ' with ', ' in ', ' where ', ' hello ', ' hi ',
                  ' nights ', ' people ', ' gear ', ' beach ', ' forest ', ' go ', ' need ', ' camping '] as $w) {
            if (str_contains($lower, $w)) {
                $en++;
            }
        }
        return $en > $fr ? 'en' : 'fr';
    }

    private function userText(string $message, array $history): string
    {
        $parts = [mb_strtolower($message)];
        foreach ($history as $h) {
            if (($h['role'] ?? '') === 'user') {
                $parts[] = mb_strtolower($h['content'] ?? '');
            }
        }
        return implode(' ', $parts);
    }

    private function decodeJson(string $raw): ?array
    {
        $c = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $c = trim(preg_replace('/\s*```$/', '', $c ?? '') ?? '');
        $decoded = json_decode($c, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $first = strpos($c, '{');
            $last  = strrpos($c, '}');
            if ($first !== false && $last !== false && $last > $first) {
                $decoded = json_decode(substr($c, $first, $last - $first + 1), true);
            }
        }
        return is_array($decoded) ? $decoded : null;
    }
}
