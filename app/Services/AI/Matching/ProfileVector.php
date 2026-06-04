<?php

namespace App\Services\AI\Matching;

final class ProfileVector
{
    public function __construct(
        public readonly int    $profileId,
        public readonly int    $userId,
        public readonly array  $dimensions,  // 6 float values normalized 0.0–1.0
        public readonly array  $raw,         // original unnormalized values for display
        public readonly string $skillLevel,
        public readonly string $budgetRange,
        public readonly string $comfortLevel,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
