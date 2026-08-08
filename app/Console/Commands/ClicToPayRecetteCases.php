<?php

namespace App\Console\Commands;

use App\Services\Payments\ClicToPayRecetteRunner;
use Illuminate\Console\Command;

/**
 * Console front-end for ClicToPayRecetteRunner — runs the cahier-de-recettes
 * error cases (CTP-06/07/08) and prints the raw JSON to paste into column G.
 *
 * The happy-path cases (CTP-01 to CTP-05) are NOT here: they must be produced by
 * a real booking on the site, and their JSON is already written to the log by
 * ClicToPayGateway on every call.
 */
class ClicToPayRecetteCases extends Command
{
    protected $signature = 'clictopay:recette
                            {--case=all : all|06|07|08}
                            {--order-number=ORDER-DUP-TEST : orderNumber used by CTP-07 — change it to re-run, a used one errors on both calls}';
    protected $description = 'Run the ClicToPay cahier-de-recettes error cases (CTP-06/07/08) and print the raw JSON responses.';

    public function handle(ClicToPayRecetteRunner $runner): int
    {
        $case = (string) $this->option('case');

        if (!in_array($case, ClicToPayRecetteRunner::CASES, true)) {
            $this->error('--case doit valoir : ' . implode('|', ClicToPayRecetteRunner::CASES));

            return Command::FAILURE;
        }

        $this->line('Base URL: ' . rtrim((string) config('services.clictopay.base_url'), '/') . '/');
        $this->newLine();

        foreach ($runner->run($case, (string) $this->option('order-number')) as $result) {
            $this->comment("── {$result['id']} — {$result['label']}");
            $this->line(json_encode(
                $result['response'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
            $this->newLine();
        }

        $this->info('Terminé. Les mêmes réponses figurent aussi dans storage/logs/laravel.log.');

        return Command::SUCCESS;
    }
}
