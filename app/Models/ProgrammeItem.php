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
}
