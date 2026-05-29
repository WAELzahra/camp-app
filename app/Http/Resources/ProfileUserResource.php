<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * User section inside a profile response.
 *
 * Self-view (viewer IS the owner): returns personal fields including email.
 * Public view: returns only publicly-safe fields — email is never included.
 */
class ProfileUserResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $isSelf = $request->user()?->id === $this->resource->id;

        $data = [
            'uuid'         => $this->uuid,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'avatar'       => $this->avatar ? storage_url($this->avatar) : null,
            'role'         => $this->role?->name,
            'phone_number' => $this->phone_number,
            'ville'        => $this->ville,
        ];

        if ($isSelf) {
            $data['email']             = $this->email;
            $data['date_naissance']    = $this->date_naissance;
            $data['sexe']              = $this->sexe;
            $data['langue']            = $this->langue;
            $data['is_active']         = (bool) $this->is_active;
            $data['email_verified_at'] = $this->email_verified_at;
            $data['first_login']       = (bool) $this->first_login;
            $data['last_login_at']     = $this->last_login_at;
            $data['created_at']        = $this->created_at;
        }

        return $data;
    }
}
