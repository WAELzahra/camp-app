<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Safe public representation of an authenticated User.
 *
 * - Exposes `uuid` as the stable public identifier.
 * - Never returns numeric `id`, `role_id`, `provider_id`, or any FK ending in _id.
 * - Resolves the avatar to a full storage URL so clients never build storage paths.
 */
class UserResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'first_name'         => $this->first_name,
            'last_name'          => $this->last_name,
            'email'              => $this->email,
            'email_verified_at'  => $this->email_verified_at,
            'phone_number'       => $this->phone_number,
            'ville'              => $this->ville,
            'date_naissance'     => $this->date_naissance,
            'sexe'               => $this->sexe,
            'langue'             => $this->langue,
            'avatar'             => $this->avatar ? storage_url($this->avatar) : null,
            'role'               => $this->role?->name,
            'is_active'          => (bool) $this->is_active,
            'first_login'        => (bool) $this->first_login,
            'last_login_at'      => $this->last_login_at,
            'created_at'         => $this->created_at,
            'provider'           => $this->provider,
        ];
    }
}
