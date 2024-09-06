<?php

/*
 * This file is part of the GraphAware Bolt package.
 *
 * (c) Graph Aware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PTS\Bolt;

use GraphAware\Common\Connection\BaseConfiguration;
use GraphAware\Common\Driver\ConfigInterface;
use GraphAware\Common\GraphDatabaseInterface;

/**
 * @deprecated
 * @package PTS\Bolt
 */
class GraphDatabase implements GraphDatabaseInterface
{
    /**
     * @param string $uri
     * @param BaseConfiguration|null $config
     * @deprecated You should create Diver directly
     * @return Driver
     */
    public static function driver($uri, ConfigInterface $config = null)
    {
        return new Driver($uri, $config);
    }
}
