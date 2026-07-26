<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * The `fournisseur` user shown on public boutique/shop pages.
 *
 * Unlike UserResource, email and phone_number are intentionally public here —
 * shop pages display them as the supplier's contact card. Everything else
 * that isn't needed for that (birthdate, gender, language, address, login
 * activity, raw email_verified_at timestamp, ...) is left out.
 */
class PublicSupplierResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'avatar'       => $this->avatar ? storage_url($this->avatar) : null,
            'email'        => $this->email,
            'phone_number' => $this->phone_number,
            'ville'        => $this->ville,
            'is_verified'  => $this->email_verified_at !== null,
            'profile'      => $this->whenLoaded('profile', fn () => [
                'city'    => $this->profile?->city,
                'address' => $this->profile?->address,
            ]),
        ];
    }
}
