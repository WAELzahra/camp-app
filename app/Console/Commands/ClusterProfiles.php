<?php

namespace App\Console\Commands;

use App\Services\AI\GroupMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClusterProfiles extends Command
{
    protected $signature   = 'ai:cluster-profiles {--stats : Show cached cluster stats without re-clustering}';
    protected $description = 'Cluster campeur profiles using K-Means + DBSCAN for group matching.';

    public function handle(GroupMatchingService $groupService): int
    {
        if ($this->option('stats')) {
            return $this->showStats($groupService);
        }

        $this->info('Running K-Means (k=4) + DBSCAN clustering on all campeur profiles...');
        $this->newLine();

        $result = $groupService->clusterAllProfiles();

        $kmeans = $result['kmeans'];
        $this->line("Total profiles : <info>{$result['total_profiles']}</info>");
        $this->line("K-Means        : iterations=<info>{$kmeans['iterations']}</info>  converged=<info>" . ($kmeans['converged'] ? 'true' : 'false') . "</info>");

        if (! empty($result['dbscan'])) {
            $noise = count(array_filter($result['dbscan'], fn ($r) => $r['cluster'] === -1));
            $this->line("DBSCAN noise   : <info>{$noise}</info> profiles (outliers / adventurous independents)");
        }

        $this->newLine();

        if (! empty($result['clusters'])) {
            $rows = [];
            foreach ($result['clusters'] as $cluster) {
                $rows[] = [
                    $cluster->clusterId,
                    $cluster->clusterLabel,
                    $cluster->memberCount,
                    number_format($cluster->cohesion, 4),
                ];
            }

            $this->table(
                ['Cluster', 'Label', 'Members', 'Cohesion'],
                $rows
            );
        } else {
            $this->warn('No clusters generated (not enough profiles?).');
        }

        $this->newLine();
        $this->info('Cluster assignments cached for 1 hour. Run with --stats to view without re-clustering.');

        return self::SUCCESS;
    }

    private function showStats(GroupMatchingService $groupService): int
    {
        $stats = $groupService->getClusterStats();

        $this->info('Cached cluster stats (clustered at: ' . ($stats['clustered_at'] ?? 'never') . ')');
        $this->newLine();

        if (empty($stats['clusters'])) {
            $this->warn('No cached clusters found. Run without --stats to trigger clustering.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($stats['clusters'] as $cluster) {
            $rows[] = [
                $cluster['clusterId'],
                $cluster['clusterLabel'],
                $cluster['memberCount'],
                number_format($cluster['cohesion'], 4),
            ];
        }

        $this->table(
            ['Cluster', 'Label', 'Members', 'Cohesion'],
            $rows
        );

        $this->line("Total profiles    : <info>{$stats['total']}</info>");
        $this->line("DBSCAN noise      : <info>{$stats['dbscan_noise_count']}</info>");
        $this->line("Algorithm         : <info>{$stats['algorithm']}</info>");

        return self::SUCCESS;
    }
}
