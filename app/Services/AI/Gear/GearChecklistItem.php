<?php

namespace App\Services\AI\Gear;

final class GearChecklistItem
{
    public function __construct(
        public readonly int    $materielle_id,
        public readonly string $nom,
        public readonly string $brand,
        public readonly string $category,
        public readonly float  $tarif_nuit,
        public readonly string $url,
        public readonly bool   $is_available,
        public readonly bool   $is_critical,
        public readonly string $reason,
        public readonly string $tip,
        public readonly int    $priority,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
