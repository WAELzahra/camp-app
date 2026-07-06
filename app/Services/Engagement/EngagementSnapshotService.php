<?php

namespace App\Services\Engagement;

use App\Models\Events;
use App\Models\Profile;
use App\Models\ProfileGroupe;

/**
 * Task A-02 §2E — snapshot the provider's engagement mode + applicable rate
 * onto every reservation at creation time. The snapshot is immutable: admin
 * rate changes never apply retroactively.
 */
class EngagementSnapshotService
{
    /** Fill snapshot attributes on a reservation model before it is saved. */
    public static function apply(object $reservation, ?Profile $providerProfile): void
    {
        if (!$providerProfile || !empty($reservation->engagement_mode_snapshot)) {
            return; // no provider resolved, or snapshot already taken — never overwrite
        }

        $mode = $providerProfile->engagement_mode ?? 'commission';
        $reservation->engagement_mode_snapshot = $mode;
        $reservation->engagement_rate_snapshot = $mode === 'agency'
            ? $providerProfile->agency_margin
            : $providerProfile->commission_rate;
    }

    public static function profileForCentreReservation(object $reservation): ?Profile
    {
        // reservations_centres.centre_id is the centre OWNER's user id
        // (the same key Balance::forUser() uses in the payment flow).
        return Profile::where('user_id', $reservation->centre_id)->first();
    }

    public static function profileForEventReservation(object $reservation): ?Profile
    {
        $groupId = $reservation->group_id ?: Events::find($reservation->event_id)?->group_id;

        return $groupId ? ProfileGroupe::find($groupId)?->profile : null;
    }

    public static function profileForMaterielleReservation(object $reservation): ?Profile
    {
        // reservations_materielles.fournisseur_id → users.id → profiles
        return Profile::where('user_id', $reservation->fournisseur_id)->first();
    }
}
