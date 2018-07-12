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

namespace Iconscout\Tracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use Iconscout\Tracker\Drivers\ElasticSearch;

class TrackerIndexQueuedModels implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var
     */
    private $model;

    /**
     * @var
     */
    private $type;

    /**
     * Create a new job instance.
     *
     * @param array $model
     * @param string $type
     *
     * @return void
     */
    public function __construct($model, $type)
    {
        $this->model = $model;
        $this->type = $type;
    }

    /**
     * Handle the job.
     *
     * @return void
     */
    public function handle()
    {
        $es = new ElasticSearch;
        $es->indexDocument($this->model, $this->type);
    }
}
