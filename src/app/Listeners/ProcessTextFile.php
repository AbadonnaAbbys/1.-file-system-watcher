<?php

namespace App\Listeners;

use App\Events\FileChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessTextFile
{
    private const string MODIFIED_FILES_CACHE_KEY = 'modified_files';

    /**
     * Handle the event.
     */
    public function handle(FileChanged $event): void
    {
        // Skip processing for internally generated changes
        if ($event->source === 'internal') {
            return;
        }

        $path = $event->path;
        $type = $event->type;

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'txt') {
            try {
                // Log that we're processing this file to prevent recursive processing
                $this->markFileAsModified($path);

                $baconIpsumUrl = env('BACON_IPSUM_API_URL', 'https://baconipsum.com/api/?type=all-meat&sentences=1');
                $response = Http::get($baconIpsumUrl);

                if ($response->successful()) {
                    $baconText = $response->json()[0] ?? ''; // Get the first sentence
                    File::append($path, "\n\n" . $baconText);

                    Log::info("Text added to file: {$path} ({$type})");
                } else {
                    Log::error(
                        "Failed to fetch Bacon Ipsum text: {$path} ({$type}) - {$response->status()} - {$response->body()}",
                    );
                }
            } catch (\Exception $e) {
                Log::error("Error processing text file: {$path} - {$e->getMessage()}");
            }
        }
    }

    /**
     * Mark a file as being modified by our code
     */
    private function markFileAsModified(string $path): void
    {
        $modifiedFiles = Cache::get(self::MODIFIED_FILES_CACHE_KEY, []);
        $modifiedFiles[$path] = now()->timestamp;
        Cache::put(self::MODIFIED_FILES_CACHE_KEY, $modifiedFiles, now()->addMinutes(5));
    }
}
