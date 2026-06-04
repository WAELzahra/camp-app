<?php

namespace App\Services\AI\Explainability;

final class Explanation
{
    public function __construct(
        public readonly string $why,
        public readonly array  $factors,
        public readonly float  $confidence,
        public readonly string $source,
        public readonly bool   $detailAvailable,
        public readonly bool   $llmEnriched,
    ) {}

    public function toArray(): array { return get_object_vars($this); }

    public static function unavailable(string $source): self
    {
        return new self(
            why: 'Explication non disponible.',
            factors: [],
            confidence: 0.0,
            source: $source,
            detailAvailable: false,
            llmEnriched: false,
        );
    }
}
