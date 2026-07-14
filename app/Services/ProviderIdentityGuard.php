<?php

namespace App\Services;

use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;

/**
 * Decides whether the current user is allowed to see a provider's real
 * identity (name/avatar/contact), or must get the masked placeholder.
 *
 * Rule: the requesting user must have at least a deposit paid on a
 * reservation with that exact provider. Anonymous visitors always get
 * masked data (methods return false for a null user id).
 */
class ProviderIdentityGuard
{
    public static function hasPaidCentre(?int $userId, ?int $centreOwnerUserId): bool
    {
        if (!$userId || !$centreOwnerUserId) {
            return false;
        }

        return Reservations_centre::where('user_id', $userId)
            ->where('centre_id', $centreOwnerUserId)
            ->where(function ($q) {
                $q->whereIn('status', [Reservations_centre::STATUS_APPROVED])
                    ->orWhere(function ($q2) {
                        $q2->where('payment_method', 'wallet')
                            ->orWhereNotNull('payment_confirmed_at');
                    });
            })
            ->exists();
    }

    public static function hasPaidEvent(?int $userId, ?int $eventId): bool
    {
        if (!$userId || !$eventId) {
            return false;
        }

        return Reservations_events::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->whereIn('status', ['confirmée', 'confirmée_solde_en_attente', 'entièrement_payée'])
            ->exists();
    }

    public static function hasPaidEventWithGroup(?int $userId, ?int $groupId): bool
    {
        if (!$userId || !$groupId) {
            return false;
        }

        return Reservations_events::whereHas('event', fn ($q) => $q->where('group_id', $groupId))
            ->where('user_id', $userId)
            ->whereIn('status', ['confirmée', 'confirmée_solde_en_attente', 'entièrement_payée'])
            ->exists();
    }

    public static function hasPaidFournisseur(?int $userId, ?int $fournisseurId): bool
    {
        if (!$userId || !$fournisseurId) {
            return false;
        }

        return Reservations_materielles::where('user_id', $userId)
            ->where('fournisseur_id', $fournisseurId)
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'retrieved', 'returned'])
                    ->orWhereNotNull('payment_confirmed_at');
            })
            ->exists();
    }

    private const GOVERNORATES = [
        'Ariana', 'Béja', 'Ben Arous', 'Bizerte', 'Gabès', 'Gafsa', 'Jendouba',
        'Kairouan', 'Kasserine', 'Kébili', 'La Manouba', 'Le Kef', 'Mahdia',
        'Médenine', 'Monastir', 'Nabeul', 'Sfax', 'Sidi Bouzid', 'Siliana',
        'Sousse', 'Tataouine', 'Tozeur', 'Tunis', 'Zaghouan',
    ];

    /**
     * Extracts a governorate name from a free-text address string, for use as
     * a safe "city" fallback when no structured city field is available.
     * Never trust arbitrary DB "type"-like columns for this — some centres in
     * this dataset store an acronym derived from their own real name there
     * (e.g. "CCV" for "CCV Zoueraa"), which would leak identity right back.
     */
    public static function extractRegion(?string $address): ?string
    {
        if (!$address) {
            return null;
        }
        foreach (self::GOVERNORATES as $gov) {
            if (mb_stripos($address, $gov) !== false) {
                return $gov;
            }
        }

        return null;
    }

    /**
     * Masked-card display label for a centre — real, distinguishing, public
     * data (category + city) instead of a single fixed placeholder repeated
     * on every card, which makes a listing unusable for browsing/comparison.
     */
    public static function centreLabel(?string $category, ?string $city): string
    {
        $cat = trim((string) $category) !== '' ? trim($category) : 'Camping';
        $city = trim((string) $city);

        return $city !== '' ? "{$cat} · {$city}" : $cat;
    }

    /**
     * Masked-card display label for a fournisseur/boutique — category-based
     * instead of a single fixed "Fournisseur vérifié" string on every card.
     */
    public static function fournisseurLabel(?string $productCategory, ?string $city): string
    {
        $cat = trim((string) $productCategory) !== '' ? trim($productCategory) : 'Équipement';
        $city = trim((string) $city);

        return $city !== '' ? "{$cat} · {$city}" : $cat;
    }

    /**
     * Masked-card display label for an event organiser — city-based instead
     * of a single fixed "Organisateur vérifié" string on every profile.
     */
    public static function organiserLabel(?string $city): string
    {
        $city = trim((string) $city);

        return $city !== '' ? "Organisateur · {$city}" : 'Organisateur vérifié';
    }
}
