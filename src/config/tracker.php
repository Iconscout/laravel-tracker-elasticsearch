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

return [

    /*
    |--------------------------------------------------------------------------
    | Tracker Driver Configurations
    |--------------------------------------------------------------------------
    |
    | Available tracker drivers and respective configurations.
    |
    */

    'elastic' => [
        'client' => [
            'hosts' => [
                env('TRACKER__ELASTIC_HOST', 'localhost:9200')
            ]
        ],
        'index' => env('TRACKER_INDEX', 'laravel_tracker')
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the cookie used to identify a session
    | instance by ID. The name specified here will get used every time a
    | new session cookie is created by the framework for every driver.
    |
    */

    'cookie' => env('TRACKER_SESSION_COOKIE', 'tracker_'.str_slug(env('APP_NAME', 'laravel'), '_').'_session'),
];
