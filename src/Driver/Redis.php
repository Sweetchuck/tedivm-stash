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
use Stash\Utilities;

/**
 * The Redis driver is used for storing data on a Redis system. This class uses
 * the PhpRedis extension to access the Redis server.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Redis extends AbstractDriver
{

    /**
     * {@inheritdoc}
     */
    public static function isAvailable(): bool
    {
        return class_exists('Redis', false);
    }

    /**
     * The Redis drivers.
     */
    protected \Redis|\RedisArray $redis;

    /**
     * The cache of indexed keys.
     */
    protected array $keyCache = [];

    protected array $redisArrayOptionNames = [
        'previous',
        'function',
        'distributor',
        'index',
        'autorehash',
        'pconnect',
        'retry_interval',
        'lazy_connect',
        'connect_timeout',
    ];

    /**
     * The options array should contain an array of servers,
     *
     * The "server" option expects an array of servers, with each server being represented by an associative array. Each
     * redis config must have either a "socket" or a "server" value, and optional "port" and "ttl" values (with the ttl
     * representing server timeout, not cache expiration).
     *
     * The "database" option lets developers specific which specific database to use.
     *
     * The "password" option is used for clusters which require authentication.
     *
     * The "username" option is used for authentication with a non-default user, for clusters which have ACL rules.
     * If "username" isn't set, but "password" is set, the `default` user will be used for authentication.
     */
    protected function setOptions(array $options = []): static
    {
        $options += $this->getDefaultOptions();

        // Normalize Server Options
        if (isset($options['servers'])) {
            $unprocessedServers = (is_array($options['servers']))
                ? $options['servers']
                : [$options['servers']];
            unset($options['servers']);

            $servers = [];
            foreach ($unprocessedServers as $server) {
                $ttl = 0.1;
                if (isset($server['ttl'])) {
                    $ttl = $server['ttl'];
                } elseif (isset($server[2])) {
                    $ttl = $server[2];
                }

                if (isset($server['socket'])) {
                    $servers[] = [
                        'socket' => $server['socket'],
                        'ttl' => $ttl,
                    ];
                } else {
                    $host = '127.0.0.1';
                    if (isset($server['server'])) {
                        $host = $server['server'];
                    } elseif (isset($server[0])) {
                        $host = $server[0];
                    }

                    $port = 6379;
                    if (isset($server['port'])) {
                        $port = $server['port'];
                    } elseif (isset($server[1])) {
                        $port = $server[1];
                    }

                    $servers[] = [
                        'server' => $host,
                        'port' => (int) $port,
                        'ttl' => (int) $ttl,
                    ];
                }
            }
        } else {
            $servers = [
                [
                    'server' => '127.0.0.1',
                    'port' => 6379,
                    'ttl' => 0.1,
                ],
            ];
        }

        // this will have to be revisited to support multiple servers, using
        // the RedisArray object. That object acts as a proxy object, meaning
        // most of the class will be the same even after the changes.

        if (count($servers) == 1) {
            $server = $servers[0];
            $redis = new \Redis();

            if (isset($server['socket']) && $server['socket']) {
                $redis->connect($server['socket']);
            } else {
                $port = $server['port'] ?? 6379;
                $ttl = $server['ttl'] ?? 0.1;
                $redis->connect($server['server'], (int) $port, (int) $ttl);
            }

            // authentication
            if (isset($options['password'])) {
                if (isset($options['username'])) {
                    $redis->auth(
                        [
                            'user' => $options['username'],
                            'pass' => $options['password']
                        ]
                    );
                } else {
                    $redis->auth(['pass' => $options['password']]);
                }
            }

            $this->redis = $redis;
        } else {
            $redisArrayOptions = [];
            foreach ($this->redisArrayOptionNames as $optionName) {
                if (array_key_exists($optionName, $options)) {
                    $redisArrayOptions[$optionName] = $options[$optionName];
                }
            }

            $serverArray = [];
            foreach ($servers as $server) {
                $serverString = $server['server'];
                if (isset($server['port'])) {
                    $serverString .= ':' . $server['port'];
                }

                $serverArray[] = $serverString;
            }

            $redis = new \RedisArray($serverArray, $redisArrayOptions);
        }

        // select database
        if (isset($options['database'])) {
            $redis->select($options['database']);
        }

        $this->redis = $redis;

        return $this;
    }

    /**
     * Properly close the connection.
     */
    public function __destruct()
    {
        if ($this->redis instanceof \Redis) {
            try {
                $this->redis->close();
            } catch (\RedisException $e) {
                // \Redis::close will throw a \RedisException("Redis server went away")
                // exception if we haven't previously been able to connect to
                // Redis or the connection has severed.
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getData(array $key): array
    {
        $entry = $this->redis->get($this->makeKeyString($key));
        if (is_string($entry)) {
            return unserialize($entry);
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function storeData(array $key, mixed $data, int $expiration): bool
    {
        $store = serialize([
            'data' => $data,
            'expiration' => $expiration,
        ]);

        if (!$expiration) {
            return $this->redis->set($this->makeKeyString($key), $store);
        }

        $ttl = $expiration - time();

        // Prevent us from even passing a negative ttl'd item to redis,
        // since it will just round up to zero and cache forever.
        if ($ttl < 1) {
            return true;
        }

        return $this->redis->setex($this->makeKeyString($key), $ttl, $store);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(?array $key = null): bool
    {
        if ($key === null) {
            $this->redis->flushDB();

            return true;
        }

        $keyString = $this->makeKeyString($key, true);
        $keyReal = $this->makeKeyString($key);
        // Increment index for children items.
        $this->redis->incr($keyString);
        // Remove direct item.
        $this->redis->del($keyReal);
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
     * Turns a key array into a key string. This includes running the indexing functions used to manage the Redis
     * hierarchical storage.
     *
     * When requested the actual path, rather than a normalized value, is returned.
     *
     * @param string[] $key
     */
    protected function makeKeyString(array $key, bool $path = false): string
    {
        $key = Utilities::normalizeKeys($key);

        $keyString = 'cache:::';
        $pathKey = ':pathdb::';
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
                $index = $this->redis->get($pathKey);
                $this->keyCache[$pathKey] = $index;
            }

            //a. cache:::name0:::
            //b. cache:::name0:::sub1:::
            $keyString .= '_' . $index . ':::';
        }

        return $path ? $pathKey : md5($keyString);
    }

    /**
     * {@inheritdoc}
     */
    public function isPersistent(): bool
    {
        return true;
    }
}
