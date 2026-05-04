<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\PlatformCancellationFee;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Services\CancellationPolicyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CancellationPreviewController extends Controller
{
    /**
     * GET /api/reservations/cancellation-preview
     * Returns the full cancellation fee breakdown, including platform cancellation fee.
     *
     * Query params: type (centre|event|materiel), reservation_id
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'type'           => 'required|in:centre,event,materiel',
            'reservation_id' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $type = $validated['type'];
        $rid  = (int) $validated['reservation_id'];

        [$startDate, $totalPaid, $centreId, $createdAt] = match ($type) {
            'centre'   => $this->resolveCentre($rid, $user->id),
            'event'    => $this->resolveEvent($rid, $user->id),
            'materiel' => $this->resolveMateriel($rid, $user->id),
        };

        // Platform cancellation fee for campers
        $platformCancFeeRecord  = PlatformCancellationFee::where('actor_type', 'camper')->first();
        $platformCancFeePct     = ($platformCancFeeRecord?->is_active) ? (float) $platformCancFeeRecord->fee_percentage : 0.0;
        $platformCancFeeAmount  = round($totalPaid * $platformCancFeePct / 100, 2);

        $result = CancellationPolicyService::preview($type, $startDate, $totalPaid, $centreId, $createdAt);

        $policyRefund = $result ? (float) $result['refund_amount'] : $totalPaid;
        $actualRefund = max(0, round($policyRefund - $platformCancFeeAmount, 2));

        $base = [
            'platform_cancellation_fee_percentage' => $platformCancFeePct,
            'platform_cancellation_fee_amount'     => $platformCancFeeAmount,
            'actual_refund'                        => $actualRefund,
        ];

        if (!$result) {
            return response()->json(array_merge([
                'has_policy'           => false,
                'fee_percentage'       => 0,
                'fee_amount'           => 0,
                'refund_amount'        => $totalPaid,
                'tier_label'           => 'Full refund',
                'total_paid'           => $totalPaid,
                'grace_period_applied' => false,
                'grace_period_hours'   => null,
            ], $base));
        }

        return response()->json(array_merge(
            ['has_policy' => true, 'total_paid' => $totalPaid],
            $result,
            $base
        ));
    }

    /**
     * GET /api/cancellation-policy/info
     * Returns the applicable policy tiers for a type + optional centre, before a reservation is created.
     */
    public function policyInfo(Request $request)
    {
        $validated = $request->validate([
            'type'      => 'required|in:centre,event,materiel',
            'centre_id' => 'nullable|integer|min:1',
        ]);

        $policy = CancellationPolicyService::getPolicy(
            $validated['type'],
            isset($validated['centre_id']) ? (int) $validated['centre_id'] : null
        );

        $platformFee = PlatformCancellationFee::where('actor_type', 'camper')->first();

        if (!$policy) {
            return response()->json([
                'has_policy'                           => false,
                'tiers'                                => [],
                'platform_cancellation_fee_percentage' => ($platformFee?->is_active) ? (float) $platformFee->fee_percentage : 0.0,
            ]);
        }

        return response()->json([
            'has_policy'                           => true,
            'policy_name'                          => $policy->name,
            'grace_period_hours'                   => $policy->grace_period_hours,
            'platform_cancellation_fee_percentage' => ($platformFee?->is_active) ? (float) $platformFee->fee_percentage : 0.0,
            'tiers'                                => $policy->tiers->map(fn ($t) => [
                'hours_before'   => $t->hours_before,
                'fee_percentage' => (float) $t->fee_percentage,
                'label'          => $t->label,
            ])->values(),
        ]);
    }

    private function resolveCentre(int $id, int $userId): array
    {
        $r = Reservations_centre::where('id', $id)->where('user_id', $userId)->firstOrFail();
        return [
            Carbon::parse($r->date_debut),
            (float) $r->total_price, // total_price already includes platform fee
            (int) $r->centre_id,
            Carbon::parse($r->created_at),
        ];
    }

    private function resolveEvent(int $id, int $userId): array
    {
        $r     = Reservations_events::where('id', $id)->where('user_id', $userId)->firstOrFail();
        $event = Events::findOrFail($r->event_id);
        // gross = base price + stored platform fee (consistent with centre & equipment)
        $gross = round($r->nbr_place * $event->price + (float) ($r->platform_fee_amount ?? 0), 2);
        return [
            Carbon::parse($event->start_date),
            $gross,
            null,
            Carbon::parse($r->created_at),
        ];
    }

    private function resolveMateriel(int $id, int $userId): array
    {
        $r = Reservations_materielles::where('id', $id)->where('user_id', $userId)->firstOrFail();
        $startDate = $r->date_debut ? Carbon::parse($r->date_debut) : now();
        return [
            $startDate,
            (float) $r->montant_total, // montant_total already includes platform fee
            null,
            Carbon::parse($r->created_at),
        ];
    }
}
