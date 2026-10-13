<?php

namespace App\Console\Commands;

use App\Search\SearchIndexer;
use Illuminate\Console\Command;

class ReindexSearch extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Rebuild the full-text and embedding index for politicians, parties, and records';

    public function handle(SearchIndexer $indexer): int
    {
        $count = $indexer->reindexAll();
        $this->info('Indexed '.$count.' catalog rows.');

        return self::SUCCESS;
    }
}
