<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProfileFournisseurResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'intervale_prix'   => $this->intervale_prix,
            'product_category' => $this->product_category,
        ];
    }
}
