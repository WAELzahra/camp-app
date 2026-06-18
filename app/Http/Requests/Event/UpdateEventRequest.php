<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'event_type' => 'sometimes|in:camping,hiking,voyage,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'capacity' => 'nullable|integer|min:1',
            'price' => 'sometimes|numeric|min:0',

            // Camping
            'camping_duration' => 'nullable|integer|min:1',
            'camping_gear' => 'nullable|string',
            'is_group_travel' => 'sometimes|boolean',

            // Transport / voyage
            'departure_city' => 'nullable|string',
            'arrival_city' => 'nullable|string',
            'departure_time' => 'nullable|date_format:H:i',
            'estimated_arrival_time' => 'nullable|date_format:H:i',
            'bus_company' => 'nullable|string',
            'bus_number' => 'nullable|string',

            // city_stops — accepts array (FormData notation) or omitted
            'city_stops' => 'nullable|array',
            'city_stops.*.city' => 'required_with:city_stops|string',
            'city_stops.*.arrival_time' => 'nullable|date_format:H:i',
            'city_stops.*.departure_time' => 'nullable|date_format:H:i',

            // Location
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string',

            // Hiking
            'difficulty' => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration' => 'nullable|numeric|min:0.5|max:48',
            'elevation_gain' => 'nullable|integer|min:0|max:8000',

            // Tags
            'tags' => 'nullable|array',
            'tags.*' => 'string',

            // Image management
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'cover_image_index' => 'nullable|integer|min:0',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer|exists:photos,id',
        ];
    }
}
