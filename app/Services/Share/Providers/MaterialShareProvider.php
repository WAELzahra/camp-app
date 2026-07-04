<?php

namespace App\Services\Share\Providers;

use App\Models\Materielles;
use App\Models\Photo;
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

        $photo = Photo::where('materielle_id', $material->id)
            ->whereNotNull('path_to_img')
            ->orderByRaw('is_cover DESC, id DESC')
            ->value('path_to_img');

        return new SharePreview(
            title:        $material->nom ?? 'Équipement',
            description:  $this->sanitize($material->description)
                ?: 'Équipement de camping sur le marché TunisiaCamp.',
            image:        $this->imageUrl($photo),
            frontendPath: '/material-details/' . ($material->slug ?: $material->id),
            ogType:       'product',
        );
    }
}
