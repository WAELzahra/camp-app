<?php

namespace App\Services\AI\Safety;

final class SafetyAssessment
{
    public function __construct(
        public readonly int    $score,
        public readonly string $label,
        public readonly array  $factors,
        public readonly string $summary,
        public readonly bool   $blocks_booking,
        public readonly bool   $llm_enriched,
        public readonly string $assessed_at,
    ) {}

    public function toArray(): array
    {
        return [
            'score'          => $this->score,
            'label'          => $this->label,
            'factors'        => array_map(fn (RiskFactor $f) => $f->toArray(), $this->factors),
            'summary'        => $this->summary,
            'blocks_booking' => $this->blocks_booking,
            'llm_enriched'   => $this->llm_enriched,
            'assessed_at'    => $this->assessed_at,
        ];
    }
}
