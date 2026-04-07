<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Annonce;
use App\Models\CampingZone;
use App\Models\ProfileCentre;
use App\Models\Materielles;
use App\Models\User;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'favoritable_id', 'favoritable_type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Load the actual target entity based on type.
     */
    public function resolveTarget(): ?array
    {
        switch ($this->favoritable_type) {
            case 'centre':
                $c = ProfileCentre::with('profile')->find($this->favoritable_id);
                if (!$c) return null;
                return [
                    'id'         => $c->id,
                    'name'       => $c->name ?? '',
                    'address'    => $c->profile?->city ?? $c->profile?->address ?? '',
                    'profilePic' => $c->profile?->cover_image
                        ? asset('storage/' . $c->profile->cover_image)
                        : null,
                ];

            case 'zone':
                $z = CampingZone::find($this->favoritable_id);
                if (!$z) return null;
                return [
                    'id'         => $z->id,
                    'name'       => $z->nom ?? '',
                    'address'    => $z->adresse ?? $z->city ?? '',
                    'profilePic' => $z->cover_image,
                ];

            case 'equipment':
                $m = Materielles::with(['photos', 'category'])->find($this->favoritable_id);
                if (!$m) return null;
                $cover = $m->photos->firstWhere('is_cover', true) ?? $m->photos->first();
                return [
                    'id'         => $m->id,
                    'name'       => $m->nom,
                    'address'    => $m->category?->nom ?? '',
                    'profilePic' => $cover ? asset('storage/' . $cover->path_to_img) : null,
                ];

            case 'profile':
                $u = User::find($this->favoritable_id);
                if (!$u) return null;
                return [
                    'id'         => $u->id,
                    'name'       => trim($u->first_name . ' ' . $u->last_name),
                    'address'    => '',
                    'profilePic' => $u->avatar ? asset('storage/' . $u->avatar) : null,
                ];

            case 'annonce':
                $a = Annonce::with(['photos', 'user'])->find($this->favoritable_id);
                if (!$a) return null;
                $cover = $a->photos->firstWhere('is_cover', true) ?? $a->photos->first();
                return [
                    'id'          => $a->id,
                    'title'       => $a->title,
                    'description' => $a->description,
                    'address'     => $a->address ?? '',
                    'start_date'  => $a->start_date,
                    'end_date'    => $a->end_date,
                    'status'      => $a->status,
                    'type'        => $a->type,
                    'created_at'  => $a->created_at,
                    'views_count' => $a->views_count ?? 0,
                    'likes_count' => $a->likes_count ?? 0,
                    'comments_count' => $a->comments_count ?? 0,
                    'photos'      => $a->photos->map(fn($p) => [
                        'id'         => $p->id,
                        'path_to_img'=> $p->path_to_img,
                        'is_cover'   => $p->is_cover,
                    ])->values()->all(),
                    'user'        => $a->user ? [
                        'id'         => $a->user->id,
                        'first_name' => $a->user->first_name,
                        'last_name'  => $a->user->last_name,
                        'avatar'     => $a->user->avatar,
                    ] : null,
                ];

            default:
                return null;
        }
    }
}
