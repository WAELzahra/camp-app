<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProfileCampeurResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'skill_level'           => $this->skill_level,
            'comfort_level'         => $this->comfort_level,
            'budget_range'          => $this->budget_range,
            'preferred_trip_styles' => $this->preferred_trip_styles,
            'preferred_activities'  => $this->preferred_activities,
            'gear_preferences'      => $this->gear_preferences,
            'total_trips'           => $this->total_trips,
        ];
    }
}
