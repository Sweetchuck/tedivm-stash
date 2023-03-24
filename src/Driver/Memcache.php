<?php

declare(strict_types = 1);

/**
 * @file
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Driver;

use Stash;
use Stash\Exception\RuntimeException;
use Stash\Driver\Sub\Memcache as SubMemcache;
use Stash\Driver\Sub\Memcached as SubMemcached;
use Stash\Utilities;

/**
 * Memcache is a wrapper around the popular memcache server. Memcache supports both memcache php
 * extensions and allows access to all of their options as well as all Stash features (including hierarchical caching).
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Memcache extends AbstractDriver
{

    /**
     * {@inheritdoc}
     */
    public static function isAvailable(): bool
    {
        return SubMemcache::isAvailable() || SubMemcached::isAvailable();
    }

    /**
     * Memcache subdriver used by this class.
     */
    protected SubMemcache|SubMemcached $memcache;

    /**
     * Cache of calculated keys.
     */
    protected array $keyCache = [];

    /**
     * Timestamp of last time the key cache was updated.
     */
    protected float $keyCacheTime = 0;

    /**
     * Limit in seconds.
     */
    protected int $keyCacheTimeLimit = 1;

    /**
     * {@inheritdoc}
     */
    public function isPersistent(): bool
    {
        return true;
    }

    public function getDefaultOptions(): array
    {
        $options = parent::getDefaultOptions();

        return $options + [
            'keycache_limit' => 1,
        ];
    }

    /**
     *
     * * servers - An array of servers, with each server represented by its own array (array(host, port, [weight])). If
     * not passed the default is array('127.0.0.1', 11211).
     *
     * * extension - Which php extension to use, either 'memcache' or 'memcache'. Defaults to memcache with memcache
     * as a fallback.
     *
     * * Options can be passed to the "memcache" driver by adding them to the options array. The memcache extension
     * defined options using constants, ie Memcached::OPT_*. By passing in the * portion ('compression' for
     * Memcached::OPT_COMPRESSION) and its respective option. Please see the php manual for the specific options
     * (http://us2.php.net/manual/en/memcache.constants.php)
     *
     * @throws RuntimeException
     */
    protected function setOptions(array $options = []): static
    {
        $options += $this->getDefaultOptions();

        if (!isset($options['servers'])) {
            $options['servers'] = [
                [
                    '127.0.0.1',
                    11211,
                ],
            ];
        }
        $servers = $this->normalizeServerConfig($options['servers']);

        if (!isset($options['extension'])) {
            $options['extension'] = 'any';
        }

        $this->keyCacheTimeLimit = (int) $options['keycache_limit'];

        $extension = strtolower($options['extension']);

        if (class_exists('Memcached', false) && $extension != 'memcache') {
            $this->memcache = new SubMemcached($servers, $options);
        } elseif (class_exists('Memcache', false) && $extension != 'memcached') {
            $this->memcache = new SubMemcache($servers);
        } else {
            throw new RuntimeException('No memcache extension available.');
        }

        return $this;
    }

    protected function normalizeServerConfig(array $servers): array
    {
        if (is_scalar($servers[0])) {
            $servers = [$servers];
        }

        $normalizedServers = [];
        foreach ($servers as $server) {
            $host = '127.0.0.1';
            if (isset($server['host'])) {
                $host = $server['host'];
            } elseif (isset($server[0])) {
                $host = $server[0];
            }

            $port = '11211';
            if (isset($server['port'])) {
                $port = $server['port'];
            } elseif (isset($server[1])) {
                $port = $server[1];
            }

            $weight = 1;
            if (isset($server['weight'])) {
                $weight = $server['weight'];
            } elseif (isset($server[2])) {
                $weight = $server[2];
            }

            $normalizedServers[] = [
                $host,
                $port,
                $weight,
            ];
        }

        return $normalizedServers;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(array $key): array
    {
        return $this->memcache->get($this->makeKeyString($key)) ?: [];
    }

    /**
     * {@inheritdoc}
     */
    public function storeData(array $key, mixed $data, int $expiration): bool
    {
        return $this->memcache->set($this->makeKeyString($key), $data, $expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(?array $key = null): bool
    {
        $this->keyCache = [];
        if (is_null($key)) {
            $this->memcache->flush();
        } else {
            $keyString = $this->makeKeyString($key, true);
            $this->memcache->inc($keyString);
            $this->keyCache = [];
            $this->makeKeyString($key);
        }
        $this->keyCache = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function purge(): bool
    {
        return true;
    }

    /**
     * Turns a key array into a key string. This includes running the indexing functions used to manage the memcached
     * hierarchical storage.
     *
     * When requested the actual path, rather than a normalized value, is returned.
     *
     * @param string[] $key
     */
    protected function makeKeyString(array $key, bool $path = false): string
    {
        $key = Utilities::normalizeKeys($key);

        $keyString = ':cache:::';
        $pathKey = ':pathdb::';
        $time = microtime(true);
        if (($time - $this->keyCacheTime) >= $this->keyCacheTimeLimit) {
            $this->keyCacheTime = $time;
            $this->keyCache = [];
        }

        foreach ($key as $name) {
            //a. cache:::name
            //b. cache:::name0:::sub
            $keyString .= $name;

            //a. :pathdb::cache:::name
            //b. :pathdb::cache:::name0:::sub
            $pathKey = ':pathdb::' . $keyString;
            $pathKey = md5($pathKey);

            if (isset($this->keyCache[$pathKey])) {
                $index = $this->keyCache[$pathKey];
            } else {
                $index = $this->memcache->cas($pathKey, 0);
                $this->keyCache[$pathKey] = $index;
            }

            //a. cache:::name0:::
            //b. cache:::name0:::sub1:::
            $keyString .= '_' . $index . ':::';
        }

        return $path ? $pathKey : md5($keyString);
    }
}
