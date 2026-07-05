<?php

namespace App\Services\Share\Providers;

use App\Models\CampingCentre;
use App\Models\Photo;
use App\Services\Share\SharePreview;

class CentreShareProvider extends AbstractShareProvider
{
    public function type(): string
    {
        return 'centre';
    }

    public function resolve(string $slug): ?SharePreview
    {
        /** @var CampingCentre|null $centre */
        $centre = $this->findBySlugOrId(CampingCentre::query()->with('profileCentre.profile'), $slug);
        if (!$centre) {
            return null;
        }

        $description = $this->sanitize(
            $centre->description
            ?: $centre->profileCentre?->profile?->bio
            ?: $centre->adresse
        ) ?: "Centre d'hébergement en Tunisie — réservez sur TunisiaCamp.";

        return new SharePreview(
            title:        $centre->nom ?? 'Centre',
            description:  $description,
            image:        $this->coverImage($centre),
            frontendPath: '/centre-details/' . ($centre->slug ?: $centre->id),
            ogType:       'place',
        );
    }

    /**
     * Photos of the centre itself, then the partner owner's uploads,
     * then the legacy image column, then the profile cover.
     */
    private function coverImage(CampingCentre $centre): ?string
    {
        return $this->imageUrl(
            $this->latestPhotoPath('camping_centre_id', $centre->id)
            ?: $this->latestPhotoPath('user_id', $centre->user_id)
            ?: $centre->image
            ?: $centre->profileCentre?->profile?->cover_image
        );
    }
}
