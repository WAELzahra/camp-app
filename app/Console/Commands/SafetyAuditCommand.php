<?php

namespace App\Console\Commands;

use App\Models\CampingZone;
use App\Services\AI\SafetyService;
use Illuminate\Console\Command;

class SafetyAuditCommand extends Command
{
    protected $signature   = 'ai:safety-check {--zones=10 : Number of zones to audit}';
    protected $description = 'Audits zone risk labels and shows content moderation stats.';

    public function handle(SafetyService $safetyService): int
    {
        $limit = (int) $this->option('zones');

        $zones = CampingZone::orderByRaw("FIELD(danger_level, 'extreme', 'high', 'medium', 'low') ASC")
            ->limit($limit)
            ->get(['id', 'nom', 'terrain_type', 'difficulty', 'danger_level']);

        if ($zones->isEmpty()) {
            $this->warn('No camping zones found in the database.');
            return self::SUCCESS;
        }

        $this->info("Safety audit for top {$zones->count()} zones (ordered by danger level desc):");
        $this->newLine();

        $rows = [];
        foreach ($zones as $zone) {
            $label = $safetyService->getQuickRiskLabel($zone);
            $rows[] = [
                $zone->id,
                mb_substr($zone->nom ?? '', 0, 35),
                $zone->terrain_type  ?? '—',
                $zone->difficulty    ?? '—',
                $zone->danger_level  ?? '—',
                $label,
            ];
        }

        $this->table(
            ['ID', 'Zone', 'Terrain', 'Difficulty', 'Danger Level', 'Risk Label'],
            $rows
        );

        // Highlight danger zones
        $dangerZones = array_filter($rows, fn ($r) => $r[5] === 'warning');
        if (! empty($dangerZones)) {
            $this->newLine();
            $this->error('⚠  Zones with WARNING risk label:');
            foreach ($dangerZones as $row) {
                $this->error("   • [{$row[0]}] {$row[1]} (difficulty={$row[3]}, danger={$row[4]})");
            }
        }

        // Moderation stats
        $this->newLine();
        $this->info('Content Moderation Stats:');
        $stats = $safetyService->getModerationStats();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total moderated',  $stats['total_moderated']],
                ['Approved',         $stats['approved']],
                ['Flagged',          $stats['flagged']],
                ['Rejected',         $stats['rejected']],
                ['LLM moderated',    $stats['llm_moderated']],
            ]
        );

        return self::SUCCESS;
    }
}
