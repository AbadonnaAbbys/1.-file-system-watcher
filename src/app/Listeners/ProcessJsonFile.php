<?php

namespace App\Listeners;

use App\Events\FileChanged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File; // Use Laravel's File facade

class ProcessJsonFile
{
    /**
     * Handle the event.
     */
    public function handle(FileChanged $event): void
    {
        $path = $event->path;
        $type = $event->type;

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
            try {
                $jsonContent = File::get($path); // Use Laravel's File::get()
                $data = json_decode($jsonContent, true);

                $url = env('JSON_ENDPOINT_URL', 'https://fswatcher.requestcatcher.com/'); // Get URL from .env

                $response = Http::post($url, $data);

                if ($response->successful()) {
                    Log::info("JSON file sent successfully: {$path} ({$type})");
                } else {
                    Log::error("Failed to send JSON file: {$path} ({$type}) - {$response->status()} - {$response->body()}");
                }
            } catch (\Exception $e) {
                Log::error("Error processing JSON file: {$path} - {$e->getMessage()}");
            }
        }
    }
}
