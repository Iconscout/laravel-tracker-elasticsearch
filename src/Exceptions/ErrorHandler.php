<?php

namespace Iconscout\Tracker\Exceptions;

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

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

use Exception;

use Iconscout\Tracker\Tracker;

class ErrorHandler extends ExceptionHandler
{
    protected $previous;

    public function __construct($previous = null)
    {
        $this->previous = $previous;
    }

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        $this->previous? $this->previous->report($exception) : null;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        $tracker = new Tracker;
        $tracker->trackError($this->prepareException($exception));

        return $this->previous ? $this->previous->render($request, $exception) : null;
    }

    public function renderForConsole($output, Exception $exception)
    {
        $this->previous ? $this->previous->renderForConsole($output, $exception) : null;
    }
}
