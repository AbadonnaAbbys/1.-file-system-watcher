<?php

namespace App\Console\Commands;

use App\Services\FileSystemWatcher;
use Illuminate\Console\Command;

class WatchFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watch:files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start watching for file changes';

    /**
     * Execute the console command.
     */
    public function handle(FileSystemWatcher $watcher): int
    {
        // No need to manually listen to events, Laravel Events will handle it
        $watcher->watch();

        $this->info('Watching for file changes...');

        return Command::SUCCESS;
    }
}
