<?php

namespace App\Services;

use App\Models\PlatformSetting;

/**
 * Encapsulates cash-at-centre payment business logic — currently just the
 * admin on/off switch and the eligibility window. Kept separate from
 * ManualPaymentService since cash and bank transfer are two genuinely
 * different payment paths (provider collects in person vs. camper wires
 * money to the platform's account), not variants of the same "manual" flow.
 */
class CashPaymentService
{
    /**
     * Cash is only offered when the reservation's target date is within this
     * many hours — it skips the usual payment-before-review flow (host
     * accepts first, cash changes hands on arrival), so a booking made far
     * out with no payment on file would be cancellable with nothing to
     * enforce. See StoreCentreReservationRequest::withValidator() and its
     * events/materielles counterparts for where this is actually enforced.
     */
    public const ELIGIBILITY_WINDOW_HOURS = 48;

    public static function isEnabled(): bool
    {
        return (bool) PlatformSetting::get('cash_payment_enabled', false);
    }

    /**
     * Whether a target date (date-only — e.g. "2026-07-29", no time component)
     * falls inside the cash eligibility window.
     *
     * The window must include TODAY — same-day cash booking is the whole point
     * of this payment method. A naive `Carbon::parse($date)->isBefore(now())`
     * check breaks this: parsing a bare date defaults to midnight, and midnight
     * of *today* is already in the past by any later hour, so "today" would be
     * silently rejected. Comparing day-granular boundaries instead — the target
     * day hasn't fully elapsed yet (endOfDay >= now), and the target day starts
     * before the 48h-from-now cutoff — keeps today eligible while still capping
     * how far out a cash booking can be made.
     */
    public static function isDateEligible(string|\DateTimeInterface $date): bool
    {
        $target = \Carbon\Carbon::parse($date);
        $cutoff = now()->addHours(self::ELIGIBILITY_WINDOW_HOURS);

        return !$target->copy()->endOfDay()->isBefore(now())
            && !$target->copy()->startOfDay()->isAfter($cutoff);
    }
}
