<?php

namespace App\Services\AI\Matching;

class KMeansClusterer
{
    private int   $k;
    private int   $maxIterations;
    private float $convergenceThreshold;

    public function __construct(
        int   $k                    = 4,
        int   $maxIterations        = 100,
        float $convergenceThreshold = 0.001,
    ) {
        $this->k                    = $k;
        $this->maxIterations        = $maxIterations;
        $this->convergenceThreshold = $convergenceThreshold;
    }

    /**
     * @param  array $vectors  [['id' => int, 'dims' => float[6]], ...]
     * @return array ['assignments' => [...], 'centroids' => [...], 'iterations' => int, 'converged' => bool]
     */
    public function cluster(array $vectors): array
    {
        if (count($vectors) === 0) {
            return ['assignments' => [], 'centroids' => [], 'iterations' => 0, 'converged' => true];
        }

        $k = min($this->k, count($vectors));
        $centroids   = $this->initializeKMeansPlusPlus($vectors, $k);
        $assignments = $this->assignClusters($vectors, $centroids);
        $converged   = false;
        $iterations  = 0;

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $iterations++;
            $newCentroids = $this->updateCentroids($vectors, $assignments, $k);
            $movement     = $this->centroidMovement($centroids, $newCentroids);
            $centroids    = $newCentroids;
            $assignments  = $this->assignClusters($vectors, $centroids);

            if ($movement < $this->convergenceThreshold) {
                $converged = true;
                break;
            }
        }

        return [
            'assignments' => $assignments,
            'centroids'   => $centroids,
            'iterations'  => $iterations,
            'converged'   => $converged,
        ];
    }

    private function initializeKMeansPlusPlus(array $vectors, int $k): array
    {
        $count     = count($vectors);
        $centroids = [];

        // Pick first centroid randomly
        $firstIdx    = rand(0, $count - 1);
        $centroids[] = $vectors[$firstIdx]['dims'];

        for ($c = 1; $c < $k; $c++) {
            $distances = [];
            $total     = 0.0;

            foreach ($vectors as $v) {
                $minDist = PHP_FLOAT_MAX;
                foreach ($centroids as $centroid) {
                    $dist    = $this->euclideanDistance($v['dims'], $centroid);
                    $minDist = min($minDist, $dist);
                }
                $distSq      = $minDist * $minDist;
                $distances[] = $distSq;
                $total      += $distSq;
            }

            // Pick next centroid with probability proportional to distance squared
            if ($total == 0.0) {
                $centroids[] = $vectors[rand(0, $count - 1)]['dims'];
                continue;
            }

            $rand      = (float) rand() / (float) getrandmax() * $total;
            $cumul     = 0.0;
            $chosen    = $count - 1;
            foreach ($distances as $idx => $d) {
                $cumul += $d;
                if ($cumul >= $rand) {
                    $chosen = $idx;
                    break;
                }
            }
            $centroids[] = $vectors[$chosen]['dims'];
        }

        return $centroids;
    }

    private function assignClusters(array $vectors, array $centroids): array
    {
        $assignments = [];
        foreach ($vectors as $v) {
            $minDist   = PHP_FLOAT_MAX;
            $clusterId = 0;
            foreach ($centroids as $cIdx => $centroid) {
                $dist = $this->euclideanDistance($v['dims'], $centroid);
                if ($dist < $minDist) {
                    $minDist   = $dist;
                    $clusterId = $cIdx;
                }
            }
            $assignments[] = ['id' => $v['id'], 'cluster_id' => $clusterId];
        }
        return $assignments;
    }

    private function updateCentroids(array $vectors, array $assignments, int $k): array
    {
        $dims  = count($vectors[0]['dims']);
        $sums  = array_fill(0, $k, array_fill(0, $dims, 0.0));
        $counts = array_fill(0, $k, 0);

        foreach ($assignments as $idx => $a) {
            $cId = $a['cluster_id'];
            $counts[$cId]++;
            for ($d = 0; $d < $dims; $d++) {
                $sums[$cId][$d] += $vectors[$idx]['dims'][$d];
            }
        }

        $newCentroids = [];
        for ($c = 0; $c < $k; $c++) {
            if ($counts[$c] === 0) {
                // Reinitialize empty cluster to a random vector
                $newCentroids[] = $vectors[rand(0, count($vectors) - 1)]['dims'];
            } else {
                $centroid = [];
                for ($d = 0; $d < $dims; $d++) {
                    $centroid[] = $sums[$c][$d] / $counts[$c];
                }
                $newCentroids[] = $centroid;
            }
        }
        return $newCentroids;
    }

    private function centroidMovement(array $old, array $new): float
    {
        $total = 0.0;
        foreach ($old as $i => $c) {
            $total += $this->euclideanDistance($c, $new[$i]);
        }
        return $total;
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
