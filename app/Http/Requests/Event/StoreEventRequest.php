<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Event fields
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_type' => 'required|in:camping,hiking,voyage,custom',
            'start_date' => 'nullable|date|required_unless:event_type,custom',
            'end_date' => 'nullable|date|required_unless:event_type,custom|after_or_equal:start_date',
            'capacity' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',

            // Camping specific fields
            'camping_duration' => 'nullable|integer|min:1',
            'camping_gear' => 'nullable|string',
            'is_group_travel' => 'sometimes|boolean',

            // Trip/Voyage fields
            'departure_city' => 'nullable|string',
            'arrival_city' => 'nullable|string',
            'departure_time' => 'nullable|date_format:H:i',
            'estimated_arrival_time' => 'nullable|date_format:H:i',
            'bus_company' => 'nullable|string',
            'bus_number' => 'nullable|string',
            'city_stops' => 'nullable|array',
            'city_stops.*.city' => 'required|string',
            'city_stops.*.arrival_time' => 'nullable|date_format:H:i',
            'city_stops.*.departure_time' => 'nullable|date_format:H:i',

            // Location fields
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string',

            // Hiking specific fields
            'difficulty' => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration' => 'nullable|numeric|min:0.5|max:48',
            'elevation_gain' => 'nullable|integer|min:0|max:8000',

            // Tags
            'tags' => 'nullable|array',
            'tags.*' => 'string',

            // Images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'cover_image_index' => 'nullable|integer|min:0',
        ];
    }
}
