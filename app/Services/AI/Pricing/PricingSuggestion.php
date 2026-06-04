<?php

namespace App\Services\AI\Pricing;

final class PricingSuggestion
{
    public function __construct(
        public readonly int          $entityId,
        public readonly string       $entityType,
        public readonly float        $currentPrice,
        public readonly float        $suggestedMin,
        public readonly float        $suggestedMax,
        public readonly float        $suggestedOptimal,
        public readonly string       $demandLevel,
        public readonly float        $confidenceScore,
        public readonly string       $priceDirection,  // increase | decrease | maintain
        public readonly string       $explanation,
        public readonly array        $actionItems,
        public readonly bool         $llmEnriched,
        public readonly DemandSignal $demandSignal,
        public readonly string       $generatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'entity_id'         => $this->entityId,
            'entity_type'       => $this->entityType,
            'current_price'     => $this->currentPrice,
            'suggested_min'     => $this->suggestedMin,
            'suggested_max'     => $this->suggestedMax,
            'suggested_optimal' => $this->suggestedOptimal,
            'demand_level'      => $this->demandLevel,
            'confidence_score'  => $this->confidenceScore,
            'price_direction'   => $this->priceDirection,
            'explanation'       => $this->explanation,
            'action_items'      => $this->actionItems,
            'llm_enriched'      => $this->llmEnriched,
            'demand_signal'     => $this->demandSignal->toArray(),
            'generated_at'      => $this->generatedAt,
        ];
    }
}
