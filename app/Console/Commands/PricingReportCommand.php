<?php

namespace App\Console\Commands;

use App\Models\Materielles;
use App\Models\Reservations_materielles;
use App\Services\AI\DynamicPricingService;
use Illuminate\Console\Command;

class PricingReportCommand extends Command
{
    protected $signature   = 'ai:pricing-report {--type=materielle : zone or materielle}';
    protected $description = 'Display market pricing overview, trending tags, and top booked items.';

    public function handle(DynamicPricingService $pricingService): int
    {
        $type = $this->option('type');

        if (! in_array($type, ['zone', 'materielle'], true)) {
            $this->error('Invalid type. Use: materielle or zone');
            return self::FAILURE;
        }

        $this->info("Dynamic Pricing Report — type: {$type}");
        $this->newLine();

        // ── Market overview ───────────────────────────────────────────────────
        $overview = $pricingService->getMarketOverview($type);

        if ($type === 'materielle') {
            $this->line('<info>Market Overview by Category:</info>');
            $rows = [];
            foreach ($overview['by_category'] as $cat) {
                $rows[] = [
                    $cat['category_name'],
                    number_format($cat['avg_price'], 2) . ' TND',
                    number_format($cat['min_price'], 2) . ' TND',
                    number_format($cat['max_price'], 2) . ' TND',
                    $cat['item_count'],
                ];
            }
            $this->table(
                ['Category', 'Avg Price/Night', 'Min', 'Max', 'Items'],
                $rows
            );
        } else {
            $this->line('<info>Market Overview by Terrain Type:</info>');
            $rows = [];
            foreach ($overview['by_terrain'] as $terrain) {
                $rows[] = [
                    $terrain['terrain_type'] ?: '(unset)',
                    number_format($terrain['avg_rating'], 2),
                    number_format($terrain['min_rating'], 2),
                    number_format($terrain['max_rating'], 2),
                    $terrain['zone_count'],
                ];
            }
            $this->table(
                ['Terrain Type', 'Avg Rating', 'Min', 'Max', 'Zones'],
                $rows
            );
        }

        // ── Trending tags ─────────────────────────────────────────────────────
        $this->newLine();
        $tags = $overview['trending_tags'] ?? [];
        $this->line('<info>Trending Trip Tags:</info> ' . (empty($tags) ? '(none)' : implode(', ', $tags)));

        // ── Top 5 most booked items (last 30 days) ────────────────────────────
        $this->newLine();
        $this->line('<info>Top 5 Most Booked Items (last 30 days):</info>');

        if ($type === 'materielle') {
            $topItems = Reservations_materielles::where('created_at', '>=', now()->subDays(30))
                ->select('materielle_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as bookings'))
                ->groupBy('materielle_id')
                ->orderByDesc('bookings')
                ->limit(5)
                ->get();

            if ($topItems->isEmpty()) {
                $this->warn('No reservations found in the last 30 days.');
            } else {
                $matIds = $topItems->pluck('materielle_id')->toArray();
                $mats   = Materielles::whereIn('id', $matIds)->get()->keyBy('id');

                $topRows = [];
                foreach ($topItems as $entry) {
                    $mat       = $mats[$entry->materielle_id] ?? null;
                    $topRows[] = [
                        $entry->materielle_id,
                        mb_substr($mat?->nom ?? '(unknown)', 0, 40),
                        $mat?->tarif_nuit ? number_format($mat->tarif_nuit, 2) . ' TND' : '—',
                        $entry->bookings,
                    ];
                }
                $this->table(['ID', 'Item', 'Price/Night', 'Bookings'], $topRows);
            }
        } else {
            $this->warn('Top bookings by zone not available (zones use centre-level reservations).');
        }

        return self::SUCCESS;
    }
}
