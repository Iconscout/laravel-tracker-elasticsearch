<?php

namespace Iconscout\Tracker\Facades;

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

use Illuminate\Support\Facades\Facade;

class Tracker extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'tracker';
    }
}