<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCancellationPolicyRequest;
use App\Http\Requests\Admin\StoreCancellationTierRequest;
use App\Http\Requests\Admin\UpdateCancellationPolicyRequest;
use App\Http\Requests\Admin\UpdateCancellationTierRequest;
use App\Http\Requests\Admin\UpdatePlatformFeeRequest;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyTier;
use Illuminate\Support\Facades\DB;

class CancellationPolicyController extends Controller
{
    // GET /admin/cancellation-policies
    public function index()
    {
        $policies = CancellationPolicy::with([
            'tiers',
            'centre:id,first_name,last_name,email',
        ])->orderBy('type')->orderBy('name')->get();

        return response()->json(['policies' => $policies]);
    }

    // POST /admin/cancellation-policies
    public function store(StoreCancellationPolicyRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $policy = CancellationPolicy::create([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'centre_id' => $validated['centre_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'grace_period_hours' => $validated['grace_period_hours'] ?? null,
            ]);

            foreach ($validated['tiers'] ?? [] as $tier) {
                $policy->tiers()->create($tier);
            }

            DB::commit();

            return response()->json(['policy' => $policy->load('tiers')], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    // GET /admin/cancellation-policies/{id}
    public function show(int $id)
    {
        $policy = CancellationPolicy::with([
            'tiers',
            'centre:id,first_name,last_name,email',
        ])->findOrFail($id);

        return response()->json(['policy' => $policy]);
    }

    // PUT /admin/cancellation-policies/{id}
    public function update(UpdateCancellationPolicyRequest $request, int $id)
    {
        $policy = CancellationPolicy::findOrFail($id);
        $validated = $request->validated();
        $policy->update($validated);

        return response()->json(['policy' => $policy->load('tiers')]);
    }

    // DELETE /admin/cancellation-policies/{id}
    public function destroy(int $id)
    {
        CancellationPolicy::findOrFail($id)->delete();

        return response()->json(['message' => 'Policy deleted.']);
    }

    // POST /admin/cancellation-policies/{id}/tiers
    public function storeTier(StoreCancellationTierRequest $request, int $id)
    {
        $policy = CancellationPolicy::findOrFail($id);
        $validated = $request->validated();
        $tier = $policy->tiers()->create($validated);

        return response()->json(['tier' => $tier], 201);
    }

    // PUT /admin/cancellation-policies/{id}/tiers/{tierId}
    public function updateTier(UpdateCancellationTierRequest $request, int $id, int $tierId)
    {
        $tier = CancellationPolicyTier::where('policy_id', $id)->findOrFail($tierId);
        $validated = $request->validated();
        $tier->update($validated);

        return response()->json(['tier' => $tier]);
    }

    // DELETE /admin/cancellation-policies/{id}/tiers/{tierId}
    public function destroyTier(int $id, int $tierId)
    {
        CancellationPolicyTier::where('policy_id', $id)->findOrFail($tierId)->delete();

        return response()->json(['message' => 'Tier deleted.']);
    }

    /* ── Platform cancellation fees ─────────────────────────────────────────── */

    // GET /admin/platform-cancellation-fees
    public function platformFees()
    {
        $fees = \App\Models\PlatformCancellationFee::orderBy('actor_type')->get();

        return response()->json(['fees' => $fees]);
    }

    // PUT /admin/platform-cancellation-fees/{actorType}
    public function updatePlatformFee(UpdatePlatformFeeRequest $request, string $actorType)
    {
        if (!in_array($actorType, ['camper', 'centre', 'group', 'supplier'])) {
            return response()->json(['message' => 'Invalid actor type.'], 422);
        }

        $validated = $request->validated();

        $fee = \App\Models\PlatformCancellationFee::firstOrCreate(
            ['actor_type' => $actorType],
            ['fee_percentage' => 0, 'is_active' => false]
        );
        $fee->update($validated);

        return response()->json(['fee' => $fee]);
    }
}
