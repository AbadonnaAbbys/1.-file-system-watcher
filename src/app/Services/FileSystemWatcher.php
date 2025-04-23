<?php

namespace App\Services;

use App\Events\FileChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class FileSystemWatcher
{
    private array $directories;
    private array $initialStates = [];

    public function __construct(array $directories)
    {
        $this->directories = $directories;
    }

    public function watch(): void
    {
        $this->initialize();

        while (true) {
            foreach ($this->directories as $directory) {
                $this->checkDirectory($directory);
            }
            sleep(1); // Adjust as needed
        }
    }

    private function initialize(): void
    {
        foreach ($this->directories as $directory) {
            // Ensure directory exists
            if (!File::isDirectory($directory)) {
                Log::warning("Directory $directory does not exist. Creating it.");
                File::makeDirectory($directory, 0777, true);
            }

            $this->initialStates[$directory] = $this->getCurrentState($directory);
        }
    }


    private function getCurrentState(string $directory): array
    {
        $files = [];
        $finder = Finder::create()->in($directory)->files();

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $files[$file->getRealPath()] = $file->getMTime();
        }

        return $files;
    }

    private function checkDirectory(string $directory): void
    {
        $currentState = $this->getCurrentState($directory);
        $previousState = $this->initialStates[$directory];

        // Check for new or modified files
        foreach ($currentState as $path => $modifiedTime) {
            if (!isset($previousState[$path])) {
                $this->dispatchEvent($path, 'created');
            } elseif ($modifiedTime > $previousState[$path]) {
                $this->dispatchEvent($path, 'modified');
            }
        }

        // Check for deleted files
        foreach ($previousState as $path => $modifiedTime) {
            if (!isset($currentState[$path])) {
                $this->dispatchEvent($path, 'deleted');
            }
        }

        $this->initialStates[$directory] = $currentState;
    }

    private function dispatchEvent(string $path, string $type): void
    {
        // Check if this file was recently modified by our code
        $modifiedFiles = Cache::get('modified_files', []);
        $recentlyModified = isset($modifiedFiles[$path]) &&
            (now()->timestamp - $modifiedFiles[$path]) < 10; // 10 seconds threshold

        // If recently modified by our code, don't dispatch an event or dispatch as internal
        if ($recentlyModified) {
            // Either skip completely:
            return;
        } else {
            // Otherwise dispatch as external
            Event::dispatch(new FileChanged($path, $type, 'external'));
            Log::info("External file system event: $type - $path");
        }
    }
}
