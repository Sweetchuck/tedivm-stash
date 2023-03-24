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

use Stash\Test\Stubs\PoolGetDriverStub;
use Stash\Driver\Sqlite;
use Stash\Item;
use Stash\Pool;
use Stash\Utilities;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class SqliteAnyTest extends \PHPUnit\Framework\TestCase
{
    protected string $driverClass = Sqlite::class;

    public static function tearDownAfterClass(): void
    {
        Utilities::deleteRecursive(Utilities::getBaseDirectory());
    }

    protected function setUp(): void
    {
        if (!call_user_func([$this->driverClass, 'isAvailable'])) {
            static::markTestSkipped('Driver class unsuited for current environment');
        }
    }

    public function testConstruction()
    {
        $key = ['apple', 'sauce'];

        $driver = new Sqlite([]);
        $pool = new Pool();
        $pool->setDriver($driver);
        $item = $pool->getItem('testKey');
        $item->set($key);
        static::assertTrue($pool->save($item), 'Able to load and store with unconfigured extension.');
    }

    public function testNesting(): void
    {
        $key = ['apple', 'sauce'];

        $driver = new Sqlite(['nesting' => 3]);
        $pool = new Pool();
        $pool->setDriver($driver);
        $item = $pool->getItem('testKey');
        $item->set($key);
        static::assertTrue($pool->save($item), 'Able to load and store with nesting level 3.');
    }
}
