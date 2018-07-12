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
use Webpatser\Uuid\Uuid;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Config;

use Iconscout\Tracker\Tracker;

class TrackerCookie
{
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

        if ($tracker->getTrackerDisabled() || ($tracker->getLogTrackerDisabled() && $tracker->getSqlTrackerDisabled())) {
            return $next($request);
        }

        $cookie = $tracker->cookieTracker();

        return $next($request)->withCookie($cookie);
    }
}
