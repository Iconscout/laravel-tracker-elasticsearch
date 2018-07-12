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

namespace Iconscout\Tracker\Drivers;

use Elasticsearch\ClientBuilder;

use Illuminate\Support\Facades\Config;

class ElasticSearch
{
    /**
     * @var string
     */
    protected $client;

    /**
     * @var string
     */
    protected $index;

    /**
     * ElasticSearch constructor.
     */
    public function __construct()
    {
        $this->client = ClientBuilder::create()->setHosts(Config::get('tracker.drivers.elastic.client.hosts', ['localhost:9200']))->build();
        $this->index = Config::get('tracker.drivers.elastic.index', 'laravel_tracker');
    }

    public function indexDocument($model, $type)
    {
        $params = [
            'index' => $this->index,
            'type' => $type,
            'id' => $model['id'],
            'body' => $model
        ];

        return $this->client->index($params);
    }

    public function createIndex()
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 0
                ]
            ]
        ];

        return $this->client->indices()->create($params);
    }

    public function updateAliases()
    {
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $this->index,
                        'alias' => $this->index.'_write'
                    ]
                ]
            ]
        ];

        return $this->client->indices()->updateAliases($params);
    }

    public function deleteIndex()
    {
        $deleteParams = [
            'index' => $this->index
        ];

        return $this->client->indices()->delete($deleteParams);
    }

    public function existsIndex()
    {
        $params = [
            'index' => $this->index
        ];

        return $this->client->indices()->exists($params);
    }

    public function putMapping($type = null)
    {
        if (empty($type)) {
            return $type;
        }

        $params = [
            'index' => $this->index,
            'type' => $type,
            'body' => [
                $type => [
                    '_source' => [
                        'enabled' => true
                    ],
                    'properties' => [
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss'
                        ]
                    ]
                ]
            ]
        ];

        return $this->client->indices()->putMapping($params);
    }
}
