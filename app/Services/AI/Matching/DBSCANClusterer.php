<?php

namespace App\Services\AI\Matching;

class DBSCANClusterer
{
    public function __construct(
        private float $epsilon   = 0.3,
        private int   $minPoints = 2,
    ) {}

    /**
     * @param  array $vectors  [['id' => int, 'dims' => float[6]], ...]
     * @return array           [['id' => int, 'cluster' => int|-1], ...]  -1 = noise
     */
    public function cluster(array $vectors): array
    {
        $count     = count($vectors);
        $visited   = array_fill(0, $count, false);
        $clusterOf = array_fill(0, $count, -1); // -1 = unassigned/noise
        $clusterId = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($visited[$i]) {
                continue;
            }
            $visited[$i] = true;
            $neighbors   = $this->getNeighbors($vectors, $i);

            if (count($neighbors) < $this->minPoints) {
                $clusterOf[$i] = -1; // noise
            } else {
                $this->expandCluster($vectors, $i, $neighbors, $clusterId, $visited, $clusterOf);
                $clusterId++;
            }
        }

        $result = [];
        foreach ($vectors as $idx => $v) {
            $result[] = ['id' => $v['id'], 'cluster' => $clusterOf[$idx]];
        }
        return $result;
    }

    private function expandCluster(
        array $vectors,
        int   $pointIdx,
        array $neighbors,
        int   $clusterId,
        array &$visited,
        array &$clusterOf,
    ): void {
        $clusterOf[$pointIdx] = $clusterId;
        $seeds = $neighbors;

        $si = 0;
        while ($si < count($seeds)) {
            $q = $seeds[$si];
            $si++;

            if (! $visited[$q]) {
                $visited[$q]   = true;
                $qNeighbors    = $this->getNeighbors($vectors, $q);
                if (count($qNeighbors) >= $this->minPoints) {
                    foreach ($qNeighbors as $n) {
                        if (! in_array($n, $seeds, true)) {
                            $seeds[] = $n;
                        }
                    }
                }
            }

            if ($clusterOf[$q] === -1) {
                $clusterOf[$q] = $clusterId;
            }
        }
    }

    private function getNeighbors(array $vectors, int $pointIdx): array
    {
        $neighbors = [];
        $point     = $vectors[$pointIdx]['dims'];
        foreach ($vectors as $i => $v) {
            if ($i === $pointIdx) {
                continue;
            }
            if ($this->euclideanDistance($point, $v['dims']) <= $this->epsilon) {
                $neighbors[] = $i;
            }
        }
        return $neighbors;
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $diff = ($a[$i] ?? 0.0) - ($b[$i] ?? 0.0);
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }
}
