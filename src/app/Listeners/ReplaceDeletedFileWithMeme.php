<?php

namespace App\Listeners;

use App\Events\FileChanged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ReplaceDeletedFileWithMeme
{
    /**
     * Maximum number of retries for API calls
     */
    protected int $maxRetries = 3;

    /**
     * Delay between retries in seconds
     */
    protected int $retryDelay = 2;

    /**
     * Handle the event.
     */
    public function handle(FileChanged $event): void
    {
        $path = $event->path;
        $type = $event->type;

        // Only process deleted files
        if ($type !== 'deleted') {
            return;
        }

        // Check if the deleted file was an image
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

        if (!in_array($extension, $imageExtensions)) {
            // Not an image file, so don't replace it
            return;
        }

        try {
            $success = $this->fetchAndSaveMeme($path);

            if (!$success) {
                // Fallback to generating a local placeholder image if we couldn't get a meme
                $this->createFallbackImage($path);
            }
        } catch (\Exception $e) {
            Log::error("Error replacing deleted image file with meme: {$path} - {$e->getMessage()}");
            $this->createFallbackImage($path);
        }
    }

    /**
     * Attempt to fetch a meme and save it to the given path
     */
    protected function fetchAndSaveMeme(string $path): bool
    {
        $memeApiUrl = config('services.meme.api_url', env('MEME_API_URL', 'https://meme-api.com/gimme'));
        $alternativeApis = [
            'https://meme-api.herokuapp.com/gimme',
            'https://api.imgflip.com/get_memes'
        ];

        // Try primary API with retries
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout(10)->get($memeApiUrl);

                if ($response->successful()) {
                    $data = $response->json();

                    // Handle primary API response format
                    if (isset($data['url']) && filter_var($data['url'], FILTER_VALIDATE_URL)) {
                        $memeUrl = $data['url'];
                        $memeContent = Http::timeout(10)->get($memeUrl)->body();

                        // Ensure the directory exists
                        $directory = dirname($path);
                        if (!File::isDirectory($directory)) {
                            File::makeDirectory($directory, 0755, true);
                        }

                        File::put($path, $memeContent);
                        Log::info("Deleted image file replaced with meme: {$path}");
                        return true;
                    }
                }

                // If we reach this point, the request failed or was invalid
                Log::warning("Meme API attempt {$attempt} failed: {$memeApiUrl} - Status: {$response->status()}");

                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay);
                }
            } catch (\Exception $e) {
                Log::warning("Meme API attempt {$attempt} exception: {$e->getMessage()}");

                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay);
                }
            }
        }

        // Try alternative APIs if primary failed
        foreach ($alternativeApis as $apiUrl) {
            try {
                $response = Http::timeout(10)->get($apiUrl);

                if ($response->successful()) {
                    $data = $response->json();
                    $memeUrl = null;

                    // Handle different API response formats
                    if (isset($data['url'])) {
                        $memeUrl = $data['url'];
                    } elseif (isset($data['data']['memes']) && is_array($data['data']['memes']) && !empty($data['data']['memes'])) {
                        // For imgflip API
                        $randomMeme = $data['data']['memes'][array_rand($data['data']['memes'])];
                        $memeUrl = $randomMeme['url'] ?? null;
                    }

                    if ($memeUrl && filter_var($memeUrl, FILTER_VALIDATE_URL)) {
                        $memeContent = Http::timeout(10)->get($memeUrl)->body();

                        // Ensure the directory exists
                        $directory = dirname($path);
                        if (!File::isDirectory($directory)) {
                            File::makeDirectory($directory, 0755, true);
                        }

                        File::put($path, $memeContent);
                        Log::info("Deleted image file replaced with meme from alternative API: {$path}");
                        return true;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Alternative meme API exception: {$apiUrl} - {$e->getMessage()}");
            }
        }

        return false;
    }

    /**
     * Create a fallback image when API calls fail
     */
    protected function createFallbackImage(string $path): void
    {
        try {
            // Determine appropriate extension for the fallback image
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            // Create a simple colored image with text
            $width = 600;
            $height = 400;
            $image = imagecreatetruecolor($width, $height);

            // Fill with a light gray background
            $bgColor = imagecolorallocate($image, 240, 240, 240);
            imagefill($image, 0, 0, $bgColor);

            // Add text
            $textColor = imagecolorallocate($image, 50, 50, 50);
            $text = "File was deleted\nReplacement image unavailable";

            // Center the text
            $fontPath = 5; // Using built-in font
            $lines = explode("\n", $text);
            $lineHeight = 20;
            $startY = ($height - count($lines) * $lineHeight) / 2;

            foreach ($lines as $i => $line) {
                $textWidth = imagefontwidth($fontPath) * strlen($line);
                $startX = ($width - $textWidth) / 2;
                imagestring($image, $fontPath, $startX, $startY + $i * $lineHeight, $line, $textColor);
            }

            // Ensure the directory exists
            $directory = dirname($path);
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Save the image based on the original extension
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($image, $path, 85);
                    break;
                case 'png':
                    imagepng($image, $path, 9);
                    break;
                case 'gif':
                    imagegif($image, $path);
                    break;
                case 'webp':
                    imagewebp($image, $path, 80);
                    break;
                default:
                    // Default to PNG if extension not supported
                    imagepng($image, $path, 9);
            }

            imagedestroy($image);
            Log::info("Created fallback image for: {$path}");
        } catch (\Exception $e) {
            Log::error("Failed to create fallback image: {$path} - {$e->getMessage()}");
        }
    }
}
