<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\GroupMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GroupMatchingController extends Controller
{
    public function __construct(
        private readonly GroupMatchingService $groupService,
    ) {}

    public function matches(Request $request): JsonResponse
    {
        $profile = Auth::user()->profile?->profileCampeur ?? null;
        if (! $profile) {
            return response()->json(['error' => 'Campeur profile required'], 404);
        }

        $limit = min((int) $request->query('limit', 5), 10);

        try {
            $matches = $this->groupService->findMatchingGroups($profile, $limit);
            return response()->json([
                'matches'   => array_map(fn ($m) => $m->toArray(), $matches),
                'total'     => count($matches),
                'algorithm' => 'kmeans-cosine-similarity',
            ]);
        } catch (\Exception $e) {
            Log::error('group_matching_failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Group matching unavailable'], 503);
        }
    }

    public function clusterStats(): JsonResponse
    {
        $role = Auth::user()->role?->name ?? '';
        if ($role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = $this->groupService->getClusterStats();
        return response()->json($stats);
    }

    public function recluster(): JsonResponse
    {
        $role = Auth::user()->role?->name ?? '';
        if ($role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Cache::forget('group_matching:kmeans_assignments');
        Cache::forget('group_matching:dbscan_assignments');
        Cache::forget('group_matching:vectors');

        $result = $this->groupService->clusterAllProfiles();
        return response()->json([
            'message'        => 'Reclustering complete',
            'total_profiles' => $result['total_profiles'],
            'iterations'     => $result['kmeans']['iterations'],
            'converged'      => $result['kmeans']['converged'],
        ]);
    }
}
