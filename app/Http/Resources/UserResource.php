<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        // Compare by internal ID (never serialised, fine for server-side logic)
        $isSelf = $request->user()?->id === $this->resource->id;

        // Public fields — safe to return for any viewer
        $data = [
            'uuid'       => $this->uuid,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'avatar'     => $this->avatar ? storage_url($this->avatar) : null,
            'role'       => $this->role?->name,
        ];

        if (!$isSelf) {
            return $data;
        }

        // Private fields — only the owning user sees these
        return array_merge($data, [
            'email'             => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'phone_number'      => $this->phone_number,
            'ville'             => $this->ville,
            'date_naissance'    => $this->date_naissance,
            'sexe'              => $this->sexe,
            'langue'            => $this->langue,
            'is_active'         => (int) $this->is_active,
            'first_login'       => (bool) $this->first_login,
            'last_login_at'     => $this->last_login_at,
            'created_at'        => $this->created_at,
            'provider'          => $this->provider,
        ]);
    }
}
