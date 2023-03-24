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

namespace Stash\Test\Acceptance\Driver;

use Stash\Test\Helper\MemcacheSetUp;
use Stash\Test\Helper\PoolGetDriverStub;
use Stash\Driver\Memcache;
use Stash\Item;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class MemcacheTest extends DriverTestBase
{
    use MemcacheSetUp;

    protected string $driverClass = Memcache::class;
    protected string $extension = 'memcache';

    protected array $servers = [];

    protected bool $persistence = true;

    protected function setUp() : void
    {
        $this->servers = $this->getMemcacheServers();

        if (!$this->setup) {
            $this->startTime = time();
            $this->expiration = $this->startTime + 3600;

            if (!call_user_func([$this->driverClass, 'isAvailable'])) {
                static::markTestSkipped('Driver class unsuited for current environment');
            }

            if (!class_exists(ucfirst($this->extension))) {
                static::markTestSkipped("Test requires {$this->extension} extension");
            }

            $server = reset($this->servers);
            if (!($sock = @fsockopen($server[0], $server[1], $errno, $errstr, 1))) {
                static::markTestSkipped('Memcache tests require memcache server');
            }

            fclose($sock);
            $this->data['object'] = new \stdClass();
        }
    }

    protected function getOptions(): array
    {
        return [
            'extension' => $this->extension,
            'servers' => $this->servers,
        ];
    }

    public function testIsAvailable()
    {
        static::assertTrue(\Stash\Driver\Sub\Memcache::isAvailable());
    }

    public function testConstructionOptions()
    {
        $key = ['apple', 'sauce'];

        $options = [
            'servers' => $this->servers,
            'extension' => $this->extension,
        ];

        $driver = new Memcache($options);

        $item = new Item();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($driver);
        $item->setPool($poolStub);
        $item->setKey($key);

        static::assertTrue($item->set($key)->save(), 'Able to load and store memcache driver using multiple servers');
    }
}
