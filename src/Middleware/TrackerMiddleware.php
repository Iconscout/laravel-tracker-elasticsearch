<?php

namespace Iconscout\Tracker\Middleware;

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

use Closure;
use Carbon\Carbon;
use Webpatser\Uuid\Uuid;
// use Jenssegers\Agent\Agent;
// use Snowplow\RefererParser\Parser;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

use Iconscout\Tracker\Tracker;
use Iconscout\Tracker\Drivers\ElasticSearch;

class TrackerMiddleware
{
    /**
     * @var string
     */
    // protected $agent;

    /**
     * @var string
     */
    // protected $parser;

    /**
     * @var string
     */
    protected $es;

    /**
     * ElasticSearch constructor.
     */
    public function __construct()
    {
        // $this->agent = new Agent;
        // $this->parser = new Parser;
        $this->es = new ElasticSearch;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $tracker = new Tracker;

        if ($tracker->getTrackerDisabled() || $tracker->getLogTrackerDisabled()) {
            return $next($request);
        }

        $cookie = $tracker->cookieTracker();

        $model = [
            'id' => Uuid::generate(4)->string,
            'user_id' => Auth::id(),
            'cookie_id' => $cookie
        ];

        Cache::tags(['tracker'])->forever($cookie, $model);

        $response = $next($request);

        $tracker->logQuery($request, $model);

        return $response;
    }

    /*public function log($request, $model)
    {
        $browser = $this->agent->browser();
        $platform = $this->agent->platform();
        $referer_url = $request->headers->get('referer');

        $model = $model + [
            'referer' => [
                'url' => $referer_url,
                'domain' => $this->domain($referer_url),
                'medium' => null,
                'source' => null,
                'search_term' => null
            ],
            'url' => $request->url(),
            'device' => [
                'kind' => $this->agent->device(),
                'model' => $this->getDeviceKind(),
                'platform' => $platform,
                'platform_version' => $this->agent->version($platform),
                'is_mobile' => $this->agent->isMobile()
            ],
            'agent' => [
                'name' => $this->agent->getUserAgent(),
                'browser' => $browser,
                'browser_version' => $this->agent->version($browser),
            ],
            'languages' => $this->agent->languages(),
            'geoip' => geoip($request->ip())->toArray(),
            'created_at' => Carbon::now()->toDateTimeString()
        ];

        $referer = $this->parser->parse($referer_url, $model['url']);

        if ($referer->isKnown()) {
            $model['referer']['medium'] = $referer->getMedium();
            $model['referer']['source'] = $referer->getSource();
            $model['referer']['search_term'] = $referer->getSearchTerm();
        }

        $this->es->indexDocument($model, 'log_queries');
    }

    protected function domain($url)
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

    protected function getDeviceKind()
    {
        if ($this->agent->isTablet()) {
            return 'Tablet';
        } elseif ($this->agent->isPhone()) {
            return 'Phone';
        } elseif ($this->agent->isComputer()) {
            return 'Computer';
        } else {
            return 'Unavailable';
        }
    }*/
}
