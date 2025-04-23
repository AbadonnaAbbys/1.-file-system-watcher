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
        Log::info("File {$event->type}: {$event->path}");
    }
}
