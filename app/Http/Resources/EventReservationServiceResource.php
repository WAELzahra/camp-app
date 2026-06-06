<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class EventReservationServiceResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'quantity'               => $this->quantity,
            'notes'                  => $this->notes,
            'price_snapshot'         => $this->price_snapshot,
            'pricing_unit_snapshot'  => $this->pricing_unit_snapshot,
            'subtotal'               => $this->subtotal,
        ];
    }
}
