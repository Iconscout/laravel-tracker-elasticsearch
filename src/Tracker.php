<?php

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

namespace Iconscout\Tracker;

use DB;
use Carbon\Carbon;
use Webpatser\Uuid\Uuid;
use Jenssegers\Agent\Agent;
// use Elasticsearch\ClientBuilder;
use Snowplow\RefererParser\Parser;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
// use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

use Iconscout\Tracker\Drivers\ElasticSearch;
use Iconscout\Tracker\Jobs\TrackerIndexQueuedModels;

class Tracker
{
    /**
     * @var string
     */
    protected $es;

    /**
     * @var string
     */
    protected $request;

    /**
     * ElasticSearch constructor.
     */
    public function __construct()
    {
        $this->es = new ElasticSearch;
        $this->request = request();
    }

    public function trackLog()
    {
        if ($this->excludedTracker()) {
            return false;
        }

        $model = $this->indexLogDocument();
        $type = 'logs';

        if (Config::get('tracker.queue', false)) {
            return $this->indexQueueLogDocument($model, $type);
        }

        return $this->es->indexDocument($model, $type);
    }

    public function trackEvents($events)
    {
        $cookie = $this->cookieTracker();
        $log = Cache::tags(['tracker.cookie'])->get($cookie);

        if (is_array($events)) {
            foreach ($events as $type => $properties) {
                $log['events'][] = [
                    'type' => $type,
                    'properties' => $properties
                ];
            }
        } else {
            $args = func_get_args();

            if (count($args) > 1) {
                $log['events'][] = [
                    'type' => $args[0],
                    'properties' => $args[1]
                ];
            }
        }

        Cache::tags(['tracker.cookie'])->forever($cookie, $log);
    }

    public function indexQueueLogDocument($model, $type)
    {
        dispatch((new TrackerIndexQueuedModels($model, $type))
                ->onQueue($this->syncWithTrackerUsingQueue())
                ->onConnection($this->syncWithTrackerUsing()));

        return true;
    }

    public function indexLogDocument()
    {
        $agent = new Agent;
        $request = $this->request;
        $browser = $agent->browser();
        $platform = $agent->platform();
        $referer_url = $request->headers->get('referer');

        $cookie = $this->cookieTracker();
        $log = Cache::tags(['tracker.cookie'])->get($cookie);

        if (empty($log)) {
            $log = [];
        }

        $model = $log + [
            'referer' => [
                'url' => $referer_url,
                'domain' => $this->domain($referer_url),
                'medium' => null,
                'source' => null,
                'search_term' => null
            ],
            'url' => $request->url(),
            'params' => $request->query(),
            'route' => $this->routeName($request),
            'device' => [
                'kind' => $agent->device(),
                'model' => $this->getDeviceKind($agent),
                'platform' => $platform,
                'platform_version' => $agent->version($platform),
                'is_mobile' => $agent->isMobile()
            ],
            'agent' => [
                'name' => $agent->getUserAgent(),
                'browser' => $browser,
                'browser_version' => $agent->version($browser),
            ],
            'languages' => $agent->languages(),
            'geoip' => geoip($request->ip())->toArray(),
            'created_at' => Carbon::now()->toDateTimeString()
        ];

        $parser = new Parser;
        $referer = $parser->parse($referer_url, $model['url']);

        if ($referer->isKnown()) {
            $model['referer']['medium'] = $referer->getMedium();
            $model['referer']['source'] = $referer->getSource();
            $model['referer']['search_term'] = $referer->getSearchTerm();
        }

        return $model;
    }

    public function sqlQuery($sql, $bindings, $time, $connection_name)
    {
        if ($this->excludedTracker()) {
            return false;
        }

        $model = $this->indexSqlQueryDocument($sql, $bindings, $time, $connection_name);
        $type = 'sql_queries';

        if (Config::get('tracker.queue', false)) {
            return $this->indexQueueSqlQueryDocument($model, $type);
        }

        return $this->es->indexDocument($model, $type);
    }

    public function indexQueueSqlQueryDocument($model, $type)
    {
        dispatch((new TrackerIndexQueuedModels($model, $type))
                ->onQueue($this->syncWithTrackerUsingQueue())
                ->onConnection($this->syncWithTrackerUsing()));

        return true;
    }

    public function indexSqlQueryDocument($sql, $bindings, $time, $connection_name)
    {
        $sql_query = htmlentities($sql);
        $database_name = DB::connection($connection_name)->getDatabaseName();

        foreach ($bindings as $key => $binding) {
            $bindings[$key] = $this->isBinary($binding) ? base64_encode($binding) : $binding;
        }

        $cookie = $this->cookieTracker();
        $log = Cache::tags(['tracker.cookie'])->get($cookie);

        $model = [
            'id' => Uuid::generate(1, '02:42:ac:14:00:03')->string,
            'log_id' => $log['id'],
            'user_id' => $log['user_id'],
            'cookie_id' => $log['cookie_id'],
            'sha1' => Uuid::generate(5, $sql_query, Uuid::NS_DNS)->string,
            'statement' => $sql_query,
            'bindings' => json_encode($bindings),
            'time' => $time,
            'connection' => $database_name,
            'created_at' => Carbon::now()->toDateTimeString()
        ];

        return $model;
    }

    public function trackError($exception)
    {
        if ($this->excludedTracker()) {
            return false;
        }

        $model = $this->indexErrorDocument($exception);
        $type = 'errors';

        if (Config::get('tracker.queue', false)) {
            return $this->indexQueueErrorDocument($model, $type);
        }

        return $this->es->indexDocument($model, $type);
    }

    public function indexQueueErrorDocument($model, $type)
    {
        dispatch((new TrackerIndexQueuedModels($model, $type))
                ->onQueue($this->syncWithTrackerUsingQueue())
                ->onConnection($this->syncWithTrackerUsing()));

        return true;
    }

    public function indexErrorDocument($exception)
    {
        $cookie = $this->cookieTracker();
        $log = Cache::tags(['tracker.cookie'])->get($cookie);

        $model = [
            'id' => Uuid::generate(1, '02:42:ac:14:00:03')->string,
            'log_id' => $log['id'],
            'user_id' => $log['user_id'],
            'cookie_id' => $log['cookie_id'],
            'code' => $this->trackerStatusCode($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'created_at' => Carbon::now()->toDateTimeString()
        ];

        return $model;
    }

    public function getTrackerDisabled($type = true): bool
    {
        if (App::runningInConsole() || (new Agent())->isRobot()) {
            return true;
        }

        $disabled_type = Config::get('tracker.disabled', false);

        if (! is_array($disabled_type)) {
            return $type === $disabled_type;
        } else {
            return in_array($type, $disabled_type);
        }
    }

    public function excludedTracker(): bool
    {
        $request = $this->request;

        return $this->excludedRoutes($this->routeName($request)) || $this->excludedPaths($request->path());
    }

    public function excludedRoutes($route): bool
    {
        // $route = Route::currentRouteName();
        $exclude_routes = Config::get('tracker.excludes.routes');

        if (is_array($exclude_routes)) {
            foreach ($exclude_routes as $exclude_route) {
                if (str_is($exclude_route, $route)) {
                    return true;
                }
            }
            return false;
        } else {
            return str_is($exclude_routes, $route);
        }
    }

    public function excludedPaths($path): bool
    {
        // $path = $this->request->path();
        $exclude_path = Config::get('tracker.excludes.paths');

        if (is_array($exclude_path)) {
            foreach ($exclude_path as $exclude_path) {
                if (str_is($exclude_path, $path)) {
                    return true;
                }
            }
            return false;
        } else {
            return str_is($exclude_path, $path);
        }
    }

    public function cookieTracker()
    {
        if (! Cookie::has(Config::get('tracker.cookie'))) {
            $cookie = Uuid::generate(4)->string;
            Cookie::queue(Cookie::forever(Config::get('tracker.cookie'), $cookie));
        } else {
            $cookie = Cookie::get(Config::get('tracker.cookie'));
        }

        return $cookie;
    }

    public function syncWithTrackerUsingQueue()
    {
        return Config::get('tracker.queue.queue');
    }

    public function syncWithTrackerUsing()
    {
        return Config::get('tracker.queue.connection', Config::get('queue.default'));
    }

    public function isBinary($str)
    {
        return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
    }

    public function domain($url)
    {
        $host = @parse_url($url, PHP_URL_HOST);

        if (! $host) {
            $host = $url;
        }

        if (substr($host, 0, 4) === "www.") {
            $host = substr($host, 4);
        }

        return $host;
    }

    public function getDeviceKind($agent)
    {
        if ($agent->isTablet()) {
            return 'Tablet';
        } elseif ($agent->isPhone()) {
            return 'Phone';
        } elseif ($agent->isComputer()) {
            return 'Computer';
        } else {
            return 'Unavailable';
        }
    }

    public function trackerStatusCode($exception)
    {
        if (method_exists($exception, 'getCode') && $code = $exception->getCode()) {
            return $code;
        }

        if (method_exists($exception, 'getStatusCode') && $code = $exception->getStatusCode()) {
            return $code;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function routeName($request)
    {
        return $request->route() ? $request->route()->getName() : null;
    }
}
