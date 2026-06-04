<?php

namespace App\Services\AI\Booking;

use Carbon\Carbon;

/**
 * Readonly DTO representing a prepared (not yet confirmed) booking.
 *
 * Serialised into cache as an array (toArray / fromArray) to avoid
 * PHP serialisation issues with readonly objects across requests.
 *
 * TTL = 15 minutes (matches expires_at). After expiry the user must
 * call prepare-booking again — no reservation can be created from a
 * stale summary.
 */
final class BookingSummary
{
    public function __construct(
        // Bookability
        public readonly bool    $is_bookable,
        public readonly string  $booking_type,      // zone_with_gear | zone_no_gear | centre_partner | not_bookable
        public readonly ?string $not_bookable_reason,

        // Accommodation
        public readonly ?int    $centre_id,          // camping_centres.id (null for zone)
        public readonly ?int    $centre_user_id,     // users.id of centre owner (reservations_centres.centre_id FK)
        public readonly ?string $centre_nom,
        public readonly ?string $check_in,           // Y-m-d
        public readonly ?string $check_out,          // Y-m-d
        public readonly bool    $dates_are_proposed,
        public readonly ?int    $nbr_place,          // group_size from conversation state
        public readonly float   $accommodation_total,

        // Gear
        public readonly array   $gear_items,         // available items only, each has fournisseur_id + item_total
        public readonly array   $unavailable_gear,   // items that are no longer available in stock
        public readonly float   $gear_total,

        // Totals
        public readonly float   $subtotal,           // accommodation_total + gear_total
        public readonly float   $platform_fee,       // subtotal × platform_fee_rate
        public readonly float   $platform_fee_rate,  // decimal (e.g. 0.03 for 3%)
        public readonly float   $total,              // subtotal + platform_fee
        public readonly string  $currency,           // always "TND"

        // Policy
        public readonly ?string $cancellation_note,

        // Meta
        public readonly string  $prepared_at,        // ISO8601
        public readonly string  $expires_at,         // ISO8601 — prepared_at + 15 minutes
    ) {}

    public function toArray(): array
    {
        return [
            'is_bookable'          => $this->is_bookable,
            'booking_type'         => $this->booking_type,
            'not_bookable_reason'  => $this->not_bookable_reason,
            'centre_id'            => $this->centre_id,
            'centre_user_id'       => $this->centre_user_id,
            'centre_nom'           => $this->centre_nom,
            'check_in'             => $this->check_in,
            'check_out'            => $this->check_out,
            'dates_are_proposed'   => $this->dates_are_proposed,
            'nbr_place'            => $this->nbr_place,
            'accommodation_total'  => $this->accommodation_total,
            'gear_items'           => $this->gear_items,
            'unavailable_gear'     => $this->unavailable_gear,
            'gear_total'           => $this->gear_total,
            'subtotal'             => $this->subtotal,
            'platform_fee'         => $this->platform_fee,
            'platform_fee_rate'    => $this->platform_fee_rate,
            'total'                => $this->total,
            'currency'             => $this->currency,
            'cancellation_note'    => $this->cancellation_note,
            'prepared_at'          => $this->prepared_at,
            'expires_at'           => $this->expires_at,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            is_bookable:         (bool)   ($data['is_bookable'] ?? false),
            booking_type:        (string) ($data['booking_type'] ?? 'not_bookable'),
            not_bookable_reason: $data['not_bookable_reason'] ?? null,
            centre_id:           isset($data['centre_id'])       ? (int)  $data['centre_id']       : null,
            centre_user_id:      isset($data['centre_user_id'])  ? (int)  $data['centre_user_id']  : null,
            centre_nom:          $data['centre_nom'] ?? null,
            check_in:            $data['check_in']  ?? null,
            check_out:           $data['check_out'] ?? null,
            dates_are_proposed:  (bool)   ($data['dates_are_proposed'] ?? true),
            nbr_place:           isset($data['nbr_place'])       ? (int)  $data['nbr_place']       : null,
            accommodation_total: (float)  ($data['accommodation_total'] ?? 0.0),
            gear_items:          (array)  ($data['gear_items']      ?? []),
            unavailable_gear:    (array)  ($data['unavailable_gear'] ?? []),
            gear_total:          (float)  ($data['gear_total']    ?? 0.0),
            subtotal:            (float)  ($data['subtotal']      ?? 0.0),
            platform_fee:        (float)  ($data['platform_fee']  ?? 0.0),
            platform_fee_rate:   (float)  ($data['platform_fee_rate'] ?? 0.0),
            total:               (float)  ($data['total']         ?? 0.0),
            currency:            (string) ($data['currency']      ?? 'TND'),
            cancellation_note:   $data['cancellation_note'] ?? null,
            prepared_at:         (string) ($data['prepared_at'] ?? Carbon::now()->toIso8601String()),
            expires_at:          (string) ($data['expires_at']  ?? Carbon::now()->addMinutes(15)->toIso8601String()),
        );
    }

    /** True when the 15-minute preparation window has passed. */
    public function isExpired(): bool
    {
        return Carbon::parse($this->expires_at)->isPast();
    }
}
