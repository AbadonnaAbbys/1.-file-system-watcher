<?php

namespace App\Listeners;

use App\Events\FileChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogFileChanged
{
    /**
     * Handle the event.
     */
    public function handle(FileChanged $event): void
    {
        // Skip processing for internally generated changes
        if ($event->source === 'internal') {
            return;
        }

        Log::info("File {$event->type}: {$event->path}");
    }
}
