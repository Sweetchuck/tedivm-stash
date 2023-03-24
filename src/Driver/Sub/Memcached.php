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

namespace Stash\Driver\Sub;

use Stash\Exception\RuntimeException;

/**
 * @internal
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Memcached
{

    /**
     * Returns true if the Memcached extension is installed.
     */
    public static function isAvailable(): bool
    {
        return class_exists('\Memcached', false);
    }

    protected \Memcached $memcached;

    /**
     * Constructs the Memcached subdriver.
     *
     * Takes an array of servers, with array containing another array with the server, port and weight.
     * array(array( '127.0.0.1', 11211, 20), array( '192.168.10.12', 11213, 80), array( '192.168.10.12', 11211, 80));
     *
     * Takes an array of options which map to the "\Memcached::OPT_" settings
     * (\Memcached::OPT_COMPRESSION => "compression").
     *
     * @throws \Stash\Exception\RuntimeException
     */
    public function __construct(array $servers = [], array $options = [])
    {
        // Build this array here instead of as a class variable since the
        // constants are only defined if the extension exists.
        $memOptions = [
            'COMPRESSION',
            'SERIALIZER',
            'PREFIX_KEY',
            'HASH',
            'DISTRIBUTION',
            'LIBKETAMA_COMPATIBLE',
            'BUFFER_WRITES',
            'BINARY_PROTOCOL',
            'NO_BLOCK',
            'TCP_NODELAY',
            'SOCKET_SEND_SIZE',
            'SOCKET_RECV_SIZE',
            'CONNECT_TIMEOUT',
            'RETRY_TIMEOUT',
            'SEND_TIMEOUT',
            'RECV_TIMEOUT',
            'POLL_TIMEOUT',
            'CACHE_LOOKUPS',
            'SERVER_FAILURE_LIMIT',
            'CLIENT_MODE',
            'REMOVE_FAILED_SERVERS',
        ];

        $this->memcached = new \Memcached();

        $serverList = $this->memcached->getServerList();
        if (empty($serverList)) {
            $this->memcached->addServers($servers);
        }

        foreach ($options as $name => $value) {
            $name = strtoupper($name);

            if (!in_array($name, $memOptions) || !defined('\Memcached::OPT_' . $name)) {
                continue;
            }

            $errorMsgPrefix = "Memcached option $name requires";

            switch ($name) {
                case 'HASH':
                    $value = strtoupper($value);
                    if (!defined('\Memcached::HASH_' . $value)) {
                        throw new RuntimeException("$errorMsgPrefix valid memcache hash option value");
                    }
                    $value = constant('\Memcached::HASH_' . $value);
                    break;

                case 'DISTRIBUTION':
                    $value = strtoupper($value);
                    if (!defined('\Memcached::DISTRIBUTION_' . $value)) {
                        throw new RuntimeException("$errorMsgPrefix valid memcache distribution option value");
                    }
                    $value = constant('\Memcached::DISTRIBUTION_' . $value);
                    break;

                case 'SERIALIZER':
                    $value = strtoupper($value);
                    if (!defined('\Memcached::SERIALIZER_' . $value)) {
                        throw new RuntimeException("$errorMsgPrefix valid memcache serializer option value");
                    }
                    $value = constant('\Memcached::SERIALIZER_' . $value);
                    break;

                case 'SOCKET_SEND_SIZE':
                case 'SOCKET_RECV_SIZE':
                case 'CONNECT_TIMEOUT':
                case 'RETRY_TIMEOUT':
                case 'SEND_TIMEOUT':
                case 'RECV_TIMEOUT':
                case 'POLL_TIMEOUT':
                case 'SERVER_FAILURE_LIMIT':
                    if (!is_numeric($value)) {
                        throw new RuntimeException("$errorMsgPrefix numeric value");
                    }
                    break;

                case 'PREFIX_KEY':
                    if (!is_string($value)) {
                        throw new RuntimeException("$errorMsgPrefix string value");
                    }
                    break;

                case 'COMPRESSION':
                case 'LIBKETAMA_COMPATIBLE':
                case 'BUFFER_WRITES':
                case 'BINARY_PROTOCOL':
                case 'NO_BLOCK':
                case 'TCP_NODELAY':
                case 'CACHE_LOOKUPS':
                case 'REMOVE_FAILED_SERVERS':
                    if (!is_bool($value)) {
                        throw new RuntimeException("$errorMsgPrefix boolean value");
                    }
                    break;
            }

            if (!@$this->memcached->setOption(constant('\Memcached::OPT_' . $name), $value)) {
                throw new RuntimeException("\Memcached::OPT_$name not accepted by memcached extension.");
            }
        }
    }

    /**
     * Stores the data in memcached.
     *
     * @param string $key
     * @param mixed $value
     * @param null|int $expire
     */
    public function set(string $key, $value, ?int $expire = null): bool
    {
        if (isset($expire) && $expire < time()) {
            return true;
        }

        return $this->memcached->set(
            $key,
            [
                'data' => $value,
                'expiration' => $expire,
            ],
            $expire,
        );
    }

    /**
     * Retrieves the data from memcached.
     *
     * @return mixed
     */
    public function get(string $key)
    {
        $value = $this->memcached->get($key);
        if ($value === false && $this->memcached->getResultCode() == \Memcached::RES_NOTFOUND) {
            return false;
        }

        return $value;
    }

    /**
     * This function emulates runs the cas memcache functionlity.
     *
     * @param string $key
     * @param mixed $value

     * @return mixed
     */
    public function cas(string $key, $value)
    {
        $token = 0;
        if (($rValue = $this->memcached->get($key, null, $token)) !== false) {
            return $rValue;
        }

        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            $this->memcached->add($key, $value);
        } else {
            $this->memcached->cas($token, $key, $value);
        }

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
