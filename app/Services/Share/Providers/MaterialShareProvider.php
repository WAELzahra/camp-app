<?php

namespace App\Services\Share\Providers;

use App\Models\Boutiques;
use App\Models\Materielles;
use App\Services\Share\SharePreview;

class MaterialShareProvider extends AbstractShareProvider
{
    public function type(): string
    {
        return 'material';
    }

    public function resolve(string $slug): ?SharePreview
    {
        /** @var Materielles|null $material */
        $material = $this->findBySlugOrId(Materielles::query(), $slug);
        if (!$material) {
            return null;
        }

        return new SharePreview(
            title:        $material->nom ?? 'Équipement',
            description:  $this->sanitize($material->description)
                ?: 'Équipement de camping sur le marché TunisiaCamp.',
            image:        $this->coverImage($material),
            frontendPath: '/material-details/' . ($material->slug ?: $material->id),
            ogType:       'product',
        );
    }

    /**
     * Photos of the item itself, then the supplier's boutique image,
     * then any photo uploaded by the supplier's account.
     */
    private function coverImage(Materielles $material): ?string
    {
        $path = $this->latestPhotoPath('materielle_id', $material->id);

        if (!$path && $material->fournisseur_id) {
            $path = Boutiques::where('fournisseur_id', $material->fournisseur_id)
                ->whereNotNull('path_to_img')
                ->value('path_to_img');
        }

        if (!$path) {
            $path = $this->latestPhotoPath('user_id', $material->fournisseur_id);
        }

        return $this->imageUrl($path);
    }
}
