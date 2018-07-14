<?php

namespace Iconscout\Tracker;

/**
 * This file is part of the Laravel Visitor Tracker package.
 *
 * @author     Arpan Rank <arpan@iconscout.com>
 * @copyright  2018
 *
 * For the full copyright and license information,
 * please view the LICENSE.md file that was distributed
 * with this source code.
 */

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Debug\ExceptionHandler;

use Elasticsearch\ClientBuilder;

use Iconscout\Tracker\Console\IndexCommand;
use Iconscout\Tracker\Console\DeleteCommand;
use Iconscout\Tracker\Exceptions\ErrorHandler;
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

        $tracker = new Tracker;

        if (! $tracker->getTrackerDisabled()) {
            $this->registerMiddlewareToGroup('web', TrackerCookie::class);
        }

        if (! $tracker->getTrackerDisabled('logs')) {
            $this->registerMiddlewareToGroup('web', TrackerMiddleware::class);
        }

        if (! $tracker->getTrackerDisabled('errors')) {
            $this->registerErrorHandler();
        }
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

        $this->registerTrackerService();

        $tracker = new Tracker;

        if (! $tracker->getTrackerDisabled('sql_queries')) {
            $this->registerSqlQueryLogWatcher($tracker);
        }
    }

    public function registerTrackerService()
    {
        $this->app->singleton('tracker', function ($app) {
            return new Tracker;
        });
    }

    protected function registerSqlQueryLogWatcher(Tracker $tracker)
    {
        if (class_exists('Illuminate\Database\Events\QueryExecuted')) {
            $this->app['events']->listen('Illuminate\Database\Events\QueryExecuted', function ($query) use ($tracker) {
                $tracker->sqlQuery($query->sql, $query->bindings, $query->time, $query->connectionName);
            });
        } else {
            $this->app['events']->listen('illuminate.query', function ($sql, $bindings, $time, $connection_name) use ($tracker) {
                $tracker->sqlQuery($sql, $bindings, $time, $connection_name);
            });
        }
    }

    protected function registerErrorHandler()
    {
        $previousHandler = null;

        if ($this->app->bound(ExceptionHandler::class) === true) {
            $previousHandler = $this->app->make(ExceptionHandler::class);
        }

        $this->app->singleton(ExceptionHandler::class, function () use ($previousHandler) {
            return new ErrorHandler($previousHandler);
        });
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
