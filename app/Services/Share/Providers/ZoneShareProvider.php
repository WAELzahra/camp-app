<?php

namespace App\Services\Share\Providers;

use App\Models\CampingZone;
use App\Models\Photo;
use App\Services\Share\SharePreview;

class ZoneShareProvider extends AbstractShareProvider
{
    public function type(): string
    {
        return 'zone';
    }

    public function resolve(string $slug): ?SharePreview
    {
        /** @var CampingZone|null $zone */
        $zone = $this->findBySlugOrId(CampingZone::query(), $slug);
        if (!$zone) {
            return null;
        }

        $location    = implode(', ', array_filter([$zone->city, $zone->region]));
        $description = $this->sanitize($zone->description ?: $zone->full_description)
            ?: ($location !== ''
                ? "Zone de camping à {$location} — découvrez-la sur TunisiaCamp."
                : 'Zone de camping en Tunisie — découvrez-la sur TunisiaCamp.');

        return new SharePreview(
            title:        $zone->nom ?? 'Zone de camping',
            description:  $description,
            image:        $this->imageUrl($this->latestPhotoPath('camping_zone_id', $zone->id)),
            frontendPath: '/zones-details/' . ($zone->slug ?: $zone->id),
            ogType:       'place',
        );
    }
}
