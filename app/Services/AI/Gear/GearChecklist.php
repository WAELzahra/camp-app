<?php

namespace App\Services\AI\Gear;

final class GearChecklist
{
    public function __construct(
        public readonly array  $items,
        public readonly array  $missing_critical,
        public readonly string $risk_level,
        public readonly string $trip_style,
        public readonly string $skill_level,
        public readonly bool   $llm_enriched,
        public readonly string $generated_at,
    ) {}

    public function toArray(): array
    {
        return [
            'items'            => array_map(fn (GearChecklistItem $i) => $i->toArray(), $this->items),
            'missing_critical' => $this->missing_critical,
            'risk_level'       => $this->risk_level,
            'trip_style'       => $this->trip_style,
            'skill_level'      => $this->skill_level,
            'llm_enriched'     => $this->llm_enriched,
            'generated_at'     => $this->generated_at,
        ];
    }
}
