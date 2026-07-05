<?php

namespace App\Services\Share\Providers;

use App\Models\Photo;
use App\Models\ProfileGroupe;
use App\Services\Share\SharePreview;

class GroupShareProvider extends AbstractShareProvider
{
    public function type(): string
    {
        return 'group';
    }

    public function resolve(string $slug): ?SharePreview
    {
        /** @var ProfileGroupe|null $group */
        $group = $this->findBySlugOrId(ProfileGroupe::query()->with('profile.user'), $slug);
        if (!$group) {
            return null;
        }

        return new SharePreview(
            title:        $group->nom_groupe ?? 'Groupe',
            description:  $this->sanitize($group->profile?->bio)
                ?: "Groupe organisateur d'événements camping sur TunisiaCamp.",
            image:        $this->coverImage($group),
            frontendPath: '/group-profile/' . ($group->slug ?: $group->id),
            ogType:       'profile',
        );
    }

    private function coverImage(ProfileGroupe $group): ?string
    {
        return $this->imageUrl(
            $group->profile?->cover_image
            ?: $this->latestPhotoPath('user_id', $group->profile?->user_id)
            ?: $group->profile?->user?->avatar
        );
    }
}
