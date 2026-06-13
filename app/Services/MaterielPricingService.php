<?php

namespace App\Services;

use App\Models\Materielles;
use Carbon\Carbon;

/**
 * Computes rental pricing for materielles with dynamic rates:
 *  - rental_unit 'night': per-night pricing, each night priced individually
 *    using seasonal overrides (weekday/weekend, Sat+Sun = weekend) when the
 *    night falls inside a recurring season; base tarif_nuit otherwise.
 *  - rental_unit 'hour': flat hourly rate × hours, with the seasonal override
 *    of the rental day applied when one covers it.
 *
 * This is the single source of truth — reservations recompute totals here,
 * never trusting client-submitted amounts.
 */
class MaterielPricingService
{
    /** Saturday + Sunday count as weekend nights. */
    private static function isWeekend(Carbon $date): bool
    {
        return $date->isSaturday() || $date->isSunday();
    }

    /** Resolve the per-unit price for a single date. */
    private static function priceForDate(Materielles $m, Carbon $date, float $baseRate): array
    {
        foreach ($m->seasonalRates as $rate) {
            if ($rate->coversDate($date)) {
                $weekend = self::isWeekend($date);
                $price   = $weekend
                    ? ($rate->price_weekend ?? $rate->price_weekday)
                    : $rate->price_weekday;
                return [
                    'price'   => (float) $price,
                    'season'  => $rate->name,
                    'weekend' => $weekend,
                ];
            }
        }
        return [
            'price'   => $baseRate,
            'season'  => null,
            'weekend' => self::isWeekend($date),
        ];
    }

    /**
     * Quote a rental.
     *
     * @return array{unit:string, quantity:int, units:int, breakdown:array, subtotal:float}
     * @throws \InvalidArgumentException on missing/invalid inputs
     */
    public static function quoteRental(
        Materielles $m,
        ?string $dateDebut,
        ?string $dateFin,
        int $quantite,
        ?int $hours = null
    ): array {
        $m->loadMissing('seasonalRates');
        $quantite = max(1, $quantite);

        if ($m->rental_unit === 'hour') {
            $hours = max(1, (int) $hours);
            $day   = Carbon::parse($dateDebut ?: now());
            $base  = (float) ($m->tarif_heure ?? $m->tarif_nuit ?? 0);
            $info  = self::priceForDate($m, $day, $base);

            $subtotal = round($info['price'] * $hours * $quantite, 2);
            return [
                'unit'      => 'hour',
                'quantity'  => $quantite,
                'units'     => $hours,
                'breakdown' => [[
                    'date'    => $day->toDateString(),
                    'units'   => $hours,
                    'price'   => $info['price'],
                    'season'  => $info['season'],
                    'weekend' => $info['weekend'],
                ]],
                'subtotal'  => $subtotal,
            ];
        }

        // ── Per-night rental ──────────────────────────────────────────────
        if (!$dateDebut || !$dateFin) {
            throw new \InvalidArgumentException('date_debut and date_fin are required for rentals.');
        }
        $start = Carbon::parse($dateDebut)->startOfDay();
        $end   = Carbon::parse($dateFin)->startOfDay();
        $nights = max(1, $start->diffInDays($end));
        $base   = (float) ($m->tarif_nuit ?? 0);

        $breakdown = [];
        $subtotal  = 0.0;
        $cursor    = $start->copy();
        for ($i = 0; $i < $nights; $i++) {
            $info = self::priceForDate($m, $cursor, $base);
            $breakdown[] = [
                'date'    => $cursor->toDateString(),
                'units'   => 1,
                'price'   => $info['price'],
                'season'  => $info['season'],
                'weekend' => $info['weekend'],
            ];
            $subtotal += $info['price'];
            $cursor->addDay();
        }

        return [
            'unit'      => 'night',
            'quantity'  => $quantite,
            'units'     => $nights,
            'breakdown' => $breakdown,
            'subtotal'  => round($subtotal * $quantite, 2),
        ];
    }
}
