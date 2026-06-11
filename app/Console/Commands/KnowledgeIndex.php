<?php

namespace App\Console\Commands;

use App\Services\AI\KnowledgeIndexer;
use Illuminate\Console\Command;

class KnowledgeIndex extends Command
{
    protected $signature   = 'knowledge:index';
    protected $description = 'Scan codebase and index platform knowledge into Qdrant';

    public function handle(KnowledgeIndexer $indexer): int
    {
        $this->info('Indexing platform knowledge...');

        $result = $indexer->index();

        $this->info("Done. {$result['topics']} topics indexed.");

        return Command::SUCCESS;
    }
}