<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One already-published listing bundled into a Programme. `item_type` +
 * `item_id` is a polymorphic reference into the listing's own table
 * (Events / ProfileCentre / Materielles) — never a re-entered stand-in.
 */
class ProgrammeItem extends Model
{
    protected $fillable = [
        'programme_id',
        'sort_order',
        'day_offset',
        'start_time',
        'end_time',
        'item_type',
        'item_id',
        'price',
        'commission_rate',
    ];

    protected $casts = [
        'price' => 'float',
        'commission_rate' => 'float',
    ];

    private const MODELS = [
        'event' => Events::class,
        // ProfileCentre (not CampingCentre) is the real, owned listing: in this
        // dataset camping_centres rows are largely unclaimed directory entries
        // (user_id is null on all of them), while profile_centres rows are the
        // actual registered centre accounts with a real owner behind them.
        'centre' => ProfileCentre::class,
        'materiel' => Materielles::class,
    ];

    private const COMMISSION_TYPES = [
        'event' => 'group',
        'centre' => 'center',
        'materiel' => 'supplier',
    ];

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * The actual listing this item references (Events|CampingCentre|Materielles).
     */
    public function listing(): ?Model
    {
        $model = self::MODELS[$this->item_type] ?? null;

        return $model ? $model::find($this->item_id) : null;
    }

    /**
     * The real platform user who should be credited when this item is paid out.
     */
    public function ownerUserId(): ?int
    {
        $listing = $this->listing();
        if (!$listing) {
            return null;
        }

        return match ($this->item_type) {
            'event' => $listing->group_id,
            'centre' => $listing->user?->id,
            'materiel' => $listing->fournisseur_id,
            default => null,
        };
    }

    /**
     * The CommissionService rate-type key matching this item's actor.
     */
    public function commissionType(): string
    {
        return self::COMMISSION_TYPES[$this->item_type] ?? 'group';
    }

    public function displayTitle(): ?string
    {
        $listing = $this->listing();
        if (!$listing) {
            return null;
        }

        return match ($this->item_type) {
            'event' => $listing->title,
            'centre' => $listing->name,
            'materiel' => $listing->nom,
            default => null,
        };
    }

    public function coverImageUrl(): ?string
    {
        $listing = $this->listing();

        return $listing ? self::resolveImage($this->item_type, $listing) : null;
    }

    public function subtitle(): ?string
    {
        $listing = $this->listing();

        return $listing ? self::resolveSubtitle($this->item_type, $listing) : null;
    }

    public function fullDescription(): ?string
    {
        $listing = $this->listing();

        return match ($this->item_type) {
            'event', 'materiel' => $listing?->description,
            default => null,
        };
    }

    public function ownerName(): ?string
    {
        $owner = User::find($this->ownerUserId());

        return $owner ? trim("{$owner->first_name} {$owner->last_name}") : null;
    }

    public function ownerAvatar(): ?string
    {
        $owner = User::find($this->ownerUserId());

        return $owner?->avatar ? storage_url($owner->avatar) : null;
    }

    /**
     * Average rating (0-5) left on the underlying listing, when that concept
     * exists for this type — Events/Materielles both have a feedbacks()
     * relation with a `note` column; ProfileCentre has no equivalent here.
     */
    public function rating(): ?float
    {
        $listing = $this->listing();
        if (!$listing || !in_array($this->item_type, ['event', 'materiel'])) {
            return null;
        }

        $avg = $listing->feedbacks()->avg('note');

        return $avg ? round((float) $avg, 1) : null;
    }

    /**
     * What the frontend needs to link this item back to the actor's own
     * public pages: a slug-routed listing for event/materiel (they each have
     * their own detail page), plus the owner's user id for a profile link —
     * every item type resolves to a real platform user, so a profile link is
     * always available even when the listing itself has no dedicated page
     * (ProfileCentre's case).
     */
    public function linkInfo(): array
    {
        $listing = $this->listing();
        if (!$listing) {
            return ['slug' => null, 'owner_user_id' => null];
        }

        return [
            'slug' => in_array($this->item_type, ['event', 'materiel']) ? $listing->slug : null,
            'owner_user_id' => $this->ownerUserId(),
        ];
    }

    /**
     * A representative image for the listing, so an admin picking from
     * search results (or a camper browsing a Programme) sees the same
     * photo as on the listing's own public page.
     */
    public static function resolveImage(string $type, Model $listing): ?string
    {
        if ($type === 'centre') {
            $campingCentre = CampingCentre::where('profile_centre_id', $listing->id)->first();
            if (!$campingCentre) {
                return null;
            }
            $cover = $campingCentre->photos()->where('is_cover', true)->first() ?? $campingCentre->photos()->first();

            return $cover?->url ?? ($campingCentre->image ? storage_url($campingCentre->image) : null);
        }

        // Events / Materielles both expose a hasMany photos() with an is_cover flag.
        $cover = $listing->photos->firstWhere('is_cover', true) ?? $listing->photos->first();

        return $cover?->url;
    }

    /**
     * A one-line description to give the admin/camper enough context without
     * leaving the Programme page — falls back to null when the listing type
     * genuinely has nothing usable (ProfileCentre has no description field).
     */
    public static function resolveSubtitle(string $type, Model $listing): ?string
    {
        return match ($type) {
            'event', 'materiel' => $listing->description ? \Illuminate\Support\Str::limit($listing->description, 140) : null,
            'centre' => $listing->effective_host_type ? ucfirst($listing->effective_host_type) : null,
            default => null,
        };
    }
}
