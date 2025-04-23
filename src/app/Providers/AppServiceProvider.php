<?php

namespace App\Providers;

use App\Services\FileSystemWatcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $directories = [
            env('WATCHER_DIRECTORY', '/app/watched'),
        ];

        $this->app->singleton(FileSystemWatcher::class, function ($app) use ($directories) {
            return new FileSystemWatcher($directories);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
