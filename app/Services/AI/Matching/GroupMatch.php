<?php

namespace App\Services\AI\Matching;

final class GroupMatch
{
    public function __construct(
        public readonly int    $groupId,
        public readonly string $groupName,
        public readonly int    $clusterId,
        public readonly float  $similarityScore,   // cosine similarity 0.0–1.0
        public readonly float  $compatibilityPct,  // similarityScore * 100 rounded
        public readonly array  $sharedTraits,      // French strings explaining match
        public readonly string $whyExplanation,    // LLM-generated or rule-based
        public readonly bool   $llmEnriched,
        public readonly array  $memberProfiles,    // top 3 member summaries
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
