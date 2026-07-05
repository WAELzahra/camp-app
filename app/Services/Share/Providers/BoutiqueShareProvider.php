<?php

namespace App\Services\Share\Providers;

use App\Models\Boutiques;
use App\Models\Materielles;
use App\Models\Photo;
use App\Services\Share\SharePreview;

class BoutiqueShareProvider extends AbstractShareProvider
{
    public function type(): string
    {
        return 'boutique';
    }

    public function resolve(string $slug): ?SharePreview
    {
        /** @var Boutiques|null $boutique */
        $boutique = $this->findBySlugOrId(Boutiques::query()->with('fournisseur'), $slug);
        if (!$boutique) {
            return null;
        }

        return new SharePreview(
            title:        $boutique->nom_boutique ?? 'Boutique',
            description:  $this->sanitize($boutique->description)
                ?: 'Boutique de matériel camping sur TunisiaCamp.',
            image:        $this->coverImage($boutique),
            frontendPath: '/supplier-profile/' . ($boutique->slug ?: $boutique->id),
            ogType:       'website',
        );
    }

    /**
     * Boutique cover, then any photo uploaded by the supplier's account,
     * then the supplier's avatar, then a photo of one of the shop's items.
     */
    private function coverImage(Boutiques $boutique): ?string
    {
        return $this->imageUrl(
            $boutique->path_to_img
            ?: $this->latestPhotoPath('user_id', $boutique->fournisseur_id)
            ?: $boutique->fournisseur?->avatar
            ?: $this->firstItemPhoto($boutique->fournisseur_id)
        );
    }

    private function firstItemPhoto(?int $fournisseurId): ?string
    {
        if (!$fournisseurId) {
            return null;
        }

        return Photo::whereIn(
                'materielle_id',
                Materielles::where('fournisseur_id', $fournisseurId)->select('id')
            )
            ->whereNotNull('path_to_img')
            ->orderByRaw('is_cover DESC, id DESC')
            ->value('path_to_img');
    }
}
