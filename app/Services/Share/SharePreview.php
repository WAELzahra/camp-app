<?php

namespace App\Services\Share;

/**
 * Immutable value object describing one social-share preview.
 * Serializable to/from array so it can live in the cache.
 */
final class SharePreview
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $image,
        /** Path on the SPA the preview links back to, e.g. "/centre-details/dar-zaghouan" */
        public readonly string $frontendPath,
        /** Open Graph object type */
        public readonly string $ogType = 'website',
    ) {
    }

    public function toArray(): array
    {
        return [
            'title'         => $this->title,
            'description'   => $this->description,
            'image'         => $this->image,
            'frontend_path' => $this->frontendPath,
            'og_type'       => $this->ogType,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title:        $data['title'],
            description:  $data['description'],
            image:        $data['image'] ?? null,
            frontendPath: $data['frontend_path'],
            ogType:       $data['og_type'] ?? 'website',
        );
    }
}
