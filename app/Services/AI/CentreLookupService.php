<?php

namespace App\Services\AI;

use App\Models\CampingCentre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Pure DB lookup for camping centres — no LLM, no decisions, never throws.
 *
 * Two kinds of centres live in the same `camping_centres` table:
 *  - Partner centres   → claimed, validated, bookable through the platform.
 *  - External centres   → discovery-only; the camper contacts them directly.
 */
class CentreLookupService
{
    /**
     * Governorate keywords used to derive a display region from a free-text
     * address. Intentionally compact — authoritative destination detection for
     * the *user's request* lives in TripPlannerService; this only labels a
     * centre's own stored address.
     */
    private const REGION_KEYWORDS = [
        'jendouba' => 'Jendouba', 'tabarka' => 'Jendouba', 'ain draham' => 'Jendouba',
        'bizerte' => 'Bizerte', 'tunis' => 'Tunis', 'ariana' => 'Ariana',
        'manouba' => 'Manouba', 'ben arous' => 'Ben Arous', 'nabeul' => 'Nabeul',
        'hammamet' => 'Nabeul', 'zaghouan' => 'Zaghouan', 'sousse' => 'Sousse',
        'monastir' => 'Monastir', 'mahdia' => 'Mahdia', 'sfax' => 'Sfax',
        'kairouan' => 'Kairouan', 'siliana' => 'Siliana', 'béja' => 'Béja',
        'beja' => 'Béja', 'kasserine' => 'Kasserine', 'sidi bouzid' => 'Sidi Bouzid',
        'gafsa' => 'Gafsa', 'tozeur' => 'Tozeur', 'kébili' => 'Kébili',
        'kebili' => 'Kébili', 'gabès' => 'Gabès', 'gabes' => 'Gabès',
        'médenine' => 'Médenine', 'medenine' => 'Médenine', 'djerba' => 'Médenine',
        'tataouine' => 'Tataouine',
    ];

    /**
     * Partner centres: claimed, approved, public, bookable.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function findPartnerCentres(?string $region, int $limit = 5): Collection
    {
        try {
            $query = CampingCentre::query()
                ->whereNotNull('user_id')
                ->whereNotNull('profile_centre_id')
                ->where('status', 1)
                ->where('validation_status', 'approved')
                ->with([
                    'profileCentre',
                    'profileCentre.equipment' => fn ($q) => $q->where('is_available', true),
                    'profileCentre.centerServices' => fn ($q) => $q->where('is_available', true),
                ]);

            if ($region) {
                $query->where('adresse', 'like', '%' . addcslashes($region, '%_') . '%');
            }

            return $query->limit($limit)->get()
                ->map(fn (CampingCentre $c) => $this->formatPartner($c, $region))
                ->values();
        } catch (\Throwable $e) {
            Log::error('centre_lookup_partner_failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * External centres: not (yet) partners — discovery-only.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function findExternalCentres(?string $region, int $limit = 5): Collection
    {
        try {
            $query = CampingCentre::query()
                ->where(function ($w) {
                    $w->whereNull('user_id')
                        ->orWhereNull('profile_centre_id')
                        ->orWhere('validation_status', '!=', 'approved');
                })
                ->with('profileCentre');

            if ($region) {
                $query->where('adresse', 'like', '%' . addcslashes($region, '%_') . '%');
            }

            return $query->limit($limit)->get()
                ->map(fn (CampingCentre $c) => $this->formatExternal($c, $region))
                ->values();
        } catch (\Throwable $e) {
            Log::error('centre_lookup_external_failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    // ── Formatting ──────────────────────────────────────────────────────────────

    private function formatPartner(CampingCentre $centre, ?string $region): array
    {
        $pc        = $centre->profileCentre;
        $equipment = $pc?->equipment
            ? $pc->equipment->where('is_available', true)->pluck('type')->values()->all()
            : [];

        return [
            'type'                    => 'centre_partner',
            'id'                      => $centre->id,
            'nom'                     => $centre->nom,
            'region'                  => $this->deriveRegion($centre->adresse) ?? $region,
            'adresse'                 => $centre->adresse,
            'lat'                     => $centre->lat ?? ($pc?->latitude !== null ? (float) $pc->latitude : null),
            'lng'                     => $centre->lng ?? ($pc?->longitude !== null ? (float) $pc->longitude : null),
            'image'                   => $centre->image,
            'centre_user_id'          => $centre->user_id,
            'capacite'                => $pc?->capacite,
            'price_per_night'         => (float) ($pc?->price_per_night ?? 0),
            'equipment_list'          => $equipment,
            'bookable_service_count'  => $pc?->centerServices?->count() ?? 0,
        ];
    }

    private function formatExternal(CampingCentre $centre, ?string $region): array
    {
        return [
            'type'          => 'centre_external',
            'id'            => $centre->id,
            'nom'           => $centre->nom,
            'region'        => $this->deriveRegion($centre->adresse) ?? $region,
            'adresse'       => $centre->adresse,
            'lat'           => $centre->lat,
            'lng'           => $centre->lng,
            'image'         => $centre->image,
            // camping_centres has no phone column; surface the profile's if one exists.
            'contact_phone' => $centre->profileCentre?->contact_phone,
        ];
    }

    private function deriveRegion(?string $address): ?string
    {
        if ($address === null || $address === '') {
            return null;
        }

        $lower = mb_strtolower($address);
        foreach (self::REGION_KEYWORDS as $keyword => $label) {
            if (str_contains($lower, $keyword)) {
                return $label;
            }
        }

        return null;
    }
}
