<?php

namespace App\Listeners;

use App\Events\FileChanged;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class OptimizeImageFile implements ShouldQueue
{
    use InteractsWithQueue;

    private const string OPTIMIZED_FILES_CACHE_KEY = 'optimized_image_files';

    // Set retry limits
    public $tries = 3;
    public $backoff = [5, 15, 30];

    /**
     * Handle the event.
     */
    public function handle(FileChanged $event): void
    {
        $path = $event->path;
        $type = $event->type;

        // Only process created or modified files
        if (!in_array($type, ['created', 'modified'])) {
            return;
        }

        // Verify file exists before processing
        if (!File::exists($path)) {
            Log::warning("File does not exist, cannot optimize: $path");
            return;
        }

        // Get file extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // List of supported image formats
        $supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($extension, $supportedFormats)) {
            return;
        }

        $fileHash = md5($path . '.' . filemtime($path));
        $optimizedFiles = Cache::get(self::OPTIMIZED_FILES_CACHE_KEY, []);

        if (in_array($fileHash, $optimizedFiles)) {
//            Log::info("Image already optimized, skipping: $path");
            return;
        }

        try {
            // Safely get file modification time
            $mtime = File::exists($path) ? filemtime($path) : 0;
            $fileHash = md5($path . '.' . $mtime);

            // Get cached optimized files list
            $optimizedFiles = Cache::get(self::OPTIMIZED_FILES_CACHE_KEY, []);

            if (in_array($fileHash, $optimizedFiles)) {
                Log::info("Image already optimized, skipping: $path");
                return;
            }

            // Get quality settings from .env
            $jpegQuality = (int)env('IMAGE_JPEG_QUALITY', 80);
            $pngCompression = (int)env('IMAGE_PNG_COMPRESSION', 9); // 0-9 for libpng
            $webpQuality = (int)env('IMAGE_WEBP_QUALITY', 80);

            // Process based on file type
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $this->optimizeJpeg($path, $jpegQuality);
                    break;

                case 'png':
                    $this->optimizePng($path, $pngCompression);
                    break;

                case 'webp':
                    $this->optimizeWebp($path, $webpQuality);
                    break;

                case 'gif':
                    $this->optimizeGif($path);
                    break;
            }

            $optimizedFiles[] = $fileHash;
            Cache::put(self::OPTIMIZED_FILES_CACHE_KEY, $optimizedFiles, now()->addDays(30));

            Log::info("Successfully optimized image: $path");
        } catch (Exception $e) {
            Log::error("Failed to optimize image: $path. Error: " . $e->getMessage());
            // Add file to a problem list to avoid repeated attempts
            $this->markFileAsProblem($path);
            // Re-throw the exception to trigger Laravel's retry mechanism
            throw $e;
        }
    }

    /**
     * Mark a file as problematic to avoid repeated processing
     */
    private function markFileAsProblem(string $path): void
    {
        $problemFiles = Cache::get('problem_image_files', []);
        $problemFiles[] = $path;
        Cache::put('problem_image_files', $problemFiles, now()->addDays(7));
    }

    /**
     * Optimize JPEG image
     * @throws Exception
     */
    private function optimizeJpeg(string $path, int $quality): void
    {
        Log::info("Optimizing JPEG image: $path with quality: $quality");

        // Load the image
        $image = @imagecreatefromjpeg($path);

        if (!$image) {
            throw new Exception("Failed to load JPEG image: $path");
        }

        // Save the image with compression
        imagejpeg($image, $path, $quality);

        // Free up memory
        imagedestroy($image);
    }

    /**
     * Optimize PNG image
     * @throws Exception
     */
    private function optimizePng(string $path, int $compression): void
    {
        Log::info("Optimizing PNG image: $path with compression: $compression");

        // Load the image
        $image = @imagecreatefrompng($path);

        if (!$image) {
            throw new Exception("Failed to load PNG image: $path");
        }

        // Save alpha channel
        imagesavealpha($image, true);

        // Set compression level (0-9)
        // For PNG, we can't directly set compression in GD, but we use quality as compression level
        imagepng($image, $path, $compression);

        // Free up memory
        imagedestroy($image);
    }

    /**
     * Optimize WebP image
     * @throws Exception
     */
    private function optimizeWebp(string $path, int $quality): void
    {
        Log::info("Optimizing WebP image: $path with quality: $quality");

        // Load the image
        $image = @imagecreatefromwebp($path);

        if (!$image) {
            throw new Exception("Failed to load WebP image: $path");
        }

        // Save alpha channel
        imagesavealpha($image, true);

        // Save the image with compression
        imagewebp($image, $path, $quality);

        // Free up memory
        imagedestroy($image);
    }

    /**
     * Optimize GIF image
     * @throws Exception
     */
    private function optimizeGif(string $path): void
    {
        Log::info("Optimizing GIF image: $path");

        // Load the image
        $image = @imagecreatefromgif($path);

        if (!$image) {
            throw new Exception("Failed to load GIF image: $path");
        }

        // Save alpha channel
        imagesavealpha($image, true);

        // GIF doesn't support quality setting in GD
        imagegif($image, $path);

        // Free up memory
        imagedestroy($image);
    }
}
