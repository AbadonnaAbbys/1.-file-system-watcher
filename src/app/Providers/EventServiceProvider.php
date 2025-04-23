<?php

namespace App\Providers;

use App\Events\FileChanged;
use App\Listeners\LogFileChanged;
use App\Listeners\OptimizeImageFile;
use App\Listeners\ProcessJsonFile;
use App\Listeners\ProcessTextFile;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        FileChanged::class => [
            LogFileChanged::class,
            OptimizeImageFile::class,
            ProcessJsonFile::class,
            ProcessTextFile::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
