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

namespace Stash;

use Stash\Interfaces\ItemInterface;
use Stash\Interfaces\PoolInterface;

/**
 * Stash\Session lets developers use Stash's Pool class to back session storage.
 * By injecting a Pool class into a Session object, and registering that Session
 * with PHP, developers can utilize any of Stash's drivers (including the
 * composite driver) and special features.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Session implements \SessionHandlerInterface
{
    /**
     * The Stash\Pool generates the individual cache items corresponding to each
     * session. Basically all persistence is handled by this object.
     */
    protected \Stash\Interfaces\PoolInterface $pool;

    /**
     * PHP passes a "save_path", which is not really relevant to most session
     * systems. This class uses it as a namespace instead.
     */
    protected string $path = '__empty_save_path';

    /**
     * The name of the current session, used as part of the cache namespace.
     */
    protected string $name = '__empty_session_name';

    /**
     * Some options (such as the ttl of a session) can be set by the developers.
     */
    protected array $options = [];

    /**
     * Registers a Session object with PHP as the session handler. This
     * eliminates some boilerplate code from projects while also helping with
     * the differences in php versions.
     */
    public static function registerHandler(Session $handler): bool
    {
        // This isn't possible to test with the CLI phpunit test
        // @codeCoverageIgnoreStart
        $results = session_set_save_handler(
            [$handler, 'open'],
            [$handler, 'close'],
            [$handler, 'read'],
            [$handler, 'write'],
            [$handler, 'destroy'],
            [$handler, 'gc'],
        );

        if (!$results) {
            return false;
        }

        // The following prevents unexpected effects when using objects as save handlers
        register_shutdown_function('session_write_close');

        return true;
        // @codeCoverageIgnoreEnd
    }

    /**
     * The constructor expects an initialized Pool object. The creation of this
     * object is up to the developer, but it should contain it's own unique
     * drivers or be appropriately namespaced to avoid conflicts with other
     * libraries.
     */
    public function __construct(PoolInterface $pool)
    {
        $this->pool = $pool;
        $this->options['ttl'] = (int) ini_get('session.gc_maxlifetime');
    }

    /**
     * Options can be set using an associative array. The only current option is
     * a "ttl" value, which represents the amount of time (in seconds) that each
     * session should last.
     */
    public function setOptions(array $options = []): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /*
     * The functions below are all implemented according to the
     * SessionHandlerInterface interface.
     */

    /**
     * This function is defined by the SessionHandlerInterface and is for PHP's
     * internal use. It takes the saved session path and turns it into a
     * namespace.
     */
    public function open(string $path, string $name) : bool
    {
        if ($path !== '') {
            $this->path = $path;
        }

        $this->name = $name;

        return true;
    }


    protected function getCache(string $session_id): ItemInterface
    {
        $path = sprintf(
            '%s.%s.%s',
            base64_encode($this->path),
            base64_encode($this->name),
            base64_encode($session_id),
        );

        return $this->pool->getItem($path);
    }

    /**
     * This function is defined by the SessionHandlerInterface and is for PHP's
     * internal use. It reads the session data from the caching system.
     */
    public function read(string $id): string
    {
        $cache = $this->getCache($id);
        $data = $cache->get();

        return $cache->isMiss() ? '' : $data;
    }

    /**
     * This function is defined by the SessionHandlerInterface and is for PHP's
     * internal use. It writes the session data to the caching system.
     */
    public function write($id, $data): bool
    {
        return $this
            ->getCache($id)
            ->set($data)
            ->expiresAfter($this->options['ttl'])
            ->save();
    }

    /**
     * This function is defined by the SessionHandlerInterface and is for PHP's
     * internal use. It currently does nothing important, as there is no need to
     * take special action.
     */
    public function close() : bool
    {
        return true;
    }

    /**
     * This function is defined by the SessionHandlerInterface and is for PHP's
     * internal use. It clears the current session.
     */
    public function destroy(string $id): bool
    {
        return $this
            ->getCache($id)
            ->clear();
    }

    /**
     * This function is defined by the SessionHandlerInterface and is for PHP's
     * internal use. It is called randomly based on the session.gc_divisor,
     * session.gc_probability and session.gc_lifetime settings, which should be
     * set according to the drivers used. Those with built in eviction
     * mechanisms will not need this functionality, while those without it will.
     * It is also possible to disable the built in garbage collection (place
     * gc_probability as zero) and call the "purge" function on the Stash\Pool
     * class directly.
     */
    public function gc(int $max_lifetime): int|false
    {
        return $this->pool->purge() ? 1 : false;
    }
}
