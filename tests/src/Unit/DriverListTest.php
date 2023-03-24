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
use Stash\Driver\Ephemeral;
use Stash\DriverList;
use Stash\Test\Helper\DriverUnavailableStub;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\DriverList
 */
class DriverListTest extends TestCase
{
    public function testGetAvailableDrivers(): void
    {
        $drivers = DriverList::getAvailableDrivers();
        static::assertArrayHasKey('FileSystem', $drivers, 'getDrivers returns FileSystem driver');
        static::assertArrayNotHasKey('Array', $drivers, "getDrivers doesn't return Array driver");

        DriverList::registerDriver('TestUnavailable_getAvailable', DriverUnavailableStub::class);
        $drivers = DriverList::getAvailableDrivers();
        static::assertArrayNotHasKey(
            'TestUnavailable_getAvailable',
            $drivers,
            "getAllDrivers doesn't return TestBroken driver",
        );
    }

    public function testGetAllDrivers(): void
    {
        DriverList::registerDriver('TestBroken_getAll', 'stdClass');
        $drivers = DriverList::getAllDrivers();
        static::assertArrayNotHasKey(
            'TestBroken_getAll',
            $drivers,
            "getAllDrivers doesn't return TestBroken driver",
        );

        DriverList::registerDriver('TestUnavailable_getAll', DriverUnavailableStub::class);
        $drivers = DriverList::getAllDrivers();
        static::assertArrayHasKey(
            'TestUnavailable_getAll',
            $drivers,
            "getAllDrivers doesn't return TestBroken driver",
        );
    }

    public function testRegisterDriver(): void
    {
        DriverList::registerDriver('Array', Ephemeral::class);

        $drivers = DriverList::getAvailableDrivers();
        static::assertArrayHasKey('Array', $drivers, 'getDrivers returns Array driver');
    }

    public function testGetDriverClass(): void
    {
        static::assertSame(
            Ephemeral::class,
            DriverList::getDriverClass('Array'),
            'getDriverClass returns proper classname for Array driver',
        );

        static::assertFalse(
            DriverList::getDriverClass('FakeName'),
            'getDriverClass returns false for nonexistent class.',
        );
    }
}
