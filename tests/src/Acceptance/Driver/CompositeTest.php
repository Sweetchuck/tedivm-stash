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

use Stash\Driver\FileSystem;
use Stash\Driver\Composite;
use Stash\Driver\Ephemeral;
use Stash\Exception\InvalidArgumentException;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\Driver\Composite
 */
class CompositeTest extends DriverTestBase
{
    protected string $driverClass = Composite::class;

    protected array $subDrivers = [];

    protected function getOptions(): array
    {
        $options = [
            'drivers' => [
                new Ephemeral(),
                new Ephemeral(),
                new Ephemeral(),
            ],
        ];
        $this->subDrivers = $options['drivers'];

        return $options;
    }

    public function testStaggeredStore(): void
    {
        $driver = $this->getFreshDriver();
        $a = $this->subDrivers[0];
        $b = $this->subDrivers[1];
        $c = $this->subDrivers[2];

        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            static::assertTrue(
                $driver->storeData($key, $value, $this->expiration),
                "Driver class able to store data type $type",
            );
        }

        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $return = $c->getData($key);

            static::assertTrue(is_array($return), 'getData ' . $type . ' returns array');

            static::assertArrayHasKey('expiration', $return, 'getData ' . $type . ' has expiration');
            static::assertLessThanOrEqual(
                $this->expiration,
                $return['expiration'],
                "getData $type returns same expiration that is equal to or sooner than the one passed.",
            );

            static::assertGreaterThan(
                $this->startTime,
                $return['expiration'],
                "getData $type returns expiration that after it's storage time",
            );

            static::assertArrayHasKey('data', $return, "getData $type has data");
            static::assertEquals($value, $return['data'], "getData $type returns same item as stored");
        }
    }

    public function testStaggeredGet(): void
    {
        $driver = $this->getFreshDriver();
        $a = $this->subDrivers[0];
        $b = $this->subDrivers[1];
        $c = $this->subDrivers[2];

        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            static::assertTrue(
                $c->storeData($key, $value, $this->expiration),
                "Driver class able to store data type $type",
            );
        }

        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $return = $driver->getData($key);

            static::assertTrue(is_array($return), 'getData ' . $type . ' returns array');

            static::assertArrayHasKey('expiration', $return, 'getData ' . $type . ' has expiration');
            static::assertLessThanOrEqual(
                $this->expiration,
                $return['expiration'],
                "getData $type returns same expiration that is equal to or sooner than the one passed."
            );

            static::assertGreaterThan(
                $this->startTime,
                $return['expiration'],
                "getData $type returns expiration that after it's storage time",
            );

            static::assertArrayHasKey('data', $return, 'getData ' . $type . ' has data');
            static::assertEquals($value, $return['data'], 'getData ' . $type . ' returns same item as stored');
        }
    }

    public function testIsPersistent(): void
    {
        $fileDriver = new FileSystem();
        $ephemeralDriver = new Ephemeral();

        $drivers = [$fileDriver, $ephemeralDriver];
        $driver = new Composite(['drivers' => $drivers]);
        static::assertTrue($driver->isPersistent());

        $drivers = [$ephemeralDriver, $fileDriver];
        $driver = new Composite(['drivers' => $drivers]);
        static::assertTrue($driver->isPersistent());

        $drivers = [$fileDriver, $fileDriver];
        $driver = new Composite(['drivers' => $drivers]);
        static::assertTrue($driver->isPersistent());

        $drivers = [$ephemeralDriver, $ephemeralDriver];
        $driver = new Composite(['drivers' => $drivers]);
        static::assertFalse($driver->isPersistent());
    }

    public function testWithoutDriversException(): void
    {
        $this->expectException(\RuntimeException::class);
        new Composite(['drivers' => null]);
    }

    public function testWithFakeDriversException(): void
    {
        $this->expectException(\RuntimeException::class);
        new Composite(['drivers' => ['fakedriver']]);
    }

    public function testWithBadDriverArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Composite(['drivers' => 'fakedriver']);
    }
}
