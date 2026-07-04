<?php

namespace App\Services\Share\Providers;

use App\Models\Events;
use App\Models\Photo;
use App\Services\Share\SharePreview;

class EventShareProvider extends AbstractShareProvider
{
    public function type(): string
    {
        return 'event';
    }

    public function resolve(string $slug): ?SharePreview
    {
        /** @var Events|null $event */
        $event = $this->findBySlugOrId(Events::query(), $slug);
        if (!$event) {
            return null;
        }

        $when        = $event->start_date?->format('d/m/Y');
        $description = $this->sanitize($event->description)
            ?: trim('Événement camping en Tunisie' . ($when ? " — le {$when}" : '') . '. Réservez sur TunisiaCamp.');

        $photo = Photo::where('event_id', $event->id)
            ->whereNotNull('path_to_img')
            ->orderByRaw('is_cover DESC, id DESC')
            ->value('path_to_img');

        return new SharePreview(
            title:        $event->title ?? 'Événement',
            description:  $description,
            image:        $this->imageUrl($photo),
            frontendPath: '/event/' . ($event->slug ?: $event->id) . '/details',
            ogType:       'article',
        );
    }
}
