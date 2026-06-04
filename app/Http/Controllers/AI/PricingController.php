<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Materielles;
use App\Services\AI\DynamicPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PricingController extends Controller
{
    private const VALID_TYPES = ['zone', 'materielle'];

    public function __construct(
        private readonly DynamicPricingService $pricingService,
    ) {}

    public function suggest(Request $request, string $entityType, int $entityId): JsonResponse
    {
        if (! in_array($entityType, self::VALID_TYPES, true)) {
            return response()->json(['error' => 'Invalid entity type. Use: zone or materielle'], 422);
        }

        if (! config('ai.features.pricing', true)) {
            return response()->json(['error' => 'Pricing service is disabled'], 503);
        }

        $role = Auth::user()->role?->name ?? '';

        if (! in_array($role, ['fournisseur', 'admin'], true)) {
            return response()->json(['error' => 'Fournisseur or admin role required'], 403);
        }

        // Ownership check — fournisseurs can only analyze their own materielles
        if ($role === 'fournisseur' && $entityType === 'materielle') {
            $item = Materielles::find($entityId);
            if (! $item) {
                return response()->json(['error' => 'Materielle not found'], 404);
            }
            if ((int) $item->fournisseur_id !== (int) Auth::id()) {
                return response()->json(['error' => 'You can only request pricing suggestions for your own listings'], 403);
            }
        }

        try {
            $suggestion = $this->pricingService->suggestPrice($entityId, $entityType);
            return response()->json(['suggestion' => $suggestion->toArray()]);
        } catch (\Exception $e) {
            Log::error('pricing_controller_failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Pricing service unavailable'], 503);
        }
    }

    public function market(string $entityType): JsonResponse
    {
        if (! in_array($entityType, self::VALID_TYPES, true)) {
            return response()->json(['error' => 'Invalid entity type. Use: zone or materielle'], 422);
        }

        $role = Auth::user()->role?->name ?? '';
        if (! in_array($role, ['fournisseur', 'admin'], true)) {
            return response()->json(['error' => 'Fournisseur or admin role required'], 403);
        }

        try {
            $overview = $this->pricingService->getMarketOverview($entityType);
            return response()->json($overview);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Market overview unavailable'], 503);
        }
    }

    public function trendingTags(): JsonResponse
    {
        $role = Auth::user()->role?->name ?? '';
        if (! in_array($role, ['fournisseur', 'admin'], true)) {
            return response()->json(['error' => 'Fournisseur or admin role required'], 403);
        }

        return response()->json([
            'tags' => Cache::get('pricing:trending_tags', []),
        ]);
    }
}
