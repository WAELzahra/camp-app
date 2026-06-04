<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CampingZone;
use App\Services\AI\ExplainabilityService;
use App\Services\AI\RecommendationService;
use App\Services\AI\SafetyService;
use App\Services\AI\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExplainabilityController extends Controller
{
    public function __construct(
        private readonly ExplainabilityService $explainService,
    ) {}

    public function recommendation(int $zoneId): JsonResponse
    {
        $user    = Auth::user();
        $profile = $user->profile?->profileCampeur;

        if (! $profile) {
            return response()->json(['error' => 'Profile required'], 404);
        }

        $zone = CampingZone::find($zoneId);
        if (! $zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $recommendationService = app(RecommendationService::class);
        $zones                 = CampingZone::where('id', $zoneId)->get();
        $scored                = $recommendationService->scoreZones($profile, $zones);
        $scoreBreakdown        = $scored->first()?->score_breakdown ?? [];
        $normalizedScore       = ($scored->first()?->score ?? 0) / 13;

        $explanation = $this->explainService->explainRecommendation(
            $scoreBreakdown,
            $zone->nom,
            $profile->skill_level,
            (float) $normalizedScore,
        );

        return response()->json(['explanation' => $explanation->toArray()]);
    }

    public function safety(int $zoneId): JsonResponse
    {
        $zone = CampingZone::find($zoneId);
        if (! $zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $profile = Auth::user()->profile?->profileCampeur;
        if (! $profile) {
            return response()->json(['error' => 'Profile required'], 404);
        }

        $safetyService = app(SafetyService::class);
        $forecast      = app(WeatherService::class)->getForecastForZone($zone);
        $assessment    = $safetyService->assessTripSafety($profile, $zone, $forecast, 1);

        $explanation = $this->explainService->explainSafetyAssessment($assessment);

        return response()->json(['explanation' => $explanation->toArray()]);
    }

    public function weather(int $zoneId): JsonResponse
    {
        $zone = CampingZone::find($zoneId);
        if (! $zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $weatherService = app(WeatherService::class);
        $forecast       = $weatherService->getForecastForZone($zone);
        $riskLevel      = $weatherService->getOverallRiskLevel($forecast);

        $explanation = $this->explainService->explainWeatherWarning($forecast, $riskLevel);

        return response()->json(['explanation' => $explanation->toArray()]);
    }

    public function onDemand(Request $request): JsonResponse
    {
        $request->validate([
            'context' => 'required|string|max:500',
            'source'  => 'required|string|in:recommendation,weather,safety,gear,group,pricing',
            'data'    => 'nullable|array',
        ]);

        $explanation = $this->explainService->explainOnDemand(
            $request->context,
            $request->source,
            $request->data ?? [],
        );

        return response()->json(['explanation' => $explanation->toArray()]);
    }
}
