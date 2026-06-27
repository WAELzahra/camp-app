<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    protected $fillable = [
        'type',
        'version',
        'effective_date',
        'content_fr',
        'content_en',
        'content_ar',
        'is_active',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_active'      => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function acceptances(): HasMany
    {
        return $this->hasMany(UserContractAcceptance::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Whether the given user has already accepted this document version. */
    public function hasBeenAcceptedBy(User $user): bool
    {
        return $this->acceptances()->where('user_id', $user->id)->exists();
    }

    /** Human-readable label for UI display. */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'cgu'              => 'Conditions Générales d\'Utilisation',
            'cgv'              => 'Conditions Générales de Vente',
            'mentions_legales' => 'Mentions Légales',
            'confidentialite'  => 'Politique de Confidentialité',
            default            => $this->type,
        };
    }

    /** Returns content for the requested locale (defaults to French). */
    public function contentForLocale(string $locale): string
    {
        return match ($locale) {
            'en'    => $this->content_en,
            'ar'    => $this->content_ar,
            default => $this->content_fr,
        };
    }
}
