<?php

namespace App\Services\AI\Matching;

final class ClusterResult
{
    public function __construct(
        public readonly int    $clusterId,
        public readonly array  $centroid,       // 6-dim float array
        public readonly array  $memberIds,      // array of profile_ids
        public readonly int    $memberCount,
        public readonly string $clusterLabel,   // human-readable French label
        public readonly float  $cohesion,       // average intra-cluster distance (lower = tighter)
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
