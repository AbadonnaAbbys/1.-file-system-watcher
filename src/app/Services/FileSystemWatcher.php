<?php

namespace App\Services;

use App\Events\FileChanged;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

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
        Event::dispatch(new FileChanged($path, $type));
        Log::info("File system event: $type - $path");
    }
}
