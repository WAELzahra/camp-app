<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Safe public representation of an Event.
 * Strips group_id FK; replaces raw group/photos/supplier relations with
 * whitelisted fields only.
 */
class EventResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        // Resolve cover image from photos relation or pre-computed property
        $coverImage = $this->cover_image ?? null;
        if (!$coverImage && $this->relationLoaded('photos') && $this->photos->isNotEmpty()) {
            $cover = $this->photos->firstWhere('is_cover', true) ?? $this->photos->first();
            $coverImage = $cover->path_to_img ? storage_url($cover->path_to_img) : null;
        }

        // Resolve accepted supplier (may be pre-set by controller or fetched from relation)
        $supplierModel = $this->accepted_supplier
            ?? $this->group?->acceptedSupplierLink?->supplier
            ?? null;

        $acceptedSupplier = $supplierModel ? [
            'uuid'       => $supplierModel->uuid,
            'first_name' => $supplierModel->first_name,
            'last_name'  => $supplierModel->last_name,
            'avatar'     => $supplierModel->avatar ? storage_url($supplierModel->avatar) : null,
        ] : null;

        // Safe group reference (the organiser user)
        $group = null;
        if ($this->relationLoaded('group') && $this->group) {
            $g = $this->group;
            $group = [
                'uuid'       => $g->uuid,
                'first_name' => $g->first_name,
                'last_name'  => $g->last_name,
                'avatar'     => $g->avatar ? storage_url($g->avatar) : null,
                'group_name' => $g->profile?->profileGroupe?->nom_groupe ?? null,
            ];
        }

        // Photos — url + is_cover only, no FK columns or raw paths
        $photos = [];
        if ($this->relationLoaded('photos')) {
            $photos = $this->photos->map(fn($p) => [
                'id'       => $p->id,
                'url'      => $p->path_to_img ? storage_url($p->path_to_img) : null,
                'is_cover' => (bool) $p->is_cover,
                'order'    => $p->order,
            ])->values()->all();
        }

        return [
            'id'                      => $this->id,
            'title'                   => $this->title,
            'description'             => $this->description,
            'event_type'              => $this->event_type,
            'start_date'              => $this->start_date,
            'end_date'                => $this->end_date,
            'capacity'                => $this->capacity,
            'price'                   => $this->price,
            'remaining_spots'         => $this->remaining_spots,
            'camping_duration'        => $this->camping_duration,
            'camping_gear'            => $this->camping_gear,
            'is_group_travel'         => (bool) $this->is_group_travel,
            'departure_city'          => $this->departure_city,
            'arrival_city'            => $this->arrival_city,
            'departure_time'          => $this->departure_time,
            'estimated_arrival_time'  => $this->estimated_arrival_time,
            'bus_company'             => $this->bus_company,
            'bus_number'              => $this->bus_number,
            'city_stops'              => $this->city_stops,
            'difficulty'              => $this->difficulty,
            'hiking_duration'         => $this->hiking_duration,
            'elevation_gain'          => $this->elevation_gain,
            'latitude'                => $this->latitude,
            'longitude'               => $this->longitude,
            'address'                 => $this->address,
            'tags'                    => $this->tags,
            'is_active'               => (bool) $this->is_active,
            'status'                  => $this->status,
            'views_count'             => $this->views_count,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
            'cover_image'             => $coverImage,
            'accepted_supplier'       => $acceptedSupplier,
            'group'                   => $group,
            'photos'                  => $photos,
        ];
    }
}
