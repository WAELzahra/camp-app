<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Safe representation of an event reservation for organiser / participant views.
 * Strips all internal FK fields; keeps contact info from the booking form.
 */
class EventReservationResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'                 => $this->name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'group_skill_level'    => $this->group_skill_level,
            'trip_purpose'         => $this->trip_purpose,
            'nbr_place'            => $this->nbr_place,
            'status'               => $this->status,
            'discount_amount'      => $this->discount_amount,
            'payment_method'       => $this->payment_method,
            // Computed financial fields (added dynamically by the controller)
            'platform_fee_rate'    => $this->platform_fee_rate,
            'platform_fee_amount'  => $this->platform_fee_amount,
            'commission_rate'      => $this->commission_rate,
            'commission_amount'    => $this->commission_amount,
            'net_revenue'          => $this->net_revenue,
            'base_amount'          => $this->base_amount,
            'services_total'       => $this->services_total,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'user'     => $this->whenLoaded('user', fn() => $this->resource->user ? [
                'uuid'         => $this->resource->user->uuid,
                'first_name'   => $this->resource->user->first_name,
                'last_name'    => $this->resource->user->last_name,
                'avatar'       => $this->resource->user->avatar
                                    ? storage_url($this->resource->user->avatar)
                                    : null,
            ] : null),
            'services' => EventReservationServiceResource::collection(
                $this->whenLoaded('services')
            ),
        ];
    }
}
