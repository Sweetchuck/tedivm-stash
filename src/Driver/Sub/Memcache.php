<?php

declare(strict_types = 1);

/**
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Driver\Sub;

/**
 * @internal
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Memcache
{
    public static array $defaultServerProperties = [
        'host' => '127.0.0.1',
        'port' => 11211,
        'persistent' => true,
        'weight' => 1,
        'timeout' => 1,
        'retry_interval' => 15,
        'status' => true,
        'failure_callback' => null,
        'timeoutms' => null,
    ];

    /**
     * Returns true if the Memcache extension is installed.
     */
    public static function isAvailable(): bool
    {
        return class_exists('\Memcache', false);
    }

    protected \Memcache $memcached;

    /**
     * Constructs the Memcache subdriver.
     *
     * Takes an array of servers, with array containing another array with the server, port and weight.
     *
     * array(array( '127.0.0.1', 11211, 20), array( '192.168.10.12', 11213, 80), array( '192.168.10.12', 11211, 80));
     *
     * @param array $servers
     */
    public function __construct(array $servers)
    {
        $this->memcached = new \Memcache();
        foreach ($servers as $server) {
            $args = $this->normalizeServerProperties($server);
            $this->memcached->addServer(...$args);
        }
    }

    public function normalizeServerProperties(array $server): array
    {
        $common = array_intersect_key($server, static::$defaultServerProperties);
        if ($common) {
            $common += static::$defaultServerProperties;

            return array_values(array_replace(
                static::$defaultServerProperties,
                $common,
            ));
        }

        $common = [
            array_shift($server) ?: static::$defaultServerProperties['host'],
            array_shift($server) ?: static::$defaultServerProperties['port'],
        ];
        $value1 = array_shift($server);
        $value2 = array_shift($server);
        if (is_null($value1) || is_int($value1)) {
            // Weight provided in place of persistent.
            $common[] = $value2 ?? static::$defaultServerProperties['persistent'];
            $common[] = $value1;
        } else {
            $common[] = $value1;
            $common[] = $value2;
        }

        $common[] = array_shift($server) ?? static::$defaultServerProperties['timeout'];
        $common[] = array_shift($server) ?? static::$defaultServerProperties['retry_interval'];
        $common[] = array_shift($server) ?? static::$defaultServerProperties['status'];
        $common[] = array_shift($server) ?? static::$defaultServerProperties['failure_callback'];
        $common[] = array_shift($server) ?? static::$defaultServerProperties['timeoutms'];

        if (!isset($common[7])) {
            unset(
                $common[7],
                $common[8],
            );
        }

        return $common;
    }

    /**
     * Stores the data in memcached.
     */
    public function set(string $key, $value, ?int $expire = null): bool
    {
        if (isset($expire) && $expire < time()) {
            return true;
        }

        // @todo Make the flag configurable.
        return $this->memcached->set(
            $key,
            [
                'data' => $value,
                'expiration' => $expire,
            ],
            0,
            $expire,
        );
    }

    /**
     * Retrieves the data from memcached.
     */
    public function get(string $key): string|array|false
    {
        return @$this->memcached->get($key);
    }

    /**
     * This function emulates the compare and swap functionality available in the other extension. This allows
     * that functionality to be used when possible and emulated without too much issue, but for obvious reasons
     * this shouldn't be counted on to be exact.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public function cas(string $key, $value)
    {
        if (($return = @$this->memcached->get($key)) !== false) {
            return $return;
        }

        $this->memcached->set($key, $value);

        return $value;
    }

    /**
     * Increments the key and returns the new value.
     */
    public function inc(string $key): int
    {
        $this->cas($key, 0);

        return $this->memcached->increment($key);
    }

    /**
     * Flushes memcached.
     */
    public function flush(): void
    {
        $this->memcached->flush();
    }
}
