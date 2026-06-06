<?php

namespace App\Services\AI;

use App\Models\CampingZone;
use App\Models\Materielles;
use App\Models\ProfileCampeur;
use App\Models\User;
use App\Services\AI\Adapters\LLMAdapterInterface;
use App\Services\AI\Behavioral\BehavioralProfile;
use App\Services\AI\BehavioralProfileService;
use App\Services\AI\CentreLookupService;
use App\Services\AI\ExplainabilityService;
use App\Services\AI\Gear\GearChecklist;
use App\Services\AI\GearAssistantService;
use App\Services\AI\Booking\BookingSummary;
use App\Services\AI\BookingPreparationService;
use App\Services\AI\ConfirmationClassifierService;
use App\Services\AI\GroupACollectorService;
use App\Services\AI\IntentExtractorService;
use App\Services\AI\ProfileExtractorService;
use App\Services\AI\SafetyService;
use App\Services\AI\WeatherService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trip planner pipeline:
 *   CALL 1 (IntentExtractorService) → PHP makes EVERY decision → CALL 2 (text only).
 *
 * The LLM never chooses a zone/centre, never picks gear, never computes cost,
 * never decides whether something exists. PHP does all of that and hands the
 * LLM a completed result with blanks only for natural-language fields
 * (why / reason / ai_summary / weather_warning).
 */
class TripPlannerService
{
    /** Set during an alternative-request turn to exclude the last-recommended item from scoring. */
    private ?int $excludeRecommendedId = null;

    public function __construct(
        private readonly LLMAdapterInterface       $llm,
        private readonly RecommendationService     $recommender,
        private readonly WeatherService            $weather,
        private readonly GearAssistantService      $gearService,
        private readonly SafetyService             $safetyService,
        private readonly ExplainabilityService     $explainer,
        private readonly ProfileExtractorService   $profileExtractor,
        private readonly CentreLookupService       $centreLookup,
        private readonly ConversationManager       $conversation,
        private readonly ConversationStateService    $conversationState,
        private readonly BehavioralProfileService    $behavioralProfileService,
        private readonly GroupACollectorService      $groupACollector,
        private readonly BookingPreparationService   $bookingPreparation,
    ) {}

    public function plan(User $user, string $message, array $history = []): array
    {
        $profile = $user->profile?->profileCampeur;

        if ($profile === null) {
            return ['error' => 'Camper profile not found. Please complete your profile first.'];
        }

        // STEP A — Extract and save any profile data embedded in the user's message
        $extracted = $this->profileExtractor->extractProfileDataFromMessage($message);
        if (! empty($extracted)) {
            $profile = $this->profileExtractor->saveExtractedProfile($profile, $extracted);
        }

        $missingCritical = $this->profileExtractor->getMissingCriticalFields($profile);

        // ── 0. Compute behavioral profile (cached, never throws) ────────────────
        // Derives skill, budget, terrain preference from real platform activity.
        // Used downstream to weight zone/gear recommendations over stale static fields.
        $behavioralProfile = $this->behavioralProfileService->compute($user->id);

        // ── 1. Load the persisted conversation state (single source of truth) ──
        $persistedState = $this->conversationState->load($user->id);

        // ── 1b. Alternative / modify request pre-processing ──────────────────
        // "recommend another" → run the pipeline again, excluding the last id.
        if ($this->isAlternativeRequest($message) && $this->conversationState->isGroupAComplete($persistedState)) {
            $this->excludeRecommendedId = $persistedState['last_recommended_id'] ?? null;
        }

        // "Je veux modifier le plan" → clear destination so user picks a new one.
        if ($this->isModifyRequest($message) && $this->conversationState->isGroupAComplete($persistedState)) {
            $persistedState['destination'] = null;
            $this->conversationState->save($user->id, $persistedState);
        }

        // ── 2. Understand this turn (current message + persisted context) ──────
        // History is passed to the LLM for conversational context; PHP never
        // scans raw history text to derive slot values.
        $turnState = $this->conversation->understand($profile, $message, $history, $persistedState);
        $lang = $turnState['language'] ?? 'fr';

        // ── 3. Build the delta and merge it into the persisted state ────────────
        // Only concrete non-null values from this turn overwrite stored values.
        // is_new_trip=true causes a full reset before applying the delta.
        $delta = [
            // region is already resolved to a canonical label by ConversationManager
            'destination'        => $turnState['region'] !== null ? $turnState['region'] : null,
            'group_size'         => $turnState['group_size'],
            'duration_nights'    => $turnState['nights'],
            // 'any' means "user hasn't chosen yet" — don't overwrite a prior choice
            'accommodation_type' => in_array($turnState['accommodation_type'] ?? null, ['zone', 'centre'], true)
                                        ? $turnState['accommodation_type']
                                        : null,
            'wants_gear'         => $turnState['gear_wanted'],
            'partner_choice'     => in_array($turnState['partner_choice'] ?? null, ['partner_only', 'external_only', 'any_centre'], true)
                                        ? $turnState['partner_choice']
                                        : null,
            'is_new_trip'        => (bool) ($turnState['is_new_trip'] ?? false),
        ];
        $mergedState = $this->conversationState->merge($user->id, $delta);

        // ── 4. Build the effective planning state ───────────────────────────────
        // Ephemeral turn fields (language, next_action, ask, reply, …) come from
        // the turn. Persistent slot values come from the merged state so they are
        // never lost between turns.
        $state = array_merge($turnState, [
            'region'             => $mergedState['destination'],
            'destination'        => $mergedState['destination'],
            'group_size'         => $mergedState['group_size'],
            'nights'             => $mergedState['duration_nights'],
            'accommodation_type' => $mergedState['accommodation_type'] ?? 'any',
            'gear_wanted'        => $mergedState['wants_gear'],
            'partner_choice'     => $mergedState['partner_choice'],
        ]);

        // ── 5. Dispatch on next_action ──────────────────────────────────────────
        // Chat and Q&A actions do not require Group A — let them through.
        if ($state['next_action'] === 'chat') {
            return ['chat_message' => $state['reply'] ?? $this->fallbackGreeting($lang)];
        }

        if ($state['next_action'] === 'answer') {
            return $this->answerQuestion($profile, $state, $message, $history);
        }

        // ── 5b. Group A gate — all planning paths require complete Group A ──────
        // Returns destination_clarification or group_a_modal when fields are
        // missing; returns null when all five Group A fields are confirmed.
        $groupAResult = $this->groupACollector->check($mergedState, $message);
        if ($groupAResult !== null) {
            return $groupAResult;
        }

        // Group A complete — run pipeline (ask = partner_choice only at this point)
        if ($state['next_action'] === 'ask') {
            $result = $this->buildAskResponse($state);
        } else {
            $result = $this->planFromState($user, $profile, $state, $message, $history, $missingCritical, $mergedState, $behavioralProfile);
            $this->excludeRecommendedId = null; // reset for next request

            // Persist the recommended id so alternative requests can exclude it.
            $newId = $result['recommended_zone']['id'] ?? ($result['recommended']['id'] ?? null);
            if ($newId !== null) {
                $mergedState['last_recommended_id'] = $newId;
                $this->conversationState->save($user->id, $mergedState);
            }

            // Attach booking summary immediately — no separate "oui parfait" step.
            $bookingSummary = $this->bookingPreparation->prepare($result, $mergedState, $user);
            if ($bookingSummary->is_bookable) {
                Cache::put('booking_summary:' . $user->id, $bookingSummary->toArray(), 900);
                $result['booking_summary'] = $bookingSummary->toArray();
                $result['booking_ready']   = true;
            } else {
                $result['booking_ready'] = false;
                $result['booking_note']  = $bookingSummary->not_bookable_reason;
            }
        }

        if (! empty($extracted) && ! isset($result['clarification_questions']) && ! isset($result['quick_replies'])) {
            $result['profile_updated'] = [
                'fields_saved' => array_keys($extracted),
                'message'      => 'Vos préférences ont été sauvegardées automatiquement.',
            ];
        }

        return $result;
    }

    // ── State-driven dispatch ───────────────────────────────────────────────────

    /**
     * Run the recommendation pipeline using only the persisted conversation
     * state — no LLM Call 1, no message parsing.
     *
     * Called by the group-a modal submission endpoint after all Group A fields
     * have been confirmed via structured form input.
     */
    public function planFromCurrentState(User $user): array
    {
        $profile = $user->profile?->profileCampeur;

        if ($profile === null) {
            return ['error' => 'Camper profile not found. Please complete your profile first.'];
        }

        $behavioralProfile = $this->behavioralProfileService->compute($user->id);
        $mergedState       = $this->conversationState->load($user->id);
        $missingCritical   = $this->profileExtractor->getMissingCriticalFields($profile);

        $state = [
            'region'             => $mergedState['destination'],
            'destination'        => $mergedState['destination'],
            'group_size'         => $mergedState['group_size'],
            'nights'             => $mergedState['duration_nights'],
            'accommodation_type' => $mergedState['accommodation_type'] ?? 'zone',
            'gear_wanted'        => $mergedState['wants_gear'],
            'partner_choice'     => $mergedState['partner_choice'],
            'budget'             => 'moderate',
            'terrain'            => 'aventure',
            'language'           => 'fr',
            'next_action'        => 'plan',
        ];

        $result = $this->planFromState(
            $user,
            $profile,
            $state,
            '',
            [],
            $missingCritical,
            $mergedState,
            $behavioralProfile,
        );

        // Persist the recommended id for alternative request filtering.
        $newId = $result['recommended_zone']['id'] ?? ($result['recommended']['id'] ?? null);
        if ($newId !== null) {
            $mergedState['last_recommended_id'] = $newId;
            $this->conversationState->save($user->id, $mergedState);
        }

        // Attach booking summary immediately.
        $bookingSummary = $this->bookingPreparation->prepare($result, $mergedState, $user);
        if ($bookingSummary->is_bookable) {
            Cache::put('booking_summary:' . $user->id, $bookingSummary->toArray(), 900);
            $result['booking_summary'] = $bookingSummary->toArray();
            $result['booking_ready']   = true;
        } else {
            $result['booking_ready'] = false;
            $result['booking_note']  = $bookingSummary->not_bookable_reason;
        }

        return $result;
    }

    /**
     * Turn the resolved dialogue state into a concrete plan by driving the
     * existing deterministic flows with EXPLICIT slots (no re-detection).
     */
    private function planFromState(
        User              $user,
        ProfileCampeur    $profile,
        array             $state,
        string            $message,
        array             $history,
        array             $missingCritical,
        array             $mergedState       = [],
        ?BehavioralProfile $behavioral        = null,
    ): array {
        $region = $state['region'];
        $intent = [
            'destination'        => $region,
            'budget'             => $state['budget'] ?? 'moderate',
            'group_size'         => $state['group_size'] ?? 2,
            'duration_nights'    => $state['nights'] ?? 2,
            'trip_style'         => $state['terrain'] ?? 'aventure',
            'accommodation_type' => $state['accommodation_type'],
            'gear_wanted'        => $state['gear_wanted'],
            'details_assumed'    => ($mergedState['group_size'] === null || $mergedState['duration_nights'] === null),
        ];

        // Build the effective profile: behavioral wins over static when confident.
        // The resulting ProfileCampeur clone is used by the recommendation engine.
        $effectiveProfile = $behavioral !== null
            ? $this->buildEffectiveProfile($profile, $behavioral)
            : $profile;

        if ($state['accommodation_type'] === 'centre') {
            return match ($state['partner_choice']) {
                'partner_only'  => $this->partnerCentreFlow($effectiveProfile, $intent, $message, $history, $missingCritical, $region),
                'external_only' => $this->externalCentreFlow($effectiveProfile, $intent, $message, $history, $region),
                default         => $this->combinedCentreFlow($effectiveProfile, $intent, $message, $history, $missingCritical, $region),
            };
        }

        return $this->generateZonePlan($user, $message, $effectiveProfile, $intent, $missingCritical, $history, $region);
    }

    /**
     * Build a clarification response: the LLM-written question (in the user's
     * language) plus the matching quick-select chips for whatever slot is next.
     */
    private function buildAskResponse(array $state): array
    {
        $lang  = $state['language'] ?? 'fr';
        $reply = $state['reply'] ?? null;

        return match ($state['ask'] ?? 'destination') {
            'accommodation'  => $this->askTripDetails($lang, $reply),
            'partner_choice' => $this->askPartnerChoice($lang, $reply),
            default          => $this->askDestination($lang, $reply),
        };
    }

    // ── POST_RECOMMENDATION handlers ────────────────────────────────────────────

    /**
     * User confirmed the recommendation → prepare booking and return a
     * natural-language summary for the user to review before confirming.
     */
    private function handleBookingConfirmation(
        User   $user,
        array  $lastRecommendation,
        array  $state,
        array  $history,
    ): array {
        $summary = $this->bookingPreparation->prepare($lastRecommendation, $state, $user);

        if (! $summary->is_bookable) {
            return [
                'chat_message'    => $summary->not_bookable_reason,
                'booking_summary' => null,
            ];
        }

        // Cache the summary so /confirm-booking can retrieve it (TTL = 15 min)
        Cache::put('booking_summary:' . $user->id, $summary->toArray(), 900);

        $chatMessage = $this->generateBookingConfirmationText($summary, $lastRecommendation, $history);

        return [
            'type'            => 'booking_confirmation_prompt',
            'chat_message'    => $chatMessage,
            'booking_summary' => $summary->toArray(),
            'confirm_action'  => 'POST /api/ai/trip-planner/confirm-booking',
            'modify_action'   => 'send a message describing what to change',
        ];
    }

    /**
     * LLM Call 2 variant: write a human-readable booking summary the user
     * must confirm before any DB record is created.
     */
    private function generateBookingConfirmationText(
        BookingSummary $summary,
        array          $recommendation,
        array          $history,
    ): string {
        $recommended = $recommendation['recommended'] ?? $recommendation['recommended_zone'] ?? [];
        $placeName   = $summary->centre_nom
                    ?? ($recommended['nom']    ?? null)
                    ?? ($recommended['region'] ?? 'votre destination');

        $gearNames = array_map(fn ($g) => $g['nom'], $summary->gear_items);

        $nights = ($summary->check_in && $summary->check_out)
            ? Carbon::parse($summary->check_in)->diffInDays(Carbon::parse($summary->check_out))
            : null;

        $context = [
            'place_nom'          => $placeName,
            'check_in'           => $summary->check_in,
            'check_out'          => $summary->check_out,
            'duration_nights'    => $nights,
            'nbr_place'          => $summary->nbr_place,
            'gear_items'         => $gearNames,
            'total'              => $summary->total,
            'currency'           => 'TND',
            'dates_are_proposed' => $summary->dates_are_proposed,
        ];

        $systemPrompt = <<<PROMPT
Vous êtes l'assistant de réservation de TunisiaCamp.
Un campeur vient de confirmer qu'il souhaite réserver ce qui suit.
Rédigez un récapitulatif de confirmation clair et amical en français, comme un assistant humain confirmant les détails.
Incluez : le nom du lieu, les dates proposées (ou la durée), le nombre de personnes, les équipements si présents, et le coût total en TND.
Terminez exactement par : « Confirmez-vous cette réservation ? »
Maximum 5 phrases. Texte fluide, sans listes ni markdown.
PROMPT;

        $userMessage = 'Détails : ' . json_encode($context, JSON_UNESCAPED_UNICODE);

        try {
            $raw = trim($this->llm->complete($systemPrompt, $userMessage, 220, $history));
            if ($raw !== '') {
                return $this->stripMarkdownFences($raw);
            }
        } catch (\Throwable) {
            // fall through to fallback
        }

        // Fallback — rule-based summary
        $group     = $summary->nbr_place ?? '?';
        $nightsStr = $nights ?? '?';
        $gearText  = ! empty($gearNames) ? ', avec ' . implode(', ', array_slice($gearNames, 0, 3)) : '';
        $proposed  = $summary->dates_are_proposed ? ' (dates proposées)' : '';

        return "Pour {$group} personne(s), {$nightsStr} nuit(s) à {$placeName}{$proposed}{$gearText}. "
             . "Coût total estimé : {$summary->total} TND (frais de plateforme inclus). "
             . "Confirmez-vous cette réservation ?";
    }

    /**
     * Generate a natural-language success message after a reservation is
     * created. Exposed as a public method so AiTripPlannerController can call
     * it from the confirmBooking endpoint.
     */
    public function generateReservationConfirmationMessage(
        int   $reservationId,
        array $context,
        array $history = [],
    ): string {
        $systemPrompt = <<<PROMPT
Vous êtes l'assistant de réservation TunisiaCamp.
Écrivez un message de confirmation de réservation amical en 2 phrases en français.
Incluez le numéro de réservation et expliquez l'étape suivante (validation du centre ou confirmation du fournisseur de matériel, puis paiement).
Adressez-vous directement à l'utilisateur. Pas de listes. Texte fluide uniquement.
PROMPT;

        $userMessage = 'Contexte : ' . json_encode($context, JSON_UNESCAPED_UNICODE);

        try {
            $raw = trim($this->llm->complete($systemPrompt, $userMessage, 150, $history));
            if ($raw !== '') {
                return $this->stripMarkdownFences($raw);
            }
        } catch (\Throwable) {
            // fall through
        }

        // Fallback
        $ref   = $context['reservation_id'] ?? $reservationId;
        $place = $context['place_nom'] ?? 'votre destination';

        return "Votre réservation #{$ref} pour {$place} a été créée avec succès et est en attente de confirmation. "
             . "Le prestataire dispose de 48h pour valider votre demande ; vous serez notifié(e) par email pour finaliser le paiement.";
    }

    /** Standard rejection response — clears state and invites a new search. */
    private function buildRejectResponse(string $lang = 'fr'): array
    {
        return match ($lang) {
            'en' => [
                'chat_message'  => "No problem! Tell me what you're looking for and I'll find another option for you.",
                'quick_replies' => [
                    ['label' => '🔄 New search',      'value' => 'I want to search for another zone'],
                    ['label' => '🏕️ Another region',  'value' => 'I want to camp in another region'],
                ],
            ],
            'ar' => [
                'chat_message'  => "لا مشكلة! أخبرني بما تبحث عنه وسأجد لك خيارًا آخر.",
                'quick_replies' => [
                    ['label' => '🔄 بحث جديد',       'value' => 'أريد البحث عن منطقة أخرى'],
                    ['label' => '🏕️ منطقة أخرى',     'value' => 'أريد التخييم في منطقة أخرى'],
                ],
            ],
            default => [
                'chat_message'  => "Pas de problème ! Dites-moi ce que vous recherchez et je trouverai une autre option pour vous.",
                'quick_replies' => [
                    ['label' => '🔄 Nouvelle recherche', 'value' => 'Je veux chercher une autre zone'],
                    ['label' => '🏕️ Autre région',       'value' => 'Je veux camper dans une autre région'],
                ],
            ],
        };
    }

    // ── Q&A about a specific zone / centre ──────────────────────────────────────

    /**
     * Answer an informational question about a zone or centre (named, or the one
     * just recommended) — instead of force-fitting it into another trip plan.
     */
    private function answerQuestion(ProfileCampeur $profile, array $state, string $message, array $history): array
    {
        $lang   = $state['language'] ?? 'fr';
        $target = $state['question_target'] ?? 'current';
        $entity = $this->resolveQuestionEntity($target, $profile, $state);

        if ($entity === null) {
            return ['chat_message' => match ($lang) {
                'en' => "I couldn't find that place in our database. Could you give me its exact name or a region?",
                'ar' => "لم أجد هذا المكان في قاعدتنا. هل يمكنك إعطائي اسمه الدقيق أو المنطقة؟",
                default => "Je n'ai pas trouvé ce lieu dans notre base. Pouvez-vous me donner son nom exact ou une région ?",
            }];
        }

        $facts  = $this->entityFacts($entity);
        $answer = $this->entityFactsTemplate($facts, $lang);

        // The mock provider can't write prose — only call a real LLM for nicer text.
        if (config('ai.provider') !== 'mock') {
            try {
                $languageName = $this->languageName($lang);
                $sys = "You are TunisiaCamp's assistant. Answer the user's question about this place using "
                    . "ONLY the facts provided. 2-3 friendly sentences in {$languageName}. Costs are in TND. "
                    . "Never invent details. No markdown, plain text.";
                $written = trim($this->llm->complete($sys, 'Facts: ' . json_encode($facts, JSON_UNESCAPED_UNICODE)
                    . "\n\nQuestion: " . $message, 220));
                if ($written !== '') {
                    $answer = $written;
                }
            } catch (\Throwable) {
                // keep the template answer
            }
        }

        return ['chat_message' => $answer, 'about' => $facts];
    }

    private function resolveQuestionEntity(string $target, ProfileCampeur $profile, array $state): ?array
    {
        // Named lookup first
        if ($target !== 'current' && mb_strlen($target) >= 3) {
            $like = '%' . addcslashes($target, '%_') . '%';
            $zone = CampingZone::where('status', true)->where('nom', 'like', $like)->first();
            if ($zone) {
                return ['kind' => 'zone', 'model' => $zone];
            }
            $centre = \App\Models\CampingCentre::with('profileCentre.equipment')->where('nom', 'like', $like)->first();
            if ($centre) {
                return ['kind' => 'centre', 'model' => $centre];
            }
        }

        // "current": resolve from the conversation's region + accommodation
        $region = $state['region'] ?? null;
        if (($state['accommodation_type'] ?? null) === 'centre' && $region) {
            $c = $this->centreLookup->findPartnerCentres($region, 1)->first()
                ?? $this->centreLookup->findExternalCentres($region, 1)->first();
            if ($c) {
                return ['kind' => 'centre_arr', 'data' => $c];
            }
        }
        if ($region) {
            [$zones] = $this->fetchContext(5, 1, $region);
            $top = $this->recommender->scoreZones($profile, $zones)->first();
            if ($top) {
                return ['kind' => 'zone', 'model' => CampingZone::find($top->id) ?? $top];
            }
        }

        return null;
    }

    private function entityFacts(array $entity): array
    {
        if ($entity['kind'] === 'centre_arr') {
            $d = $entity['data'];
            return [
                'type'            => 'centre',
                'nom'             => $d['nom'] ?? '',
                'region'          => $d['region'] ?? '',
                'price_per_night' => $d['price_per_night'] ?? null,
                'capacite'        => $d['capacite'] ?? null,
                'equipment'       => $d['equipment_list'] ?? [],
                'bookable'        => ($d['type'] ?? '') === 'centre_partner',
            ];
        }

        if ($entity['kind'] === 'centre') {
            $c  = $entity['model'];
            $pc = $c->profileCentre;
            return [
                'type'            => 'centre',
                'nom'             => $c->nom,
                'adresse'         => $c->adresse,
                'description'     => mb_substr((string) $c->description, 0, 280),
                'price_per_night' => $pc?->price_per_night,
                'capacite'        => $pc?->capacite,
                'equipment'       => $pc ? $pc->equipment->where('is_available', true)->pluck('type')->values()->all() : [],
                'bookable'        => $c->profile_centre_id !== null && (bool) $c->status,
            ];
        }

        $z = $entity['model'];
        return [
            'type'              => 'zone',
            'nom'               => $z->nom,
            'region'            => $z->region,
            'terrain'           => $z->terrain_type,
            'difficulty'        => $z->difficulty,
            'beginner_friendly' => (bool) ($z->is_beginner_friendly ?? false),
            'rating'            => $z->rating,
            'danger_level'      => $z->danger_level,
            'activities'        => is_string($z->activities) ? json_decode($z->activities, true) : $z->activities,
            'description'       => mb_substr((string) ($z->full_description ?? $z->description ?? ''), 0, 280),
        ];
    }

    private function entityFactsTemplate(array $f, string $lang): string
    {
        $nom = $f['nom'] ?? '';
        if (($f['type'] ?? '') === 'centre') {
            $price = $f['price_per_night'] ? " ({$f['price_per_night']} TND/nuit)" : '';
            $equip = ! empty($f['equipment']) ? implode(', ', array_slice($f['equipment'], 0, 5)) : '';
            return match ($lang) {
                'en' => trim("{$nom} is a camping centre" . ($f['region'] ?? '' ? " in {$f['region']}" : '') . "{$price}."
                    . ($equip ? " Facilities: {$equip}." : '') . ($f['bookable'] ?? false ? ' Bookable on the platform.' : '')),
                'ar' => trim("{$nom} مركز تخييم{$price}." . ($equip ? " المرافق: {$equip}." : '')),
                default => trim("{$nom} est un centre de camping" . ($f['region'] ?? '' ? " à {$f['region']}" : '') . "{$price}."
                    . ($equip ? " Équipements : {$equip}." : '') . ($f['bookable'] ?? false ? ' Réservable sur la plateforme.' : '')),
            };
        }

        $terrain = $f['terrain'] ?? '';
        $diff    = $f['difficulty'] ?? '';
        return match ($lang) {
            'en' => trim("{$nom} is a {$terrain} camping zone" . ($f['region'] ?? '' ? " in {$f['region']}" : '')
                . ", difficulty {$diff}" . (($f['beginner_friendly'] ?? false) ? ', beginner-friendly' : '') . '.'),
            'ar' => trim("{$nom} منطقة تخييم ({$terrain})" . ($f['region'] ?? '' ? " في {$f['region']}" : '') . "، الصعوبة {$diff}."),
            default => trim("{$nom} est une zone de camping {$terrain}" . ($f['region'] ?? '' ? " à {$f['region']}" : '')
                . ", difficulté {$diff}" . (($f['beginner_friendly'] ?? false) ? ', adaptée aux débutants' : '') . '.'),
        };
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ZONE FLOW  (PHP decides zone, gear, weather, safety, cost; LLM writes text)
    // ═══════════════════════════════════════════════════════════════════════════

    private function generateZonePlan(
        User           $user,
        string         $message,
        ProfileCampeur $profile,
        array          $intent,
        array          $missingCritical,
        array          $history,
        ?string        $destinationRegion,
    ): array {
        [$zones, $gear] = $this->fetchContext(8, 12, $destinationRegion);

        // No zones for the requested destination — PHP decides this, not the LLM
        if ($destinationRegion !== null && $zones->isEmpty()) {
            return [
                'intent'           => $intent,
                'recommended_zone' => [
                    'type'   => 'zone',
                    'id'     => 0,
                    'nom'    => 'Aucune zone disponible',
                    'region' => $destinationRegion,
                    'why'    => '',
                ],
                'gear_list'       => [],
                'weather_warning' => null,
                'estimated_cost'  => ['gear_per_night' => 0, 'total_estimate' => 0, 'currency' => 'TND'],
                'ai_summary'      => "Nous n'avons pas encore de zones de camping répertoriées pour "
                    . "{$destinationRegion}. Souhaitez-vous explorer une région voisine ?",
                'no_zone_found'   => true,
            ];
        }

        // Pre-score (PHP recommendation engine)
        $scoredZones = $this->recommender->scoreZones($profile, $zones);
        $scoredGear  = $this->recommender->scoreGear($profile, $gear);

        // Exclude the last-recommended zone for alternative-request turns.
        if ($this->excludeRecommendedId !== null) {
            $filtered = $scoredZones->filter(fn ($z) => $z->id !== $this->excludeRecommendedId);
            if ($filtered->isNotEmpty()) {
                $scoredZones = $filtered->values();
            }
        }

        // Regional reorder — destination overrides score
        $mentionedRegion = $this->extractMentionedRegion($message);
        if ($mentionedRegion && $scoredZones->isNotEmpty()) {
            $regional = $scoredZones->filter(
                fn ($z) => str_contains(mb_strtolower($z->region ?? ''), mb_strtolower($mentionedRegion))
            );
            if ($regional->isNotEmpty()) {
                $others      = $scoredZones->filter(
                    fn ($z) => ! str_contains(mb_strtolower($z->region ?? ''), mb_strtolower($mentionedRegion))
                );
                $scoredZones = $regional->concat($others)->values();
            }
        }

        // Pick top zone (PHP decision)
        $topZoneData = $scoredZones->first();
        // Effective terrain: the zone's own type, else inferred from the
        // requested trip style (seed data often leaves terrain_type empty).
        $terrainType = ($topZoneData?->terrain_type ?: null)
            ?? $this->terrainFromTripStyle($intent['trip_style'] ?? '')
            ?? 'mixed';
        $zoneModel   = $topZoneData ? CampingZone::find($topZoneData->id) : null;

        // Weather (PHP WeatherService decision)
        $forecast       = null;
        $weatherSummary = null;
        $weatherRisk    = 'low';
        if ($zoneModel && config('ai.features.weather', true)) {
            $forecast    = $this->weather->getForecastForZone($zoneModel);
            $weatherRisk = $this->weather->getOverallRiskLevel($forecast);
            if ($this->weather->shouldWarnUser($forecast)) {
                $weatherSummary = $this->weather->getWeatherSummaryForPrompt($forecast);
            }
        }

        // Gear checklist (PHP GearAssistantService decision).
        // wants_gear is read from the merged persistent state — no history scan.
        $gearChecklist = null;
        $suppressGear  = ($intent['gear_wanted'] ?? null) === false;
        if ($zoneModel && config('ai.features.gear_assistant', true) && ! $suppressGear) {
            $gearChecklist = $this->gearService->generateChecklist(
                $profile, $zoneModel, $forecast, $intent['group_size']
            );
        }

        // Format gear list (PHP decision — terrain-aware). Empty when the user
        // explicitly opted out of equipment.
        $gearForResponse = $suppressGear
            ? []
            : $this->formatGearForResponse($gearChecklist, $scoredGear, $terrainType);

        // Cost (pure PHP math)
        $cost = $this->calculateCost($gearForResponse, $intent);

        // Safety (PHP SafetyService)
        $safetyAssessment = null;
        $safetyAlert      = null;
        if ($zoneModel && config('ai.features.safety', true)) {
            $safetyAssessment = $this->safetyService->assessTripSafety(
                $profile, $zoneModel, $forecast, $intent['group_size']
            );
            if (in_array($safetyAssessment->label, ['danger', 'warning'], true)) {
                $safetyAlert = $safetyAssessment->label;
            }
        }

        // Complete result structure — only text fields are blank
        $result = [
            'intent'           => $intent,
            'recommended_zone' => [
                'type'   => 'zone',
                'id'     => $topZoneData?->id ?? 0,
                'nom'    => $topZoneData?->nom ?? '',
                'region' => $topZoneData?->region ?? '',
                'why'    => '', // CALL 2 fills
            ],
            'gear_list'       => $gearForResponse,
            'weather_warning' => $weatherSummary,
            'estimated_cost'  => $cost,
            'ai_summary'      => '',
        ];

        // ── CALL 2 — text only ────────────────────────────────────────────────
        $llmContext = [
            'recommended_type'     => 'zone',
            'accommodation_type'   => $intent['accommodation_type'] ?? 'zone',
            'zone_nom'             => $result['recommended_zone']['nom'],
            'zone_region'          => $result['recommended_zone']['region'],
            'zone_terrain'         => $terrainType,
            'zone_difficulty'      => $topZoneData?->difficulty ?? 'easy',
            'zone_score_breakdown' => $topZoneData?->score_breakdown ?? [],
            'skill_level'          => $profile->skill_level ?? 'beginner',
            'budget_range'         => $profile->budget_range ?? 'moderate',
            'comfort_level'        => $profile->comfort_level ?? 'standard',
            'group_size'           => $intent['group_size'],
            'duration_nights'      => $intent['duration_nights'],
            'trip_style'           => $intent['trip_style'],
            'gear_items'           => array_map(fn ($g) => [
                'nom'      => $g['nom'],
                'category' => $g['category'] ?? '',
                'terrain'  => $terrainType,
            ], $gearForResponse),
            'weather_risk'         => $weatherRisk,
            'safety_label'         => $safetyAssessment?->label ?? 'safe',
            'safety_factors'       => array_map(
                fn ($f) => $f->description,
                $safetyAssessment?->factors ?? []
            ),
            'total_cost'           => $cost['total_estimate'],
            'missing_profile'      => $missingCritical,
            'has_external_centre'  => false,
            'details_assumed'      => $intent['details_assumed'] ?? true,
        ];

        $decoded = $this->callTextLlm($llmContext, $message, $history, $missingCritical, $safetyAlert, $weatherRisk) ?? [];

        $result['recommended_zone']['why'] = $decoded['zone_why']
            ?? $this->fallbackZoneWhy($topZoneData, $profile, $terrainType);
        $result['ai_summary'] = $decoded['ai_summary']
            ?? $this->fallbackSummary($intent, $result);
        $this->applyGearReasons($result['gear_list'], $decoded['gear_reasons'] ?? [], $terrainType);
        if ($weatherSummary !== null) {
            $result['weather_warning'] = $decoded['weather_warning'] ?? $weatherSummary;
        }

        // ── Attach structured data + explanations ─────────────────────────────
        if ($forecast !== null) {
            $result['weather_data'] = $forecast->toArray();
            $result['weather_risk'] = $weatherRisk;
        }
        if ($gearChecklist !== null) {
            $result['gear_checklist'] = $gearChecklist->toArray();
            $result['critical_alert'] = $this->gearService->getCriticalMissingAlert($gearChecklist);
        }
        if ($safetyAssessment !== null) {
            $result['safety'] = $safetyAssessment->toArray();
        }

        if ($topZoneData) {
            $result['recommended_zone']['explanation'] = $this->explainer
                ->explainRecommendation(
                    $topZoneData->score_breakdown ?? [],
                    $topZoneData->nom ?? '',
                    $profile->skill_level ?? 'beginner',
                    ($topZoneData->score ?? 0) / 13,
                )->toArray();
        }
        if ($safetyAssessment !== null) {
            $result['safety']['explanation'] = $this->explainer
                ->explainSafetyAssessment($safetyAssessment)->toArray();
        }
        if ($gearChecklist !== null) {
            $result['gear_checklist']['explanation'] = $this->explainer
                ->explainGearSuggestion($gearChecklist, $terrainType, $weatherRisk)->toArray();
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CLARIFICATION BUILDERS  (LLM writes the question; PHP supplies the chips)
    // ═══════════════════════════════════════════════════════════════════════════

    private function askDestination(string $lang, ?string $reply): array
    {
        $pick = fn (array $m) => $m[$lang] ?? $m['fr'];

        return [
            'chat_message' => $reply ?? $pick([
                'fr' => "Avec plaisir ! Où en Tunisie souhaitez-vous camper ?",
                'en' => "With pleasure! Where in Tunisia would you like to camp?",
                'ar' => "بكل سرور! أين في تونس ترغب في التخييم؟",
            ]),
            'quick_replies' => [
                ['label' => $pick(['fr' => '🌿 Nord — Jendouba / Tabarka', 'en' => '🌿 North — Jendouba / Tabarka', 'ar' => '🌿 الشمال — جندوبة / طبرقة']),  'value' => $pick(['fr' => 'Je veux camper à Jendouba ou Tabarka', 'en' => 'I want to camp in Jendouba or Tabarka', 'ar' => 'أريد التخييم في Jendouba أو Tabarka'])],
                ['label' => $pick(['fr' => '🏙️ Grand Tunis', 'en' => '🏙️ Greater Tunis', 'ar' => '🏙️ تونس الكبرى']),                'value' => $pick(['fr' => 'Je veux camper près de Tunis', 'en' => 'I want to camp near Tunis', 'ar' => 'أريد التخييم قرب Tunis'])],
                ['label' => $pick(['fr' => '🌊 Côte Est — Sousse / Nabeul', 'en' => '🌊 East Coast — Sousse / Nabeul', 'ar' => '🌊 الساحل الشرقي — سوسة / نابل']), 'value' => $pick(['fr' => 'Je veux camper à Sousse ou Nabeul', 'en' => 'I want to camp in Sousse or Nabeul', 'ar' => 'أريد التخييم في Sousse أو Nabeul'])],
                ['label' => $pick(['fr' => '🏜️ Sud — Tozeur / Djerba', 'en' => '🏜️ South — Tozeur / Djerba', 'ar' => '🏜️ الجنوب — توزر / جربة']),      'value' => $pick(['fr' => 'Je veux camper à Tozeur ou Djerba', 'en' => 'I want to camp in Tozeur or Djerba', 'ar' => 'أريد التخييم في Tozeur أو Djerba'])],
            ],
        ];
    }

    private function askTripDetails(string $lang, ?string $reply): array
    {
        $pick = fn (array $m) => $m[$lang] ?? $m['fr'];

        // option VALUES stay canonical French — machine payloads for the fallback detectors.
        return [
            'chat_message' => $reply ?? $pick([
                'fr' => "Super ! Pour bien cibler, dites-moi le type de séjour et le terrain. "
                    . "Vous pouvez aussi préciser le nombre de personnes, de nuits et votre budget.",
                'en' => "Great! To narrow it down, pick your stay type and terrain. "
                    . "You can also tell me group size, nights and budget.",
                'ar' => "رائع! لتحديد الأنسب، اختر نوع الإقامة والتضاريس. "
                    . "يمكنك أيضاً ذكر عدد الأشخاص والليالي والميزانية.",
            ]),
            'clarification_questions' => [
                [
                    'id'       => 'accommodation',
                    'question' => $pick(['fr' => '🏡 Type de séjour', 'en' => '🏡 Type of stay', 'ar' => '🏡 نوع الإقامة']),
                    'multiple' => false,
                    'options'  => [
                        ['label' => $pick(['fr' => '🌿 Zone naturelle', 'en' => '🌿 Wild nature zone', 'ar' => '🌿 منطقة طبيعية']),    'value' => 'zone de camping naturelle'],
                        ['label' => $pick(['fr' => '🏕️ Centre de camping', 'en' => '🏕️ Camping centre', 'ar' => '🏕️ مركز تخييم']), 'value' => 'centre de camping équipé'],
                    ],
                ],
                [
                    'id'       => 'gear',
                    'question' => $pick(['fr' => '🎒 Équipements', 'en' => '🎒 Equipment', 'ar' => '🎒 المعدات']),
                    'multiple' => false,
                    'options'  => [
                        ['label' => $pick(['fr' => '✅ Oui, recommande-moi du matériel', 'en' => '✅ Yes, recommend gear', 'ar' => '✅ نعم، أوصِ بالمعدات']), 'value' => 'avec recommandations équipements'],
                        ['label' => $pick(['fr' => '❌ Sans équipements', 'en' => '❌ Without equipment', 'ar' => '❌ بدون معدات']),                'value' => 'sans équipement'],
                    ],
                ],
                [
                    'id'       => 'style',
                    'question' => $pick(['fr' => '🗺️ Style / terrain (plusieurs choix)', 'en' => '🗺️ Style / terrain (multiple)', 'ar' => '🗺️ النمط / التضاريس (متعدد)']),
                    'multiple' => true,
                    'options'  => [
                        ['label' => $pick(['fr' => '🥾 Randonnée', 'en' => '🥾 Hiking', 'ar' => '🥾 مشي']),       'value' => 'randonnée'],
                        ['label' => $pick(['fr' => '👨‍👩‍👧 Famille', 'en' => '👨‍👩‍👧 Family', 'ar' => '👨‍👩‍👧 عائلي']),    'value' => 'famille'],
                        ['label' => $pick(['fr' => '📸 Découverte', 'en' => '📸 Discovery', 'ar' => '📸 اكتشاف']), 'value' => 'découverte culturelle'],
                        ['label' => $pick(['fr' => '🌊 Balnéaire', 'en' => '🌊 Seaside', 'ar' => '🌊 شاطئي']),   'value' => 'activités aquatiques'],
                        ['label' => $pick(['fr' => '🧘 Détente', 'en' => '🧘 Relax', 'ar' => '🧘 استرخاء']),     'value' => 'détente'],
                        ['label' => $pick(['fr' => '⛺ Aventure', 'en' => '⛺ Adventure', 'ar' => '⛺ مغامرة']),  'value' => 'aventure'],
                    ],
                ],
            ],
        ];
    }

    private function askPartnerChoice(string $lang, ?string $reply): array
    {
        $pick = fn (array $m) => $m[$lang] ?? $m['fr'];

        return [
            'chat_message' => $reply ?? $pick([
                'fr' => "Souhaitez-vous un centre partenaire (réservable directement sur la plateforme "
                    . "avec services et équipements) ou un centre externe (informations de contact uniquement) ?",
                'en' => "Would you like a partner centre (bookable directly on the platform with services "
                    . "and equipment) or an external centre (contact information only)?",
                'ar' => "هل ترغب في مركز شريك (قابل للحجز مباشرة على المنصة مع خدمات ومعدات) أم مركز خارجي "
                    . "(معلومات الاتصال فقط)؟",
            ]),
            'clarification_questions' => [
                [
                    'id'       => 'partner_choice',
                    'question' => $pick(['fr' => 'Type de centre', 'en' => 'Centre type', 'ar' => 'نوع المركز']),
                    'multiple' => false,
                    'options'  => [
                        ['label' => $pick(['fr' => '🏕️ Centre partenaire (réservable)', 'en' => '🏕️ Partner centre (bookable)', 'ar' => '🏕️ مركز شريك (قابل للحجز)']), 'value' => 'partner_only'],
                        ['label' => $pick(['fr' => '📍 Centre externe (info seulement)', 'en' => '📍 External centre (info only)', 'ar' => '📍 مركز خارجي (معلومات فقط)']), 'value' => 'external_only'],
                        ['label' => $pick(['fr' => '🔍 Les deux options', 'en' => '🔍 Both options', 'ar' => '🔍 كلا الخيارين']), 'value' => 'any_centre'],
                    ],
                ],
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CENTRE FLOWS
    // ═══════════════════════════════════════════════════════════════════════════

    private function partnerCentreFlow(
        ProfileCampeur $profile,
        array          $intent,
        string         $message,
        array          $history,
        array          $missingCritical,
        ?string        $region,
    ): array {
        $region  = $region ?? $this->extractMentionedRegion($message);
        $centres = $this->reorderCentresByRegion(
            $this->centreLookup->findPartnerCentres($region),
            $this->extractMentionedRegion($message) ?? $region
        );

        // Exclude last-recommended centre on alternative-request turns.
        if ($this->excludeRecommendedId !== null) {
            $filtered = $centres->filter(fn ($c) => ($c['id'] ?? null) !== $this->excludeRecommendedId);
            if ($filtered->isNotEmpty()) {
                $centres = $filtered->values();
            }
        }

        if ($centres->isEmpty()) {
            // No partner centre — fall back to external discovery for the region
            $external = $this->externalCentreFlow($profile, $intent, $message, $history, $region);
            $lang     = $this->detectLanguage($message, $history);
            $loc      = $region ? " ({$region})" : '';
            $prefix   = match ($lang) {
                'en' => "No bookable partner centre is listed{$loc}. ",
                'ar' => "لا يوجد مركز شريك قابل للحجز{$loc}. ",
                default => "Aucun centre partenaire réservable n'est répertorié{$loc}. ",
            };
            $external['ai_summary'] = $prefix . ($external['ai_summary'] ?? '');
            return $external;
        }

        $top    = $this->pickTopPartner($centres, $intent);
        $result = $this->buildPartnerResult($top, $profile, $intent, $message, $history, $missingCritical);

        return $result;
    }

    private function combinedCentreFlow(
        ProfileCampeur $profile,
        array          $intent,
        string         $message,
        array          $history,
        array          $missingCritical,
        ?string        $region,
    ): array {
        $region    = $region ?? $this->extractMentionedRegion($message);
        $mentioned = $this->extractMentionedRegion($message) ?? $region;

        $partners  = $this->reorderCentresByRegion($this->centreLookup->findPartnerCentres($region), $mentioned);
        $externals = $this->reorderCentresByRegion($this->centreLookup->findExternalCentres($region), $mentioned);

        // Exclude last-recommended centre on alternative-request turns.
        if ($this->excludeRecommendedId !== null) {
            $filtered = $partners->filter(fn ($c) => ($c['id'] ?? null) !== $this->excludeRecommendedId);
            if ($filtered->isNotEmpty()) {
                $partners = $filtered->values();
            }
        }

        // No partner → recommendation becomes external, no alternatives
        if ($partners->isEmpty()) {
            return $this->externalCentreFlow($profile, $intent, $message, $history, $region);
        }

        $top    = $this->pickTopPartner($partners, $intent);
        $result = $this->buildPartnerResult($top, $profile, $intent, $message, $history, $missingCritical);

        // Show external centres side by side as alternatives
        $result['alternatives'] = $externals
            ->map(fn ($e) => [
                'type'          => 'centre_external',
                'id'            => $e['id'],
                'nom'           => $e['nom'],
                'region'        => $e['region'] ?? '',
                'adresse'       => $e['adresse'] ?? '',
                'contact_phone' => $e['contact_phone'] ?? null,
                'image'         => $e['image'] ?? null,
            ])
            ->values()
            ->all();

        return $result;
    }

    private function buildPartnerResult(
        array          $top,
        ProfileCampeur $profile,
        array          $intent,
        string         $message,
        array          $history,
        array          $missingCritical,
    ): array {
        $synthZone = $this->syntheticZoneForCentre($top);

        // Weather for the centre coordinates (PHP decision)
        $forecast       = null;
        $weatherSummary = null;
        $weatherRisk    = 'low';
        if (config('ai.features.weather', true) && $synthZone->lat && $synthZone->lng) {
            $forecast    = $this->weather->getForecastForZone($synthZone);
            $weatherRisk = $this->weather->getOverallRiskLevel($forecast);
            if ($this->weather->shouldWarnUser($forecast)) {
                $weatherSummary = $this->weather->getWeatherSummaryForPrompt($forecast);
            }
        }

        // Gear — skipped entirely when the user opted out of equipment.
        $wantsGear     = ($intent['gear_wanted'] ?? null) !== false;
        $gearChecklist = null;
        $fullGear      = [];
        if ($wantsGear) {
            if (config('ai.features.gear_assistant', true)) {
                $gearChecklist = $this->gearService->generateChecklist(
                    $profile, $synthZone, $forecast, $intent['group_size']
                );
            }
            [, $gear]   = $this->fetchContext(8, 12, null);
            $scoredGear = $this->recommender->scoreGear($profile, $gear);
            $fullGear   = $this->formatGearForResponse($gearChecklist, $scoredGear, 'plain');
        }

        // Gear choice: with-centre-equipment vs bring-own (only when wanted AND centre has equipment)
        $equipmentList = $top['equipment_list'] ?? [];
        $gearOptions   = ($wantsGear && ! empty($equipmentList))
            ? $this->buildGearOptions($fullGear, $equipmentList, $intent)
            : null;
        $defaultGear = $gearOptions ? $gearOptions['bring_own_gear']['items'] : $fullGear;

        // Cost: (price × nights) + (gear × nights × group_size)
        $cost = $this->calculateCost($defaultGear, $intent, (float) ($top['price_per_night'] ?? 0));

        $result = [
            'intent'      => $intent,
            'recommended' => [
                'type'                    => 'centre_partner',
                'id'                      => $top['id'],
                'centre_user_id'          => $top['centre_user_id'] ?? null,
                'nom'                     => $top['nom'],
                'region'                  => $top['region'] ?? '',
                'adresse'                 => $top['adresse'] ?? '',
                'price_per_night'         => (float) ($top['price_per_night'] ?? 0),
                'equipment_list'          => $equipmentList,
                'bookable_services_count' => $top['bookable_service_count'] ?? 0,
                'why'                     => '',
            ],
            'gear_list'       => $defaultGear,
            'weather_warning' => $weatherSummary,
            'estimated_cost'  => $cost,
            'ai_summary'      => '',
        ];
        if ($gearOptions) {
            $result['gear_options'] = $gearOptions;
        }

        // ── CALL 2 — text only ────────────────────────────────────────────────
        $llmContext = [
            'recommended_type'    => 'centre_partner',
            'accommodation_type'  => 'centre',
            'centre_nom'          => $top['nom'],
            'centre_region'       => $top['region'] ?? '',
            'price_per_night'     => (float) ($top['price_per_night'] ?? 0),
            'capacite'            => $top['capacite'] ?? null,
            'equipment_list'      => $equipmentList,
            'skill_level'         => $profile->skill_level ?? 'beginner',
            'budget_range'        => $profile->budget_range ?? 'moderate',
            'comfort_level'       => $profile->comfort_level ?? 'standard',
            'group_size'          => $intent['group_size'],
            'duration_nights'     => $intent['duration_nights'],
            'trip_style'          => $intent['trip_style'],
            'gear_items'          => array_map(fn ($g) => [
                'nom'      => $g['nom'],
                'category' => $g['category'] ?? '',
                'terrain'  => 'plain',
            ], $defaultGear),
            'weather_risk'        => $weatherRisk,
            'total_cost'          => $cost['total_estimate'],
            'missing_profile'     => $missingCritical,
            'has_external_centre' => false,
            'details_assumed'     => $intent['details_assumed'] ?? true,
        ];

        $decoded = $this->callTextLlm($llmContext, $message, $history, $missingCritical, null, $weatherRisk) ?? [];

        $result['recommended']['why'] = $decoded['zone_why'] ?? $this->fallbackCentreWhy($top, $profile);
        $result['ai_summary']         = $decoded['ai_summary'] ?? $this->fallbackCentreSummary($intent, $result, $top);
        $this->applyGearReasons($result['gear_list'], $decoded['gear_reasons'] ?? [], 'plain');
        if ($weatherSummary !== null) {
            $result['weather_warning'] = $decoded['weather_warning'] ?? $weatherSummary;
        }

        // Attach structured data
        if ($forecast !== null) {
            $result['weather_data'] = $forecast->toArray();
            $result['weather_risk'] = $weatherRisk;
        }
        if ($gearChecklist !== null) {
            $result['gear_checklist'] = $gearChecklist->toArray();
            $result['critical_alert'] = $this->gearService->getCriticalMissingAlert($gearChecklist);
        }

        return $result;
    }

    private function externalCentreFlow(
        ProfileCampeur $profile,
        array          $intent,
        string         $message,
        array          $history,
        ?string        $region,
    ): array {
        $region    = $region ?? $this->extractMentionedRegion($message);
        $externals = $this->reorderCentresByRegion(
            $this->centreLookup->findExternalCentres($region),
            $this->extractMentionedRegion($message) ?? $region
        );

        if ($externals->isEmpty()) {
            return [
                'intent'      => $intent,
                'recommended' => [
                    'type'          => 'centre_external',
                    'id'            => 0,
                    'nom'           => 'Aucun centre externe trouvé',
                    'region'        => $region ?? '',
                    'adresse'       => '',
                    'contact_phone' => null,
                    'image'         => null,
                    'why'           => '',
                ],
                'gear_list'      => [],
                'estimated_cost' => $this->zeroCentreCost($intent, 'Contactez le centre directement pour les tarifs et la disponibilité.'),
                'ai_summary'     => "Je n'ai pas trouvé de centre externe répertorié"
                    . ($region ? " à {$region}" : '')
                    . '. Essayez une autre région ou demandez une zone de camping sauvage.',
                'no_centre_found' => true,
            ];
        }

        $top = $externals->first();

        $result = [
            'intent'      => $intent,
            'recommended' => [
                'type'          => 'centre_external',
                'id'            => $top['id'],
                'nom'           => $top['nom'],
                'region'        => $top['region'] ?? '',
                'adresse'       => $top['adresse'] ?? '',
                'contact_phone' => $top['contact_phone'] ?? null,
                'image'         => $top['image'] ?? null,
                'why'           => '',
            ],
            'gear_list'      => [],
            'estimated_cost' => $this->zeroCentreCost($intent, 'Contactez le centre directement pour les tarifs et la disponibilité.'),
            'ai_summary'     => '',
        ];

        $llmContext = [
            'recommended_type'    => 'centre_external',
            'accommodation_type'  => 'centre',
            'centre_nom'          => $top['nom'],
            'centre_region'       => $top['region'] ?? '',
            'centre_adresse'      => $top['adresse'] ?? '',
            'skill_level'         => $profile->skill_level ?? 'beginner',
            'group_size'          => $intent['group_size'],
            'duration_nights'     => $intent['duration_nights'],
            'trip_style'          => $intent['trip_style'],
            'total_cost'          => 0,
            'cost_note'           => 'Price unknown — external centre, the camper must contact it directly.',
            'has_external_centre' => true,
            'details_assumed'     => $intent['details_assumed'] ?? true,
        ];

        $decoded = $this->callTextLlm($llmContext, $message, $history, [], null, 'low') ?? [];

        $result['recommended']['why'] = $decoded['zone_why'] ?? $this->fallbackExternalWhy($top);
        $result['ai_summary']         = $decoded['ai_summary'] ?? $this->fallbackExternalSummary($top, $region);

        return $result;
    }

    // ── Centre helpers ──────────────────────────────────────────────────────────

    private function reorderCentresByRegion(Collection $centres, ?string $region): Collection
    {
        if (! $region || $centres->isEmpty()) {
            return $centres;
        }

        $needle = mb_strtolower($region);
        $hay    = fn ($c) => mb_strtolower(($c['region'] ?? '') . ' ' . ($c['adresse'] ?? ''));

        $match = $centres->filter(fn ($c) => str_contains($hay($c), $needle));
        if ($match->isEmpty()) {
            return $centres;
        }
        $other = $centres->reject(fn ($c) => str_contains($hay($c), $needle));

        return $match->concat($other)->values();
    }

    private function pickTopPartner(Collection $centres, array $intent): array
    {
        $group  = max(1, (int) ($intent['group_size'] ?? 2));
        $budget = $intent['budget'] ?? 'moderate';
        $best   = null;

        foreach ($centres as $c) {
            $capacite = $c['capacite'] ?? null;
            $capOk    = $capacite === null || (int) $capacite >= $group;
            $priceOk  = $this->priceFitsBudget((float) ($c['price_per_night'] ?? 0), $budget);

            if ($capOk && $priceOk) {
                return $c;
            }
            if ($best === null && $capOk) {
                $best = $c;
            }
        }

        return $best ?? $centres->first();
    }

    private function priceFitsBudget(float $price, string $budget): bool
    {
        if ($price <= 0) {
            return true;
        }
        return match ($budget) {
            'budget'  => $price <= 60,
            'premium' => true,
            default   => $price <= 150,
        };
    }

    private function syntheticZoneForCentre(array $centre): CampingZone
    {
        // In-memory only (never saved). The +900000 id offset keeps gear/weather
        // cache keys from colliding with real camping zones.
        $zone               = new CampingZone();
        $zone->id           = 900000 + (int) ($centre['id'] ?? 0);
        $zone->nom          = $centre['nom'] ?? 'Centre';
        $zone->region       = $centre['region'] ?? '';
        $zone->terrain_type = 'plain';
        $zone->difficulty   = 'easy';
        $zone->lat          = $centre['lat'] ?? null;
        $zone->lng          = $centre['lng'] ?? null;

        return $zone;
    }

    /**
     * Build the two gear variants for a partner centre with on-site equipment.
     */
    private function buildGearOptions(array $fullGear, array $equipmentList, array $intent): array
    {
        // Centre equipment → camping gear categories it makes redundant
        $excludeMap = [
            'kitchen'        => ['Cuisine outdoor'],
            'bbq_area'       => ['Cuisine outdoor'],
            'electricity'    => ['Éclairage'],
            'drinking_water' => ['Filtration', 'Hydratation', 'Eau'],
        ];
        $labels = [
            'kitchen'        => 'cuisine',
            'bbq_area'       => 'zone BBQ',
            'showers'        => 'douches',
            'electricity'    => 'électricité',
            'drinking_water' => 'eau potable',
            'toilets'        => 'toilettes',
            'parking'        => 'parking',
            'security'       => 'sécurité',
            'wifi'           => 'wifi',
            'swimming_pool'  => 'piscine',
        ];

        $excluded = [];
        $covered  = [];
        foreach ($equipmentList as $eq) {
            $covered[] = $labels[$eq] ?? $eq;
            foreach ($excludeMap[$eq] ?? [] as $cat) {
                $excluded[] = $cat;
            }
        }

        $reduced = array_values(array_filter(
            $fullGear,
            fn ($g) => ! in_array($g['category'] ?? '', $excluded, true)
        ));

        return [
            'with_centre_equipment' => [
                'description'         => 'Utiliser les équipements du centre (cuisine, douches, etc.)',
                'items'               => $reduced,
                'covered_by_centre'   => array_values(array_unique($covered)),
                'estimated_gear_cost' => $this->gearEstimate($reduced, $intent),
            ],
            'bring_own_gear' => [
                'description'         => 'Apporter votre propre équipement',
                'items'               => $fullGear,
                'estimated_gear_cost' => $this->gearEstimate($fullGear, $intent),
            ],
        ];
    }

    private function gearEstimate(array $items, array $intent): float
    {
        $perNight  = array_sum(array_column($items, 'tarif_nuit'));
        $nights    = max(1, (int) ($intent['duration_nights'] ?? 2));
        $groupSize = max(1, (int) ($intent['group_size'] ?? 2));

        return round($perNight * $nights * $groupSize, 2);
    }

    private function zeroCentreCost(array $intent, ?string $note = null): array
    {
        $cost = [
            'accommodation_per_night' => 0,
            'gear_per_night'          => 0,
            'total_estimate'          => 0,
            'nights'                  => max(1, (int) ($intent['duration_nights'] ?? 2)),
            'group_size'              => max(1, (int) ($intent['group_size'] ?? 2)),
            'currency'                => 'TND',
        ];
        if ($note !== null) {
            $cost['external_centre_note'] = $note;
        }
        return $cost;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SHARED PHP DECISION HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Pure PHP arithmetic. Optionally adds per-night accommodation (centres).
     *
     * gear_total          = gear_per_night × nights × group_size
     * accommodation_total = accommodation_per_night × nights   (no group multiplier)
     */
    private function calculateCost(array $gearItems, array $intent, float $accommodationPerNight = 0.0): array
    {
        $gearPerNight = array_sum(array_column($gearItems, 'tarif_nuit'));
        $nights       = max(1, (int) ($intent['duration_nights'] ?? 2));
        $groupSize    = max(1, (int) ($intent['group_size'] ?? 2));

        $gearTotal          = $gearPerNight * $nights * $groupSize;
        $accommodationTotal = $accommodationPerNight * $nights;

        $cost = [
            'gear_per_night' => round($gearPerNight, 2),
            'total_estimate' => round($accommodationTotal + $gearTotal, 2),
            'nights'         => $nights,
            'group_size'     => $groupSize,
            'currency'       => 'TND',
        ];

        if ($accommodationPerNight > 0) {
            $cost = ['accommodation_per_night' => round($accommodationPerNight, 2)] + $cost;
        }

        return $cost;
    }

    // Ordered terrain priority for gear. Leads with the categories that make a
    // terrain distinctive, but still includes shelter/sleep essentials so each
    // kit stays sensible. Drives both the checklist reorder and the fallback.
    private const PREFERRED_GEAR_BY_TERRAIN = [
        'coastal'  => ['Tentes', 'Transport & stockage', 'Sacs de couchage', 'Cuisine outdoor'],
        'mountain' => ['Vêtements techniques', 'Navigation', 'Sécurité', 'Tentes', 'Sacs de couchage'],
        'desert'   => ['Navigation', 'Sécurité', 'Transport & stockage', 'Tentes', 'Sacs de couchage'],
        'forest'   => ['Tentes', 'Navigation', 'Sacs de couchage', 'Cuisine outdoor'],
        'wetland'  => ['Vêtements techniques', 'Sécurité', 'Tentes', 'Sacs de couchage'],
    ];

    /**
     * PHP decides the gear list — never the LLM. Prefers the pre-filtered gear
     * checklist (terrain/weather-driven inside GearAssistantService); otherwise
     * falls back to terrain-aware top-5 scored gear. Each item carries an empty
     * 'reason' for CALL 2 to fill.
     *
     * NOTE: differentiation by terrain depends on zones carrying a terrain_type
     * (and on a broad enough gear pool). With the current seed data every zone
     * has terrain_type = NULL, so the checklist collapses to the base kit and
     * looks similar across destinations — populating terrain_type resolves this.
     */
    private function formatGearForResponse(
        ?GearChecklist $gearChecklist,
        Collection     $scoredGear,
        string         $terrainType,
    ): array {
        $preferred = self::PREFERRED_GEAR_BY_TERRAIN[$terrainType] ?? [];

        if ($gearChecklist !== null && ! empty($gearChecklist->items)) {
            $available = array_values(array_filter(
                $gearChecklist->items,
                fn ($i) => $i->materielle_id > 0 && $i->is_available
            ));
            if (! empty($available)) {
                // Surface terrain-distinctive categories first (stable sort keeps
                // the checklist's base ordering within the same priority) so a
                // desert kit visibly differs from a forest kit.
                if (! empty($preferred)) {
                    usort($available, function ($a, $b) use ($preferred) {
                        $ai = array_search($a->category, $preferred, true);
                        $bi = array_search($b->category, $preferred, true);
                        $ai = $ai === false ? PHP_INT_MAX : $ai;
                        $bi = $bi === false ? PHP_INT_MAX : $bi;
                        return $ai <=> $bi;
                    });
                }
                return array_map(fn ($i) => $this->mapChecklistItem($i), array_slice($available, 0, 5));
            }
        }

        // Fallback: terrain-aware ranking of scored gear
        $sorted = $scoredGear->sort(function ($a, $b) use ($preferred) {
            $am = in_array($a->category->nom ?? '', $preferred, true) ? 0 : 1;
            $bm = in_array($b->category->nom ?? '', $preferred, true) ? 0 : 1;
            if ($am !== $bm) {
                return $am <=> $bm;
            }
            return ($b->score ?? 0) <=> ($a->score ?? 0);
        })->values();

        return $sorted->take(5)->map(fn ($g) => $this->mapScoredGear($g))->all();
    }

    private function mapChecklistItem(\App\Services\AI\Gear\GearChecklistItem $i): array
    {
        return [
            'id'         => $i->materielle_id,
            'nom'        => $i->nom,
            'brand'      => $i->brand,
            'tarif_nuit' => $i->tarif_nuit,
            'url'        => $i->url,
            'reason'     => '',
            'category'   => $i->category,
            'priority'   => $i->priority,
        ];
    }

    private function mapScoredGear(object $g): array
    {
        return [
            'id'         => $g->id,
            'nom'        => $g->nom,
            'brand'      => $g->brand ?? '',
            'tarif_nuit' => (float) ($g->tarif_nuit ?? 0),
            'url'        => '/marketplace/materielle/' . $g->id,
            'reason'     => '',
            'category'   => $g->category->nom ?? '',
            'priority'   => 3,
        ];
    }

    /**
     * Map a requested trip style to a concrete terrain for gear selection when
     * the chosen zone has no terrain_type of its own. Returns null for styles
     * with no clear terrain (e.g. "aventure", "famille").
     */
    private function terrainFromTripStyle(string $tripStyle): ?string
    {
        return match (mb_strtolower($tripStyle)) {
            'desert'  => 'desert',
            'coastal' => 'coastal',
            'nature'  => 'mountain',
            default   => null,
        };
    }

    private function applyGearReasons(array &$gearList, array $reasons, string $terrainType): void
    {
        $i = 0;
        foreach ($gearList as &$item) {
            $item['reason'] = $reasons[$i] ?? "Recommandé pour terrain {$terrainType}.";
            $i++;
        }
        unset($item);
    }

    private function userOptedOutOfGear(array $history, string $message): bool
    {
        if (str_contains(mb_strtolower($message), 'sans équipement')) {
            return true;
        }
        foreach ($history as $h) {
            if (($h['role'] ?? '') === 'user'
                && str_contains(mb_strtolower($h['content'] ?? ''), 'sans équipement')) {
                return true;
            }
        }
        return false;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CALL 2 — text-only LLM
    // ═══════════════════════════════════════════════════════════════════════════

    private function callTextLlm(
        array   $llmContext,
        string  $message,
        array   $history,
        array   $missingCritical,
        ?string $safetyAlert,
        string  $weatherRisk,
    ): ?array {
        $systemPrompt = $this->buildTextOnlyPrompt(
            $missingCritical,
            $safetyAlert,
            $weatherRisk,
            $llmContext['recommended_type'] ?? 'zone',
            $this->detectLanguage($message, $history),
        );
        $userMessage = 'Context: ' . json_encode($llmContext, JSON_UNESCAPED_UNICODE)
            . "\n\nUser message: " . $message;

        try {
            $raw     = $this->llm->complete($systemPrompt, $userMessage, 500, $history);
            $content = $this->stripMarkdownFences($raw);

            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $first = strpos($content, '{');
                $last  = strrpos($content, '}');
                if ($first !== false && $last !== false && $last > $first) {
                    $decoded = json_decode(substr($content, $first, $last - $first + 1), true);
                }
            }

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::error('ai_text_generation_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildTextOnlyPrompt(
        array   $missingCritical = [],
        ?string $safetyAlert     = null,
        string  $weatherRisk     = 'low',
        string  $recommendedType = 'zone',
        string  $lang            = 'fr',
    ): string {
        $languageName = $this->languageName($lang);
        $languageNote = "\nLANGUAGE: Write EVERY text field (zone_why, ai_summary, gear_reasons, "
            . "weather_warning) in {$languageName}. The user wrote in {$languageName} — match it exactly.";

        $safetyNote = $safetyAlert
            ? "\nSAFETY: Assessment returned '{$safetyAlert}'. Mention this clearly in ai_summary."
            : '';

        $weatherNote = in_array($weatherRisk, ['high', 'extreme'], true)
            ? "\nWEATHER: Risk level is {$weatherRisk}. Write a clear weather_warning."
            : '';

        $profileNote = ! empty($missingCritical)
            ? "\nPROFILE: Ask ONE question at end of ai_summary for: " . $missingCritical[0]
            : '';

        $zoneWhyRule = match ($recommendedType) {
            'centre_partner'  => 'zone_why: explain how the centre\'s services/equipment match the camper and how the price fits the budget.',
            'centre_external' => 'zone_why: explain the location only and clearly state that direct contact with the centre is required.',
            default           => 'zone_why: reference the terrain type and the camper\'s skill level.',
        };

        return <<<PROMPT
LANGUAGE RULE (highest priority, overrides everything):
Detect the language of the user's message and respond in that exact language throughout your entire response.
French message → French response.
English message → English response.
Arabic message → Arabic response.
Tunisian dialect → respond in Tunisian dialect or French.
Mixed language → use the dominant language.
This applies to zone_why, ai_summary, gear_reasons, and weather_warning. Every field must be in the same language.
Never mix languages within a single response.

You are TunisiaCamp's camping assistant. The PHP backend has already decided:
- Which zone or centre to recommend
- Which gear to suggest
- The exact cost

Your ONLY job is to write natural language text for these fields.
Respond ONLY with valid JSON — no preamble, no markdown.

{
  "zone_why":       "1 inviting sentence on why this recommendation fits the camper and request",
  "ai_summary":     "A warm, natural 1-2 sentence summary naming the place, group size, nights and total cost (TND)",
  "gear_reasons":   ["1 short sentence per gear item explaining why it's needed"],
  "weather_warning":"1 sentence warning if weather is bad, null if weather is fine"
}

Rules:
1. {$zoneWhyRule}
2. ai_summary must state the actual group size, nights, and total cost from context — copy the numbers exactly.
3. If "details_assumed" is true in context, briefly add that you assumed the group size / nights and the user can adjust.
4. gear_reasons array must have exactly the same number of items as gear_items in context.
5. Each gear reason must mention the terrain or trip context.
6. Never invent information not in the context (no made-up prices, dates, or places).
7. CURRENCY: all costs are in Tunisian Dinar (TND). NEVER write euros, dollars, or convert — always "TND".
8. Tone: friendly and inviting, never robotic. Be concise: ai_summary max 2 sentences; zone_why max 20 words; each gear_reason max 15 words.
9. Do not repeat yourself or add filler like "you will be surrounded by nature".{$languageNote}{$safetyNote}{$weatherNote}{$profileNote}
PROMPT;
    }

    // ── Rule-based text fallbacks (used when the LLM is unavailable) ────────────

    private function fallbackZoneWhy(mixed $zoneData, ProfileCampeur $profile, string $terrainType): string
    {
        if (! $zoneData) {
            return 'Zone sélectionnée selon vos préférences.';
        }
        $skill = $profile->skill_level ?? 'beginner';
        $diff  = $zoneData->difficulty ?? 'easy';

        return "Zone {$terrainType} de difficulté {$diff}, adaptée au niveau {$skill}.";
    }

    private function fallbackSummary(array $intent, array $result): string
    {
        $nights = $intent['duration_nights'] ?? 2;
        $group  = $intent['group_size'] ?? 2;
        $total  = $result['estimated_cost']['total_estimate'] ?? 0;
        $zone   = $result['recommended_zone']['nom'] ?? ($result['recommended']['nom'] ?? 'cette destination');

        $nightWord  = $nights === 1 ? 'nuit' : 'nuits';
        $personWord = $group === 1 ? 'personne' : 'personnes';

        return "Plan prévu pour {$group} {$personWord}, {$nights} {$nightWord} à {$zone}. "
            . "Coût total estimé : {$total} TND.";
    }

    private function fallbackCentreWhy(array $centre, ProfileCampeur $profile): string
    {
        $equip = ! empty($centre['equipment_list'])
            ? ' avec ' . implode(', ', array_slice($centre['equipment_list'], 0, 3))
            : '';
        $price = (float) ($centre['price_per_night'] ?? 0);
        $priceTxt = $price > 0 ? " à {$price} TND/nuit" : '';

        return "Centre partenaire{$equip}{$priceTxt}, adapté à votre séjour.";
    }

    private function fallbackCentreSummary(array $intent, array $result, array $centre): string
    {
        $nights = $intent['duration_nights'] ?? 2;
        $group  = $intent['group_size'] ?? 2;
        $total  = $result['estimated_cost']['total_estimate'] ?? 0;
        $nom    = $centre['nom'] ?? 'ce centre';

        $nightWord  = $nights === 1 ? 'nuit' : 'nuits';
        $personWord = $group === 1 ? 'personne' : 'personnes';

        return "Séjour au centre {$nom} pour {$group} {$personWord}, {$nights} {$nightWord}. "
            . "Coût total estimé : {$total} TND (hébergement + équipement).";
    }

    private function fallbackExternalWhy(array $centre): string
    {
        $region = $centre['region'] ?? '';
        $loc    = $region !== '' ? " situé à {$region}" : '';

        return "Centre externe{$loc} — contactez-le directement pour réserver.";
    }

    private function fallbackExternalSummary(array $centre, ?string $region): string
    {
        $nom = $centre['nom'] ?? 'ce centre';

        return "Voici {$nom}" . ($region ? " ({$region})" : '')
            . '. Ce centre est informatif uniquement : contactez-le directement pour les tarifs '
            . 'et la disponibilité.';
    }

    /**
     * Clone the static ProfileCampeur and override specific fields with
     * behavioral values when the behavioral profile has sufficient confidence.
     *
     * The clone is used for the recommendation engine only — the original
     * $profile object is never mutated so profile-saving logic upstream is safe.
     *
     * Behavioral wins over static when:
     *   - behavioral.has_sufficient_data = true  (confidence >= 0.4), AND
     *   - the specific behavioral value is not null.
     */
    private function buildEffectiveProfile(ProfileCampeur $profile, BehavioralProfile $behavioral): ProfileCampeur
    {
        // Always work on a clone so the original profile is never mutated.
        $effective = clone $profile;

        // Attach behavioral metadata so RecommendationService can build accurate
        // user vectors without changing its method signatures.
        // These are read via Eloquent's getAttribute() and default to null/0.0 when absent.
        $effective->setAttribute('behavioral_confidence',       $behavioral->confidence_score);
        $effective->setAttribute('inferred_terrain_preference', $behavioral->inferred_terrain_preference);
        $effective->setAttribute('inferred_group_size',         $behavioral->inferred_group_size);

        if (! $behavioral->has_sufficient_data) {
            // Behavioral data exists but confidence is too low; return the clone with
            // the metadata attributes set (so vectors degrade gracefully) but without
            // overriding the static profile values.
            return $effective;
        }

        if ($behavioral->inferred_skill_level !== null) {
            $effective->skill_level = $behavioral->inferred_skill_level;
        }

        if ($behavioral->inferred_budget_range !== null) {
            $effective->budget_range = $behavioral->inferred_budget_range;
        }

        if ($behavioral->inferred_terrain_preference !== null) {
            $current = is_array($effective->preferred_trip_styles)
                ? $effective->preferred_trip_styles
                : [];
            $effective->preferred_trip_styles = array_values(
                array_unique(array_merge([$behavioral->inferred_terrain_preference], $current))
            );
        }

        return $effective;
    }

    /**
     * Returns true when the message is clearly a new-trip request with no
     * specific destination yet — "je veux camper", "nouveau voyage", etc.
     *
     * Used as Pre-check 2 in the POST_RECOMMENDATION phase to avoid sending
     * these messages through the confirmation classifier, which could
     * misclassify them as "modify" or "other".
     *
     * Note: always call extractMentionedRegion() FIRST. A message like
     * "je veux camper à Bizerte" matches "je veux camper" but should be
     * treated as Pre-check 1 (place-name detection), not Pre-check 2.
     */
    private function isNewTripMessage(string $message): bool
    {
        $lower = mb_strtolower(trim($message));

        $signals = [
            'je veux camper',
            'je voudrais camper',
            'nouveau voyage',
            'nouvelle recherche',
            'nouvelle destination',
            'autre destination',
            'changer de destination',
            'recommencer',
            'repartir de zéro',
            'new trip',
            'start over',
            'بداية جديدة',   // Arabic: new start
            'رحلة جديدة',    // Arabic: new trip
            'تخييم جديد',    // Arabic: new camping trip
        ];

        foreach ($signals as $signal) {
            if (str_contains($lower, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the user wants a different recommendation within the same trip context.
     * "recommend another centre", "une autre option", "show me something else".
     */
    private function isAlternativeRequest(string $message): bool
    {
        $lower   = mb_strtolower(trim($message));
        $signals = [
            'another', 'other option', 'different', 'alternative', 'show me more',
            'une autre', 'autre option', 'autre centre', 'autre zone',
            'montre-moi autre', 'différent', 'propose-moi autre', 'montre autre',
            'خيار آخر', 'غير هذا', 'بديل', 'شيء آخر', 'اقتراح آخر',
        ];
        foreach ($signals as $signal) {
            if (str_contains($lower, $signal)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when the user explicitly wants to modify/change the current plan.
     * Triggered by the frontend "Modifier le plan" button which sends a known phrase.
     */
    private function isModifyRequest(string $message): bool
    {
        $lower   = mb_strtolower(trim($message));
        $signals = [
            'modifier le plan', 'changer le plan',
            'je veux modifier', 'je veux changer',
            'modify the plan', 'change the plan',
            'i want to modify', 'i want to change',
            'أريد التعديل', 'أريد تغيير الخطة',
        ];
        foreach ($signals as $signal) {
            if (str_contains($lower, $signal)) {
                return true;
            }
        }
        return false;
    }

    private function extractMentionedRegion(string $message): ?string
    {
        $regions = [
            'bizerte'   => 'Bizerte',  'binzert'   => 'Bizerte',  'benzert'  => 'Bizerte',
            'بنزرت'     => 'Bizerte',  'tunis'     => 'Tunis',    'تونس'     => 'Tunis',
            'nabeul'    => 'Nabeul',   'نابل'      => 'Nabeul',   'hammamet' => 'Nabeul',
            'tabarka'   => 'Tabarka',  'تبرقة'     => 'Tabarka',
            'ain draham'=> 'Ain Draham',
            'sousse'    => 'Sousse',   'سوسة'      => 'Sousse',
            'sfax'      => 'Sfax',     'صفاقس'     => 'Sfax',
            'tozeur'    => 'Tozeur',   'توزر'      => 'Tozeur',
            'douz'      => 'Douz',     'دوز'       => 'Douz',
            'djerba'    => 'Djerba',   'jerba'     => 'Djerba',   'جربة'     => 'Djerba',
            'zaghouan'  => 'Zaghouan', 'béja'      => 'Béja',     'beja'     => 'Béja',
            'jendouba'  => 'Jendouba', 'jandouba'  => 'Jendouba',
            'bni mtir'  => 'Jendouba', 'bnimtir'   => 'Jendouba',
            'siliana'   => 'Siliana',  'kairouan'  => 'Kairouan',
            'monastir'  => 'Monastir', 'mahdia'    => 'Mahdia',
            'gafsa'     => 'Gafsa',    'gabes'     => 'Gabès',    'gabès'    => 'Gabès',
            'medenine'  => 'Médenine', 'médenine'  => 'Médenine',
            'tataouine' => 'Tataouine','kebili'    => 'Kébili',
        ];

        $lower = mb_strtolower($message);
        foreach ($regions as $keyword => $region) {
            if (str_contains($lower, $keyword)) {
                return $region;
            }
        }
        return null;
    }
    private function fetchContext(
        int     $zoneLimit = 8,
        int     $gearLimit = 12,
        ?string $destinationRegion = null,
    ): array {
        $zonesQuery = CampingZone::where('status', true)
            ->where('is_closed', false)
            ->select([
                'id', 'nom', 'region', 'terrain_type', 'difficulty',
                'is_beginner_friendly', 'rating', 'activities',
            ]);

        if ($destinationRegion !== null) {
            $zonesQuery->where(
                'region', 'like',
                '%' . addcslashes($destinationRegion, '%_') . '%'
            );
        }

        // reviews_count is needed for the social_proof dimension of the zone vector.
        $zonesQuery->addSelect('reviews_count');

        $zones = $zonesQuery->limit($zoneLimit)->get();

        $gear = Materielles::where('status', 'up')
            ->where('quantite_dispo', '>', 0)
            ->with('category:id,nom')
            ->select([
                'id', 'nom', 'brand', 'trip_type_tags',
                'tarif_nuit', 'condition', 'is_rentable', 'category_id',
            ])
            ->limit($gearLimit)
            ->get()
            ->map(function (Materielles $item) {
                $item->nom = mb_substr($item->nom, 0, 60);
                return $item;
            });

        return [$zones, $gear];
    }

    // ── Greeting helper ─────────────────────────────────────────────────────────

    private function fallbackGreeting(string $lang = 'fr'): string
    {
        return match ($lang) {
            'en' => "Hello! I'm the TunisiaCamp assistant. I can help you plan a trip, find a "
                . "camping zone, or recommend rental gear in Tunisia. How can I help?",
            'ar' => "مرحباً! أنا مساعد TunisiaCamp. يمكنني مساعدتك في تخطيط رحلة، إيجاد منطقة "
                . "تخييم، أو اقتراح معدات للإيجار في تونس. كيف يمكنني مساعدتك؟",
            default => "Bonjour ! Je suis l'assistant camping TunisiaCamp. Je peux vous aider "
                . "à planifier un voyage, trouver une zone de camping ou recommander du "
                . "matériel en location. Comment puis-je vous aider ?",
        };
    }

    // ── Language ──────────────────────────────────────────────────────────────

    /**
     * Detect the user's language from the message (and recent history) so the
     * bot can reply and clarify in the same language. Returns 'ar' | 'en' | 'fr'
     * (French is the platform default and the tie-breaker).
     */
    private function detectLanguage(string $message, array $history = []): string
    {
        // Judge by the USER's own words only — never the bot's (French) greeting
        // or replies, which would otherwise flip detection to French.
        $text = $this->userText($message, $history);

        // Arabic script is unambiguous
        if (preg_match('/\p{Arabic}/u', $text)) {
            return 'ar';
        }

        $lower = ' ' . $text . ' ';

        $fr = 0;
        foreach ([' je ', ' veux ', ' un ', ' une ', ' des ', ' le ', ' la ', ' les ', ' avec ',
                  ' pour ', ' dans ', ' où ', ' bonjour ', ' salut ', ' mon ', ' nuits ',
                  ' personnes ', ' équipement ', ' centre ', ' montagne ', ' plage ', ' forêt ',
                  ' désert ', ' aller '] as $w) {
            if (str_contains($lower, $w)) {
                $fr++;
            }
        }
        if (preg_match('/[éèêàâçùôîï]/u', $lower)) {
            $fr++;
        }

        $en = 0;
        foreach ([' i ', ' want ', ' the ', ' to ', ' a ', ' with ', ' for ', ' in ', ' where ',
                  ' hello ', ' hi ', ' my ', ' nights ', ' people ', ' gear ', ' center ',
                  ' mountain ', ' beach ', ' forest ', ' desert ', ' go ', ' need ', ' would '] as $w) {
            if (str_contains($lower, $w)) {
                $en++;
            }
        }

        return $en > $fr ? 'en' : 'fr';
    }

    private function languageName(string $lang): string
    {
        return match ($lang) {
            'en'    => 'English',
            'ar'    => 'Arabic',
            default => 'French',
        };
    }

    /**
     * Lowercased text of the USER's own turns (current message + user-role
     * history). Used by the clarification gates so the bot's own greeting and
     * replies never count as something the user has specified.
     */
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

    /**
     * True when the user never gave any number — so group size / nights were
     * defaulted. Lets CALL 2 say "I assumed 2 people for 2 nights" instead of
     * silently presenting assumptions as facts.
     */
    private function detailsAssumed(string $message, array $history): bool
    {
        return ! preg_match('/\d/', $this->userText($message, $history));
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    private function stripMarkdownFences(string $content): string
    {
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content ?? '');
        return trim($content ?? '');
    }
}
