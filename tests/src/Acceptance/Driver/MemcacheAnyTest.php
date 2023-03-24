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

use PHPUnit\Framework\TestCase;
use Stash\Test\Helper\MemcacheSetUp;
use Stash\Test\Helper\PoolGetDriverStub;
use Stash\Driver\Memcache;
use Stash\Item;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class MemcacheAnyTest extends TestCase
{
    use MemcacheSetUp;

    protected string $driverClass = Memcache::class;

    protected array $servers = [];

    protected function setUp() : void
    {
        if (!call_user_func([$this->driverClass, 'isAvailable'])) {
            static::markTestSkipped("Driver class {$this->driverClass} unsuited for current environment");
        }

        $this->servers = $this->getMemcacheServers();

        $server = reset($this->servers);
        if (!($sock = @fsockopen($server[0], $server[1], $errno, $errstr, 1))) {
            $this->markTestSkipped('Memcache tests require memcache server');
        }

        fclose($sock);
    }

    public function testConstruction(): void
    {
        $key = ['apple', 'sauce'];

        $options = [];
        $options['servers'][] = $this->servers[1];
        $driver = new Memcache($options);

        $item = new Item();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($driver);
        $item->setPool($poolStub);

        $item->setKey($key);
        static::assertTrue($item->set($key)->save(), 'Able to load and store with unconfigured extension.');
    }
}
