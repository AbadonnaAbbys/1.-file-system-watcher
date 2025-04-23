<?php

namespace App\Listeners;

use App\Events\FileChanged;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ExtractZipFile
{
    /**
     * Handle the event.
     */
    public function handle(FileChanged $event): void
    {
        $path = $event->path;
        $type = $event->type;

        if ($type === 'created' && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            try {
                $zip = new ZipArchive;
                if ($zip->open($path) === TRUE) {
                    $extractPath = env('ZIP_EXTRACT_PATH', storage_path('extracted')); // Get extract path from .env
                    if (!is_dir($extractPath)) {
                        mkdir($extractPath, 0777, true);
                    }
                    $zip->extractTo($extractPath);
                    $zip->close();
                    Log::info("ZIP file extracted: {$path} to {$extractPath}");
                } else {
                    Log::error("Failed to open ZIP file: {$path}");
                }
            } catch (\Exception $e) {
                Log::error("Error extracting ZIP file: {$path} - {$e->getMessage()}");
            }
        }
    }
}
