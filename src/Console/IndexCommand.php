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

namespace Iconscout\Tracker\Console;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Config;
use Iconscout\Tracker\Drivers\ElasticSearch;

class IndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:es-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Index all of the model's records into the search index";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(ElasticSearch $es)
    {
        $index = Config::get('tracker.drivers.elastic.index', 'laravel_tracker');

        if ($es->existsIndex() === false) {

            $es->createIndex();
            $this->info("The {$index} index was created!");

            $es->updateAliases();
            $this->info("The {$index}_write alias for the {$index} index was created!");

            $types = [
                'logs',
                'sql_queries',
                'errors'
            ];

            foreach ($types as $type) {
                $es->putMapping($type);
                $this->info("The {$index} | {$type} mapping was updated!");
            }

        } else {
            $this->info("The {$index} index already exist!");
        }
    }
}
