<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CampingZone;
use App\Models\ProfileCampeur;
use App\Services\AI\SafetyService;
use App\Services\AI\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SafetyController extends Controller
{
    public function __construct(
        private readonly SafetyService  $safetyService,
        private readonly WeatherService $weatherService,
    ) {}

    /**
     * POST /api/ai/safety/assess
     * Auth required. Returns full safety assessment for a zone + user profile.
     */
    public function assess(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id'    => ['required', 'integer', 'exists:camping_zones,id'],
            'group_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $profile = Auth::user()->profile?->profileCampeur;
        if (! $profile) {
            return response()->json(['error' => 'Campeur profile required'], 404);
        }

        $zone = CampingZone::find($request->zone_id);

        $forecast = config('ai.features.weather')
            ? $this->weatherService->getForecastForZone($zone)
            : null;

        try {
            $assessment = $this->safetyService->assessTripSafety(
                $profile,
                $zone,
                $forecast,
                (int) ($request->group_size ?? 1),
            );

            return response()->json([
                'zone_id'    => $zone->id,
                'zone_name'  => $zone->nom,
                'assessment' => $assessment->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Safety assessment unavailable'], 500);
        }
    }

    /**
     * GET /api/ai/safety/zone/{zoneId}
     * No auth — public quick risk label for listing cards.
     */
    public function quickRisk(int $zoneId): JsonResponse
    {
        $zone = CampingZone::find($zoneId);
        if (! $zone) {
            return response()->json(['error' => 'Zone not found'], 404);
        }

        $payload = Cache::remember('safety:quick:' . $zoneId, 3600, function () use ($zone) {
            return [
                'zone_id'      => $zone->id,
                'risk_label'   => $this->safetyService->getQuickRiskLabel($zone),
                'danger_level' => $zone->danger_level,
                'difficulty'   => $zone->difficulty,
            ];
        });

        return response()->json($payload);
    }

    /**
     * POST /api/ai/safety/moderate
     * Auth required. Fournisseur or admin only.
     */
    public function moderate(Request $request): JsonResponse
    {
        $userRole = Auth::user()->profile?->role?->name ?? '';
        if (! in_array($userRole, ['fournisseur', 'admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title'        => ['required', 'string', 'min:3', 'max:255'],
            'description'  => ['required', 'string', 'min:10', 'max:5000'],
            'category'     => ['required', 'string'],
            'price'        => ['required', 'numeric', 'min:0'],
            'content_type' => ['nullable', 'string', 'in:listing,zone_description,event'],
        ]);

        $result = $this->safetyService->moderateContent(
            $request->title,
            $request->description,
            $request->category,
            (float) $request->price,
            $request->content_type ?? 'listing',
        );

        $httpStatus = $result->status === 'rejected' ? 422 : 200;

        return response()->json(['moderation' => $result->toArray()], $httpStatus);
    }

    /**
     * GET /api/ai/safety/moderation-stats
     * Admin only.
     */
    public function moderationStats(): JsonResponse
    {
        $role = Auth::user()->profile?->role?->name ?? '';
        if ($role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($this->safetyService->getModerationStats());
    }
}
