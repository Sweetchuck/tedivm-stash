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

namespace Stash\Test\Unit;

use PHPUnit\Framework\TestCase;
use Stash\Test\Helper\DriverExceptionStub;
use Stash\Test\Helper\PoolGetDriverStub;
use Stash\Pool;
use Stash\Item;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\Pool
 * @covers \Stash\Item
 */
class CacheExceptionTest extends TestCase
{
    public function testSet(): void
    {
        $item = new Item();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver(new DriverExceptionStub());
        $item->setPool($poolStub);
        $item->setKey(['path', 'to', 'store']);

        static::assertFalse($item->isDisabled());
        static::assertFalse($item->set([1, 2, 3])->save());
        static::assertTrue($item->isDisabled(), 'Is disabled after exception is thrown in driver');
    }

    public function testGet(): void
    {
        $item = new Item();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver(new DriverExceptionStub());
        $item->setPool($poolStub);
        $item->setKey(['path', 'to', 'get']);

        static::assertFalse($item->isDisabled());
        static::assertNull($item->get());
        static::assertTrue($item->isDisabled(), 'Is disabled after exception is thrown in driver');
    }

    public function testClear(): void
    {
        $item = new Item();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver(new DriverExceptionStub());
        $item->setPool($poolStub);
        $item->setKey(['path', 'to', 'clear']);

        static::assertFalse($item->isDisabled());
        static::assertFalse($item->clear());
        static::assertTrue($item->isDisabled(), 'Is disabled after exception is thrown in driver');
    }

    public function testPurge(): void
    {
        $pool = new Pool();
        $pool->setDriver(new DriverExceptionStub());

        $item = $pool->getItem('test');
        static::assertFalse($item->isDisabled());
        static::assertFalse($pool->purge());

        $item = $pool->getItem('test');
        static::assertTrue($item->isDisabled(), 'Is disabled after exception is thrown in driver');
        static::assertFalse($pool->purge());
    }

    public function testPoolClear(): void
    {
        $pool = new Pool();
        $pool->setDriver(new DriverExceptionStub());

        $item = $pool->getItem('test');
        static::assertFalse($item->isDisabled());
        static::assertFalse($pool->clear());

        $item = $pool->getItem('test');
        static::assertTrue($item->isDisabled(), 'Is disabled after exception is thrown in driver');
        static::assertFalse($pool->clear());
    }
}
