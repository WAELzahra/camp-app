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

    private function coverImage(CampingCentre $centre): ?string
    {
        // 1. Photos attached to the centre itself (cover first)
        $photo = Photo::where('camping_centre_id', $centre->id)
            ->whereNotNull('path_to_img')
            ->orderByRaw('is_cover DESC, id DESC')
            ->value('path_to_img');

        // 2. Photos of the partner owner's account (admin/owner uploads)
        if (!$photo && $centre->user_id) {
            $photo = Photo::where('user_id', $centre->user_id)
                ->whereNotNull('path_to_img')
                ->orderByRaw('is_cover DESC, id DESC')
                ->value('path_to_img');
        }

        // 3. Legacy image column, then the profile cover
        return $this->imageUrl(
            $photo
            ?: $centre->image
            ?: $centre->profileCentre?->profile?->cover_image
        );
    }
}
