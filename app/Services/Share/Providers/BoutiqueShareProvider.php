<?php

namespace App\Services\Share\Providers;

use App\Models\Boutiques;
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
        $boutique = $this->findBySlugOrId(Boutiques::query(), $slug);
        if (!$boutique) {
            return null;
        }

        return new SharePreview(
            title:        $boutique->nom_boutique ?? 'Boutique',
            description:  $this->sanitize($boutique->description)
                ?: 'Boutique de matériel camping sur TunisiaCamp.',
            image:        $this->imageUrl($boutique->path_to_img),
            frontendPath: '/supplier-profile/' . ($boutique->slug ?: $boutique->id),
            ogType:       'website',
        );
    }
}
