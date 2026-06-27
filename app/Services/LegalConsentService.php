<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserContractAcceptance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * All business logic for legal-document consent tracking.
 *
 * Keeps controllers thin: controllers validate input and delegate here.
 * Follows the static-service pattern already used in this codebase.
 */
class LegalConsentService
{
    // ── Cache helpers ─────────────────────────────────────────────────────────

    /**
     * Global version counter stored in cache.
     * Bumping it orphans every per-user cache key, so all users re-check on
     * their next request — no need for a pattern-delete on publish.
     */
    private static function globalCacheVersion(): int
    {
        return (int) Cache::get('legal_docs:cache_version', 1);
    }

    private static function userCacheKey(User $user): string
    {
        return 'legal_status:user:' . $user->id . ':v' . self::globalCacheVersion();
    }

    /** Forget a single user's cached status (called after they accept). */
    private static function forgetUserCache(User $user): void
    {
        Cache::forget(self::userCacheKey($user));
    }

    /**
     * Bump the global version so every existing per-user cache key becomes
     * unreachable. Old entries expire naturally via their TTL.
     * Called when a new document version is published.
     */
    public static function invalidateAllStatusCaches(): void
    {
        Cache::increment('legal_docs:cache_version');
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    /** All currently active legal documents (one per type). */
    public static function getActiveDocuments(): Collection
    {
        return LegalDocument::active()->orderBy('type')->get();
    }

    /**
     * Active documents the given user has NOT yet accepted.
     * Returns an empty collection when the user is fully compliant.
     */
    public static function getPendingForUser(User $user): Collection
    {
        $acceptedIds = UserContractAcceptance::where('user_id', $user->id)
            ->pluck('legal_document_id');

        return LegalDocument::active()
            ->whereNotIn('id', $acceptedIds)
            ->orderBy('type')
            ->get();
    }

    /** True only when the user has accepted every active document. */
    public static function hasAcceptedAll(User $user): bool
    {
        return static::getPendingForUser($user)->isEmpty();
    }

    /** Acceptance history for the user, newest first. */
    public static function getHistoryForUser(User $user): Collection
    {
        return UserContractAcceptance::with('legalDocument')
            ->where('user_id', $user->id)
            ->orderByDesc('accepted_at')
            ->get();
    }

    /**
     * Cached legal status for the user.
     *
     * Result is stored in Redis (or file cache in local dev) for 1 hour.
     * The cache key includes the global version counter, so publishing a new
     * document version automatically orphans all cached entries.
     *
     * @return array{ needs_acceptance: bool, pending_document_ids: int[] }
     */
    public static function getStatusForUser(User $user): array
    {
        return Cache::remember(self::userCacheKey($user), 3600, function () use ($user) {
            $pending = static::getPendingForUser($user);

            return [
                'needs_acceptance'    => $pending->isNotEmpty(),
                'pending_document_ids'=> $pending->pluck('id')->toArray(),
            ];
        });
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    /**
     * Record acceptance for the given document IDs.
     *
     * Idempotent: already-accepted documents are silently skipped so a double
     * submit or replay never throws a unique-constraint violation.
     *
     * @param  int[]   $documentIds
     * @param  string  $method  'registration' | 'modal' | 'api'
     */
    public static function recordAcceptances(
        User   $user,
        array  $documentIds,
        string $ipAddress,
        string $userAgent,
        string $method = 'modal'
    ): void {
        $now = now();

        // Filter to IDs that are actually active documents and not yet accepted.
        $alreadyAccepted = UserContractAcceptance::where('user_id', $user->id)
            ->whereIn('legal_document_id', $documentIds)
            ->pluck('legal_document_id')
            ->toArray();

        $toInsert = collect($documentIds)
            ->reject(fn (int $id) => in_array($id, $alreadyAccepted))
            ->unique()
            ->values();

        if ($toInsert->isEmpty()) {
            return;
        }

        $rows = $toInsert->map(fn (int $docId) => [
            'user_id'           => $user->id,
            'legal_document_id' => $docId,
            'accepted_at'       => $now,
            'ip_address'        => mb_substr($ipAddress, 0, 45),
            'user_agent'        => mb_substr($userAgent, 0, 512),
            'acceptance_method' => $method,
            'created_at'        => $now,
            'updated_at'        => $now,
        ])->toArray();

        DB::table('user_contract_acceptances')->insert($rows);

        self::forgetUserCache($user);
    }

    // ── Publishing ────────────────────────────────────────────────────────────

    /**
     * Publish a new version of a legal document.
     *
     * Wraps in a transaction:
     *  1. Deactivates any currently active version of the same type.
     *  2. Creates the new document marked active.
     *
     * After this call every user who hasn't accepted the new row will see it
     * as pending on their next legal/status check — forcing re-acceptance.
     */
    public static function publishVersion(
        string $type,
        string $version,
        string $effectiveDate,
        string $contentFr,
        string $contentEn,
        string $contentAr
    ): LegalDocument {
        $document = DB::transaction(function () use ($type, $version, $effectiveDate, $contentFr, $contentEn, $contentAr) {
            LegalDocument::active()->ofType($type)->update(['is_active' => false]);

            return LegalDocument::create([
                'type'           => $type,
                'version'        => $version,
                'effective_date' => $effectiveDate,
                'content_fr'     => $contentFr,
                'content_en'     => $contentEn,
                'content_ar'     => $contentAr,
                'is_active'      => true,
            ]);
        });

        // Bump the global version so every user's cached status is invalidated.
        self::invalidateAllStatusCaches();

        return $document;
    }
}
