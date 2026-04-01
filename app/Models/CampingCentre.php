<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Photo;

class CampingCentre extends Model
{
    use HasFactory;

    protected $table = 'camping_centres';

    protected $fillable = [
        'nom',
        'type',
        'description',
        'adresse',
        'lat',
        'lng',
        'image',
        'status',
        'validation_status',
        'user_id',
        'profile_centre_id',
    ];

    protected $casts = [
        'status' => 'boolean',
        'lat'    => 'float',
        'lng'    => 'float',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function profileCentre()
    {
        return $this->belongsTo(ProfileCentre::class, 'profile_centre_id');
    }

    public function zones()
    {
        return $this->hasMany(Camping_Zones::class, 'centre_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class, 'camping_centre_id')->orderBy('order');
    }

    public function coverPhoto()
    {
        return $this->hasOne(Photo::class, 'camping_centre_id')->where('is_cover', true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isRegistered(): bool
    {
        return !is_null($this->user_id);
    }

    public function isPublic(): bool
    {
        return (bool) $this->status;
    }

    public function fullDetails(): array
    {
        return [
            'id'          => $this->id,
            'nom'         => $this->nom,
            'type'        => $this->type,
            'description' => $this->description,
            'adresse'     => $this->adresse,
            'lat'         => $this->lat,
            'lng'         => $this->lng,
            'image'       => $this->image,
            'status'      => $this->isPublic(),
            'user'        => $this->user ? [
                'id'            => $this->user->id,
                'name'          => $this->user->name,
                'profile'       => $this->user->profile ?? null,
                'profileCentre' => $this->user->profileCentre ?? null,
            ] : null,
            'zones' => $this->zones
                ? $this->zones->map(fn($z) => [
                    'id'          => $z->id,
                    'nom'         => $z->nom,
                    'lat'         => $z->lat,
                    'lng'         => $z->lng,
                    'description' => $z->description,
                  ])->values()->toArray()
                : [],
        ];
    }
}