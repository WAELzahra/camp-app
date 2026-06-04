<?php

namespace App\Console\Commands;

use App\Models\Materielles_categories;
use App\Models\Materielles;
use Illuminate\Console\Command;

class CheckGearInventory extends Command
{
    protected $signature   = 'ai:check-gear';
    protected $description = 'Checks which gear categories have available items in the marketplace.';

    public function handle(): int
    {
        $categories = Materielles_categories::orderBy('is_safety_critical', 'desc')
            ->orderBy('nom')
            ->get();

        if ($categories->isEmpty()) {
            $this->warn('No gear categories found in the database.');
            return self::SUCCESS;
        }

        // Batch-count available items per category (one query, no N+1)
        $availableCounts = Materielles::where('status', 'up')
            ->where('quantite_dispo', '>', 0)
            ->selectRaw('category_id, count(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id');

        $rows            = [];
        $emptyCritical   = [];

        foreach ($categories as $cat) {
            $count  = (int) ($availableCounts[$cat->id] ?? 0);
            $status = $count > 0 ? '✓ OK' : '⚠ EMPTY';

            if ($count === 0 && $cat->is_safety_critical) {
                $emptyCritical[] = $cat->nom;
            }

            $rows[] = [
                $cat->nom,
                $cat->is_safety_critical ? 'yes' : 'no',
                $count,
                $status,
            ];
        }

        $this->table(
            ['Category', 'Safety Critical', 'Available Items', 'Status'],
            $rows
        );

        if (! empty($emptyCritical)) {
            $this->newLine();
            $this->warn('⚠  Safety-critical categories with NO available items:');
            foreach ($emptyCritical as $name) {
                $this->warn("   • {$name}");
            }
        } else {
            $this->newLine();
            $this->info('All safety-critical categories have available items.');
        }

        return self::SUCCESS;
    }
}
