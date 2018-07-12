<?php

namespace Iconscout\Tracker;

use Illuminate\Support\ServiceProvider;

use Iconscout\Tracker\Console\IndexCommand;
use Iconscout\Tracker\Console\DeleteCommand;
use Iconscout\Tracker\Middleware\TrackerCookie;
use Iconscout\Tracker\Middleware\TrackerMiddleware;

class TrackerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/tracker.php' => config_path('tracker.php'),
        ], 'tracker');

        $this->registerMiddlewareToGroup('web', TrackerCookie::class);
        $this->registerMiddlewareToGroup('web', TrackerMiddleware::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/tracker.php', 'tracker'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                IndexCommand::class,
                DeleteCommand::class,
            ]);
        }
    }

    /**
     * Register the Tracker Middleware
     *
     * @param  string $middleware
     */
    protected function registerMiddlewareToGroup($group, $middleware)
    {
        $kernel = $this->app['router'];
        $kernel->pushMiddlewareToGroup($group, $middleware);
    }
}
