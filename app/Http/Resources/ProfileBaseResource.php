<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Wraps the Profile model for API responses.
 * cin_path is NEVER exposed — not even to self. has_cin bool is exposed to self only.
 */
class ProfileBaseResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        // Compute self-view from the profile's user_id
        $isSelf = $request->user()?->id === $this->resource->user_id;

        $data = [
            'type'        => $this->type,
            'bio'         => $this->bio,
            'cover_image' => $this->cover_image ? storage_url(ltrim($this->cover_image, '/')) : null,
            'city'        => $this->city,
            'address'     => $this->address,
            'is_public'   => (bool) $this->is_public,
            'activities'  => $this->activities,
        ];

        if ($isSelf) {
            $data['has_cin'] = (bool) $this->cin_path;
        }

        return $data;
    }
}
