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

use Stash\Driver\Ephemeral;
use Stash\Exception\InvalidArgumentException;
use Stash\Item;
use Stash\Test\Helper\PoolGetDriverStub;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class EphemeralTest extends DriverTestBase
{
    protected string $driverClass = Ephemeral::class;

    protected bool $persistence = false;

    public function testKeyCollisions1(): void
    {
        $driver = new $this->driverClass();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($driver);

        $item1 = new Item();
        $item1->setPool($poolStub);
        $item1->setKey(['##', '#']);
        $item1->set('X')->save();

        $item2 = new Item();
        $item2->setPool($poolStub);
        $item2->setKey(['#', '##']);
        $item2->set('Y')->save();

        static::assertEquals('X', $item1->get());
    }

    public function testKeyCollisions2(): void
    {
        $driver = new $this->driverClass();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($driver);

        $item1 = new Item();
        $item1->setPool($poolStub);
        $item1->setKey(['#']);
        $item1->set('X');

        $item2 = new Item();
        $item2->setPool($poolStub);
        $item2->setKey([':']);
        $item2->set('Y');

        static::assertEquals('X', $item1->get());
    }

    public function testSettingMaxItemsInvalidArgumentThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        new $this->driverClass([
          'maxItems' => null,
        ]);
    }

    public function testSettingMaxItemsLessThan0Throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new $this->driverClass([
          'maxItems' => -1,
        ]);
    }

    public function testEviction(): void
    {
        /** @var \Stash\Driver\Ephemeral $driver */
        $driver = new $this->driverClass([
          'maxItems' => 1,
        ]);

        $expire = time() + 100;
        $driver->storeData(['fred'], 'tuttle', $expire);
        static::assertArraySubset(
            [
                'data' => 'tuttle',
                'expiration' => $expire,
            ],
            $driver->getData(['fred']),
        );

        $driver->storeData(['foo'], 'bar', $expire);
        static::assertSame([], $driver->getData(['fred']));
        static::assertArraySubset(
            [
                'data' => 'bar',
                'expiration' => $expire,
            ],
            $driver->getData(['foo'])
        );
    }

    public function testNoEvictionWithDefaultOptions(): void
    {
        /** @var \Stash\Driver\Ephemeral $driver */
        $driver = new $this->driverClass();
        $expire = time() + 100;

        for ($i = 1; $i <= 5; ++$i) {
            $driver->storeData(["item$i"], "value$i", $expire);
        }

        for ($i = 1; $i <= 5; ++$i) {
            static::assertArraySubset(
                [
                    'data' => "value$i",
                    'expiration' => $expire,
                ],
                $driver->getData(["item$i"]),
            );
        }
    }
}
