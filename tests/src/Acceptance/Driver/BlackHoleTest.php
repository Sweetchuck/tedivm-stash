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
use Stash\Driver\BlackHole;

/**
 * @author  Benjamin Zikarsky <benjamin.zikarsky@perbility.de>
 *
 * @covers \Stash\Driver\BlackHole
 */
class BlackHoleTest extends TestCase
{
    protected BlackHole $driver;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new BlackHole();
    }

    public function testPurge(): void
    {
        static::assertTrue($this->driver->purge());
    }

    public function testStoreData(): void
    {
        $key = ['test'];
        static::assertTrue($this->driver->storeData($key, 'data', 0));
        static::assertSame([], $this->driver->getData($key));
    }

    public function testGetData()
    {
        static::assertSame([], $this->driver->getData(['test']));
    }

    public function testClear(): void
    {
        static::assertTrue($this->driver->clear());
        static::assertTrue($this->driver->clear(['test']));
    }
}
