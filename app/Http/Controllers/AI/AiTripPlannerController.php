<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTripPlanJob;
use App\Models\CampingZone;
use App\Models\Materielles;
use App\Models\Reservations_centre;
use App\Models\Reservations_materielles;
use App\Services\AI\ExplainabilityService;
use App\Services\AI\RecommendationService;
use App\Services\AI\BehavioralProfileService;
use App\Services\AI\Booking\BookingSummary;
use App\Services\AI\BookingPreparationService;
use App\Services\AI\ConversationStateService;
use App\Services\AI\GroupACollectorService;
use App\Services\AI\TripPlannerService;
use App\Services\AI\GearAssistantService;
use App\Services\AI\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Balance;
class AiTripPlannerController extends Controller
{
    public function __construct(
        private readonly TripPlannerService        $tripPlannerService,
        private readonly RecommendationService     $recommender,
        private readonly WeatherService            $weatherService,
        private readonly GearAssistantService      $gearService,
        private readonly GroupACollectorService    $groupACollector,
        private readonly ConversationStateService  $conversationState,
        private readonly BookingPreparationService $bookingPreparation,
        private readonly BehavioralProfileService  $behavioralProfileService,
    ) {}

    /**
     * POST /api/ai/trip-planner
     */
    public function plan(Request $request): JsonResponse
    {
        $request->validate([
            'message'           => ['required', 'string', 'max:1000'],
            // Accept a long transcript but keep only the most recent turns below —
            // never reject the request just because the chat has grown.
            'history'           => ['nullable', 'array', 'max:100'],
            'history.*.role'    => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1000'],
        ]);

        if (! config('ai.features.trip_planner')) {
            return response()->json(['error' => 'Feature disabled'], 503);
        }

        try {
            $message = $request->input('message');
            // Keep only the last 10 turns — older context is dropped automatically
            // so a long conversation never errors out.
            $history = array_slice($request->input('history', []), -10);

            if (config('ai.queue_driver') === 'database') {
                $jobId = Str::uuid()->toString();
                Cache::put('ai:job:' . $jobId, ['status' => 'processing'], 3600);

                ProcessTripPlanJob::dispatch(Auth::id(), $message, $jobId)->onQueue('ai');

                return response()->json([
                    'job_id'   => $jobId,
                    'status'   => 'processing',
                    'poll_url' => '/api/ai/status/' . $jobId,
                ], 202);
            }

            $result = $this->tripPlannerService->plan(Auth::user(), $message, $history);

            return response()->json([
                'success'         => true,
                'trip_plan'       => $result,
                'profile_updated' => $result['profile_updated'] ?? null,
            ]);

        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 429);
        } catch (\Exception $e) {
            return response()->json(['error' => 'AI service error'], 500);
        }
    }

    /**
     * POST /api/ai/trip-planner/group-a
     *
     * Receives the structured modal form submission (Group A fields).
     * Skips LLM Call 1 entirely — this is structured data, not natural language.
     * Merges the fields into state, then runs the pipeline if Group A is complete.
     */
    public function groupA(Request $request): JsonResponse
    {
        $request->validate([
            'fields'                    => ['required', 'array'],
            'fields.group_size'         => ['nullable', 'integer', 'min:1', 'max:50'],
            'fields.duration_nights'    => ['nullable', 'integer', 'min:1', 'max:30'],
            'fields.accommodation_type' => ['nullable', 'string', 'in:zone,centre'],
            'fields.wants_gear'         => ['nullable'],
        ]);

        if (! config('ai.features.trip_planner')) {
            return response()->json(['error' => 'Feature disabled'], 503);
        }

        $user   = Auth::user();
        $fields = $request->input('fields', []);

        $mergedState = $this->groupACollector->applyModalSubmission($user->id, $fields);

        if (! $this->conversationState->isGroupAComplete($mergedState)) {
            // A field is still missing (destination was not set before submission).
            $clarification = $this->groupACollector->check($mergedState);
            return response()->json([
                'success'   => true,
                'trip_plan' => $clarification,
            ]);
        }

        $result = $this->tripPlannerService->planFromCurrentState($user);

        return response()->json([
            'success'   => true,
            'trip_plan' => $result,
        ]);
    }

    // ── Booking preparation & confirmation ────────────────────────────────────

    /**
     * POST /api/ai/trip-planner/prepare-booking
     *
     * Validates the last recommendation, prices everything, and returns a
     * BookingSummary for the user to review. No DB record is created here.
     * The summary is cached for 15 minutes under 'booking_summary:{userId}'.
     */
    public function prepareBooking(Request $request): JsonResponse
    {
        if (! config('ai.features.trip_planner')) {
            return response()->json(['error' => 'Feature disabled'], 503);
        }

        $user  = Auth::user();
        $state = $this->conversationState->load($user->id);

        if (! $this->conversationState->isGroupAComplete($state)) {
            return response()->json([
                'error' => 'Veuillez d\'abord compléter les informations de votre voyage (destination, durée, groupe, hébergement, équipements).',
            ], 422);
        }

        $recommendation = Cache::get('last_recommendation:' . $user->id);

        if ($recommendation === null) {
            return response()->json([
                'error' => 'Aucune recommandation disponible. Veuillez d\'abord obtenir une recommandation.',
            ], 422);
        }

        $summary = $this->bookingPreparation->prepare($recommendation, $state, $user);

        Cache::put('booking_summary:' . $user->id, $summary->toArray(), 900);

        return response()->json([
            'booking_summary' => $summary->toArray(),
            'message'         => 'Voici le récapitulatif de votre réservation. Confirmez pour procéder au paiement.',
        ]);
    }

    /**
     * POST /api/ai/trip-planner/confirm-booking
     *
     * Creates the reservation records from a valid cached BookingSummary.
     * Does NOT trigger payment — returns reservation IDs for the frontend to
     * redirect to the existing payment flow.
     *
     * Hard rules:
     *   - Rejects expired summaries (> 15 min old).
     *   - Rejects non-bookable summaries.
     *   - Wraps all DB writes in a single transaction (all or nothing).
     *   - Deletes the cached summary on success.
     */

    public function confirmBooking(Request $request): JsonResponse
    {
        if (! config('ai.features.trip_planner')) {
            return response()->json(['error' => 'Feature disabled'], 503);
        }
    
        $request->validate([
            'check_in'        => ['nullable', 'date'],
            'check_out'       => ['nullable', 'date', 'after:check_in'],
            'gear_item_ids'   => ['nullable', 'array'],
            'gear_item_ids.*' => ['integer', 'min:1'],
        ]);
    
        $user        = Auth::user();
    
        Log::debug('confirmBooking_called', [
            'user_id'     => $user->id,
            'has_summary' => Cache::has('booking_summary:' . $user->id),
        ]);
    
        $summaryData = Cache::get('booking_summary:' . $user->id);
    
        if ($summaryData === null) {
            return response()->json([
                'error' => 'Session expirée. Veuillez demander une nouvelle recommandation.',
            ], 422);
        }
    
        $summary = BookingSummary::fromArray($summaryData);
    
        if ($summary->isExpired()) {
            return response()->json([
                'error' => 'La session de réservation a expiré. Veuillez demander une nouvelle recommandation.',
            ], 422);
        }
    
        if (! $summary->is_bookable) {
            return response()->json([
                'error' => $summary->not_bookable_reason ?? 'Cette réservation n\'est pas disponible.',
            ], 422);
        }
    
        // ── Balance check BEFORE any DB writes ───────────────────────────────────
        $balance          = Balance::forUser($user->id);
        $soldeDisponible  = (float) $balance->solde_disponible;
        $totalRequired    = $summary->total;
    
        if ($soldeDisponible < $totalRequired) {
            $shortfall = round($totalRequired - $soldeDisponible, 2);
            return response()->json([
                'error'            => 'INSUFFICIENT_BALANCE',
                'required_amount'  => $totalRequired,
                'available_amount' => $soldeDisponible,
                'shortfall'        => $shortfall,
                'message'          => "Solde insuffisant. Il vous manque {$shortfall} TND pour confirmer cette réservation.",
            ], 422);
        }
    
        // ── Apply optional overrides ──────────────────────────────────────────────
        $checkIn  = $request->input('check_in',  $summary->check_in);
        $checkOut = $request->input('check_out', $summary->check_out);
        $nights   = (int) Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        $nights   = max(1, $nights);
    
        $gearItems = $summary->gear_items;
        if ($request->filled('gear_item_ids')) {
            $allowedIds = array_map('intval', $request->input('gear_item_ids'));
            $gearItems  = array_values(
                array_filter($gearItems, fn ($g) => in_array((int) $g['id'], $allowedIds, true))
            );
        }
    
        // Fee rate stored as decimal in summary (e.g. 0.03); DB columns expect percentage (3.0)
        $feeRateDb = round($summary->platform_fee_rate * 100, 2);
    
        try {
            Log::debug('transaction_starting', ['booking_type' => $summary->booking_type, 'nbr_place' => $summary->nbr_place]);
            DB::beginTransaction();
    
            $centreReservationId = null;
            $gearReservationIds  = [];
    
            // ── Centre reservation ──────────────────────────────────────────────
            if ($summary->booking_type === 'centre_partner') {
                $centreBase       = $summary->accommodation_total;
                $centreFeeAmt     = round($centreBase * $summary->platform_fee_rate, 2);
                $centreTotalPrice = round($centreBase + $centreFeeAmt, 2);
    
                $centreRes = Reservations_centre::create([
                    'user_id'             => $user->id,
                    'centre_id'           => $summary->centre_user_id,
                    'date_debut'          => $checkIn,
                    'date_fin'            => $checkOut,
                    'nbr_place'           => $summary->nbr_place,
                    'nights'              => $nights,
                    'status'              => 'pending',
                    'total_price'         => $centreTotalPrice,
                    'payment_method'      => 'wallet',
                    'platform_fee_rate'   => $feeRateDb,
                    'platform_fee_amount' => $centreFeeAmt,
                ]);
    
                $centreReservationId = $centreRes->id;
            }
    
            // ── Gear reservations ───────────────────────────────────────────────
            foreach ($gearItems as $gearItem) {
                $materielle = Materielles::findOrFail((int) $gearItem['id']);
    
                $itemBase   = round((float) $gearItem['tarif_nuit'] * $nights, 2);
                $itemFeeAmt = round($itemBase * $summary->platform_fee_rate, 2);
                $itemTotal  = round($itemBase + $itemFeeAmt, 2);
    
                $gearRes = Reservations_materielles::create([
                    'materielle_id'       => $materielle->id,
                    'user_id'             => $user->id,
                    'fournisseur_id'      => $gearItem['fournisseur_id'],
                    'type_reservation'    => 'location',
                    'date_debut'          => $checkIn,
                    'date_fin'            => $checkOut,
                    'quantite'            => 1,
                    'montant_total'       => $itemTotal,
                    'mode_livraison'      => 'pickup',
                    'cin_camper'          => $user->profile?->cin_path ?? null,
                    'status'              => 'pending',
                    'payment_method'      => 'wallet',
                    'platform_fee_amount' => $itemFeeAmt,
                    'platform_fee_rate'   => $feeRateDb,
                ]);
    
                $gearReservationIds[] = $gearRes->id;
            }
    
            // ── Lock funds atomically inside the transaction ────────────────────
            // Uses a raw UPDATE so it participates in the DB transaction and
            // rolls back automatically if anything above throws.
            DB::table('balances')
                ->where('user_id', $user->id)
                // Double-check balance inside the transaction to guard race conditions
                ->where('solde_disponible', '>=', $totalRequired)
                ->update([
                    'solde_disponible'     => DB::raw("solde_disponible - {$totalRequired}"),
                    'solde_en_attente'     => DB::raw("solde_en_attente + {$totalRequired}"),
                    'dernier_mouvement_at' => now(),
                ]);
    
            // Verify the update actually matched a row (race-condition guard)
            $updatedRows = DB::select(
                'SELECT id FROM balances WHERE user_id = ? AND solde_disponible >= 0 LIMIT 1',
                [$user->id]
            );
    
            if (empty($updatedRows) && $totalRequired > 0) {
                DB::rollBack();
                return response()->json([
                    'error'   => 'INSUFFICIENT_BALANCE',
                    'message' => 'Solde insuffisant au moment de la confirmation (condition de concurrence). Veuillez réessayer.',
                ], 422);
            }
    
            DB::commit();
            Log::debug('transaction_committed', [
                'centre_reservation_id' => $centreReservationId,
                'gear_ids'              => $gearReservationIds,
                'locked_amount'         => $totalRequired,
            ]);
    
            $this->behavioralProfileService->invalidate($user->id);
            Cache::forget('booking_summary:' . $user->id);
    
            $primaryReservationId = $centreReservationId ?? ($gearReservationIds[0] ?? null);
    
            $lastRec     = Cache::get('last_recommendation:' . $user->id);
            $recommended = $lastRec['recommended'] ?? $lastRec['recommended_zone'] ?? [];
            $placeNom    = $summary->centre_nom
                        ?? ($recommended['nom']    ?? null)
                        ?? ($recommended['region'] ?? 'votre destination');
    
            $nextStep = in_array($summary->booking_type, ['zone_with_gear', 'zone_no_gear'], true)
                ? 'Votre demande de location de matériel est en attente de confirmation du fournisseur.'
                : 'Le centre dispose de 48h pour valider votre demande.';
    
            $confirmContext = [
                'reservation_id' => $primaryReservationId,
                'place_nom'      => $placeNom,
                'total'          => $summary->total,
                'next_step'      => $nextStep,
            ];
    
            $confirmMessage = $this->tripPlannerService->generateReservationConfirmationMessage(
                (int) ($primaryReservationId ?? 0),
                $confirmContext,
            );
    
            return response()->json([
                'success'              => true,
                'booking_confirmed'    => true,
                'reservation_id'       => $centreReservationId,
                'gear_reservation_ids' => $gearReservationIds,
                'message'              => $confirmMessage,
            ]);
    
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('booking_confirm_failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
    
            return response()->json([
                'error' => 'Une erreur est survenue lors de la création de la réservation. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * GET /api/ai/status/{jobId}
     */
    public function status(string $jobId): JsonResponse
    {
        $payload = Cache::get('ai:job:' . $jobId);

        if ($payload === null) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $httpStatus = match ($payload['status'] ?? '') {
            'processing' => 202,
            'done'       => 200,
            default      => 500,
        };

        return response()->json($payload, $httpStatus);
    }

    /**
     * GET /api/ai/recommendations
     *
     * Returns pre-scored zones and gear for the authenticated camper
     * without calling the LLM — pure recommendation engine output.
     * Used by frontend "Recommended for you" sections.
     * Cached per user for 30 minutes.
     */
    public function recommendations(Request $request): JsonResponse
    {
        if (! config('ai.features.trip_planner')) {
            return response()->json(['error' => 'Feature disabled'], 503);
        }

        $user           = Auth::user();
        $campeurProfile = $user->profile?->profileCampeur;

        if (! $campeurProfile) {
            return response()->json(['error' => 'Campeur profile not found'], 404);
        }

        $cacheKey = 'recommendations:' . $user->id;

        $explainService = app(ExplainabilityService::class);

        $result = Cache::remember($cacheKey, 1800, function () use ($campeurProfile, $explainService) {
            // DB queries run inside the closure so they are skipped on cache hits
            $zones = CampingZone::where('status', 1)
                ->where('is_closed', 0)
                ->select([
                    'id', 'nom', 'region', 'terrain_type', 'difficulty',
                    'is_beginner_friendly', 'rating', 'activities', 'reviews_count',
                ])
                ->limit(50)
                ->get();

            $gear = Materielles::where('status', 'up')
                ->where('quantite_dispo', '>', 0)
                ->with('category:id,nom')
                ->select([
                    'id', 'nom', 'brand', 'trip_type_tags', 'tarif_nuit',
                    'condition', 'is_rentable', 'category_id',
                ])
                ->limit(50)
                ->get();

            $scoredZones = $this->recommender->scoreZones($campeurProfile, $zones)->take(5);
            $scoredGear  = $this->recommender->scoreGear($campeurProfile, $gear)->take(10);

            $zonesWithExplanations = $scoredZones->map(function ($zone) use ($campeurProfile, $explainService) {
                $arr = $zone->toArray();
                $arr['explanation'] = $explainService->explainRecommendation(
                    $zone->score_breakdown ?? [],
                    $zone->nom ?? '',
                    $campeurProfile->skill_level,
                    ($zone->score ?? 0) / 13,
                )->toArray();
                return $arr;
            })->values();

            return [
                'zones' => $zonesWithExplanations,
                'gear'  => $scoredGear->values(),
            ];
        });

        return response()->json([
            'zones'           => $result['zones'],
            'gear'            => $result['gear'],
            'profile_summary' => [
                'skill_level'   => $campeurProfile->skill_level,
                'budget_range'  => $campeurProfile->budget_range,
                'comfort_level' => $campeurProfile->comfort_level,
            ],
        ]);
    }

    /**
     * GET /api/ai/gear/essential/{terrainType}
     * Pure rule lookup — no auth, no DB, no LLM.
     */
    public function essentialGear(Request $request, string $terrainType): JsonResponse
    {
        $validTerrains = ['forest', 'mountain', 'desert', 'coastal', 'plain', 'wetland'];
        if (! in_array($terrainType, $validTerrains, true)) {
            return response()->json(['error' => 'Invalid terrain type'], 422);
        }

        $riskLevel  = $request->query('risk', 'low');
        $categories = $this->gearService->getEssentialItems($terrainType, $riskLevel);

        return response()->json([
            'terrain_type' => $terrainType,
            'risk_level'   => $riskLevel,
            'categories'   => $categories,
        ]);
    }

    /**
     * GET /api/ai/gear/{zoneId}
     * Returns a gear checklist for a specific zone.
     * Auth optional — personalized when authenticated.
     */
    public function gear(Request $request, int $zoneId): JsonResponse
    {
        if (! config('ai.features.gear_assistant')) {
            return response()->json(['error' => 'Feature disabled'], 503);
        }

        $zone = CampingZone::find($zoneId);
        if (! $zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $groupSize = max(1, min((int) $request->query('group_size', 1), 50));

        $forecast = null;
        if (config('ai.features.weather')) {
            $forecast = $this->weatherService->getForecastForZone($zone);
        }

        $profile = $request->user()?->profile?->profileCampeur;
        if (! $profile) {
            $profile = new \App\Models\ProfileCampeur([
                'skill_level'           => 'beginner',
                'comfort_level'         => 'standard',
                'budget_range'          => 'moderate',
                'preferred_trip_styles' => [],
                'preferred_activities'  => [],
                'gear_preferences'      => [],
                'total_trips'           => 0,
            ]);
        }

        try {
            $checklist = $this->gearService->generateChecklist($profile, $zone, $forecast, $groupSize);
            $alert     = $this->gearService->getCriticalMissingAlert($checklist);

            return response()->json([
                'zone_id'        => $zone->id,
                'zone_name'      => $zone->nom,
                'terrain_type'   => $zone->terrain_type,
                'group_size'     => $groupSize,
                'checklist'      => $checklist->toArray(),
                'critical_alert' => $alert,
                'personalized'   => $request->user() !== null,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('gear_checklist_failed', [
                'zone_id' => $zoneId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Gear assistant unavailable'], 503);
        }
    }

    /**
     * GET /api/ai/weather/{zoneId}
     * Returns weather forecast for a specific camping zone.
     * Used by zone detail page to show weather widget.
     */
    public function weather(int $zoneId): JsonResponse
    {
        if (! config('ai.features.weather')) {
            return response()->json(['error' => 'Feature disabled'], 503);
        }

        $zone = CampingZone::find($zoneId);
        if (! $zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        if (! $zone->lat || ! $zone->lng) {
            return response()->json(['error' => 'Zone has no coordinates'], 422);
        }

        try {
            $forecast = $this->weatherService->getForecastForZone($zone);
            if (! $forecast) {
                return response()->json(['error' => 'Weather data unavailable'], 503);
            }

            return response()->json([
                'zone_id'     => $zone->id,
                'zone_name'   => $zone->nom,
                'forecast'    => $forecast->toArray(),
                'risk_level'  => $this->weatherService->getOverallRiskLevel($forecast),
                'should_warn' => $this->weatherService->shouldWarnUser($forecast),
                'summary'     => $this->weatherService->getWeatherSummaryForPrompt($forecast),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 429);
        }
    }
}
