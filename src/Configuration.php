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

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Configuration extends BaseConfiguration
{
    const DEFAULT_BOLT_PORT = 7687;
    const TLSMODE_REQUIRED = 'REQUIRED';
    const TLSMODE_REQUIRED_NO_VALIDATION = 'REQUIRED_NO_VALIDATION';
    const TLSMODE_REJECTED = 'REJECTED';

    
    /**
     * Create a new configuration with default values.
     *
     * @return self
     */
    public static function create()
    {
        return new self([
            'tls_mode' => self::TLSMODE_REJECTED,
            'forced_bolt_version' => 0,
            'host' => 'localhost',
            'port' => self::DEFAULT_BOLT_PORT,
            'scheme' => 'bolt',
            'database' => '',
            'username' => 'null',
            'password' => 'null',
            'bind_to_interface' => 'null',
            'timeout' => 5,
            'bookmarks' => null,
            'credentials' => ['null', 'null'],
        ]);
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return Configuration
     */
    public function withCredentials($username, $password)
    {
        if (null === $username && null === $password) {
            // No change if credentials or null
            return $this;
        }

        return $this->setValue('username', $username)
            ->setValue('password', $password)
            ->setValue('credentials', [$username, $password]);
    }

    /**
     * @param string $interface
     *
     * @return Configuration
     */
    public function bindToInterface($interface)
    {
        return $this->setValue('bind_to_interface', $interface);
    }

    /**
     * @param int $timeout
     *
     * @return Configuration
     */
    public function withTimeout($timeout)
    {
        return $this->setValue('timeout', $timeout);
    }

    /**
     * @param int $version
     *
     * @return Configuration
     */
    public function withBoltVersion($version)
    {
        return $this->setValue('forced_bolt_version', $version);
    }

    /**
     * @param $mode
     *
     * @return Configuration
     */
    public function withTLSMode($mode)
    {
        return $this->setValue('tls_mode', $mode);
    }


    /**
     * @param $scheme
     *
     * @return Configuration
     */
    public function withScheme($scheme)
    {
        return $this->setValue('scheme', $scheme);
    }
    
     /**
     * @param $host
     *
     * @return Configuration
     */
    public function withHost($host)
    {
        return $this->setValue('host', $host);
    }

     /**
     * @param $port
     *
     * @return Configuration
     */
    public function withPort($port)
    {
        return $this->setValue('port', $port);
    }

     /**
     * @param $database
     *
     * @return Configuration
     */
    public function withDatabase($database)
    {
        return $this->setValue('database', $database);
    }

    /**
     * @param array $bookmarks
     *
     * @return Configuration
     */
    public function withBookmarks(array $bookmarks)
    {
        return $this->setValue('bookmarks', $bookmarks);
    }


    /**
     * @param $uri
     *
     * @return Configuration
     */
    public function withUri($uri)
    {
        $parsedUri = parse_url($uri);
        $newConfig = $this;
        if (isset($parsedUri['scheme'])) {
            $newConfig = $newConfig->withScheme($parsedUri['scheme']);
        }
        if (isset($parsedUri['user'])) {
            if (isset($parsedUri['pass'])) {
                $newConfig = $newConfig->withCredentials($parsedUri['user'], $parsedUri['pass']);
            } else {
                $newConfig = $newConfig->withCredentials($parsedUri['user'], null);
            }
        }
        if (isset($parsedUri['host'])) {
            $newConfig = $newConfig->withHost($parsedUri['host']);
        }
        if (isset($parsedUri['port'])) {
            $newConfig = $newConfig->withPort($parsedUri['port']);
        }
        if (isset($parsedUri['path'])) {
            $newConfig = $newConfig->withDatabase(str_replace('/', '', $parsedUri['path']));
        }
        if (isset($parsedUri['query'])) {
            parse_str($parsedUri['query'], $query);
            if (isset($query['tls']) && $query['tls'] == 'true') {
                if (isset($query['validate_tls']) && $query['validate_tls'] == 'false') {
                    $newConfig = $newConfig->withTLSMode(self::TLSMODE_REQUIRED_NO_VALIDATION);
                } else {
                    $newConfig = $newConfig->withTLSMode(self::TLSMODE_REQUIRED);
                }
            }
        }
        return $newConfig;
    }
}
