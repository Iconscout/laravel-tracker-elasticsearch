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
use Elasticsearch\ClientBuilder;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Iconscout\Tracker\Drivers\ElasticSearch;

class Tracker
{
    /**
     * @var string
     */
    protected $es;

    /**
     * ElasticSearch constructor.
     */
    public function __construct()
    {
        $this->es = new ElasticSearch;
    }

    public function logSqlQuery($sql, $bindings, $time, $connection_name)
    {
        $sql_query = htmlentities($sql);
        $database_name = DB::connection($connection_name)->getDatabaseName();

        foreach ($bindings as $key => $binding) {
            $bindings[$key] = $this->isBinary($binding) ? base64_encode($binding) : $binding;
        }

        $cookie = $this->cookieTracker();
        $log = Cache::tags('tracker')->get($cookie);

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

        $this->es->indexDocument($model, 'sql_queries');
    }

    public function getTrackerDisabled(): bool
    {
        return Config::get('tracker.disabled.all_queries', false);
    }

    public function getLogTrackerDisabled(): bool
    {
        return Config::get('tracker.disabled.log_queries', false);
    }

    public function getSqlTrackerDisabled(): bool
    {
        return Config::get('tracker.disabled.sql_queries', false);
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

    public function isBinary($str)
    {
        return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
    }
}
