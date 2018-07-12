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

use Elasticsearch\ClientBuilder;

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

        $this->registerSqlQueryLogWatcher();
    }

    protected function registerSqlQueryLogWatcher()
    {
        $tracker = new Tracker;

        if ($tracker->getTrackerDisabled() || $tracker->getSqlTrackerDisabled()) {
            return false;
        }

        if (class_exists('Illuminate\Database\Events\QueryExecuted')) {
            $this->app['events']->listen('Illuminate\Database\Events\QueryExecuted', function ($query) use ($tracker) {
                $tracker->logSqlQuery($query->sql, $query->bindings, $query->time, $query->connectionName);
            });
        } else {
            $this->app['events']->listen('illuminate.query', function ($sql, $bindings, $time, $connection_name) use ($tracker) {
                $tracker->logSqlQuery($sql, $bindings, $time, $connection_name);
            });
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
