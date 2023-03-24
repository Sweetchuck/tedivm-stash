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

use Stash\Interfaces\DriverInterface;
use Stash\Test\Unit\TestBase;
use Stash\Utilities;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
abstract class DriverTestBase extends TestBase
{
    protected array $data = [
        'string' => 'Hello world!',
        'complexString' => "\t\tHello\r\n\r\'\'World!\"\'\\",
        'int' => 4234,
        'negint' => -6534,
        'bigint' => 58635272821786587286382824657568871098287278276543219876543,
        'float' => 1.8358023545,
        'negfloat' => -5.7003249023,
        'false' => false,
        'true' => true,
        'null' => null,
        'array' => [3, 5, 7],
        'hashmap' => ['one' => 1, 'two' => 2],
        'multidemensional array' => [
            [5345],
            [
                3,
                'hello',
                false,
                [
                    'one' => 1,
                    'two' => 2,
                ],
            ],
        ],
        '@node' => 'stuff',
        'test/of/really/long/key/with/lots/of/children/keys' => true,
    ];

    protected int $expiration;

    protected string $driverClass;

    protected int $startTime;

    protected bool $setup = false;

    protected bool $persistence = true;

    public static function tearDownAfterClass() : void
    {
        Utilities::deleteRecursive(Utilities::getBaseDirectory());
    }

    protected function setUp() : void
    {
        if (!$this->setup) {
            $this->startTime = time();
            $this->expiration = $this->startTime + 3600;

            if (!$this->getFreshDriver()) {
                static::markTestSkipped('Driver class unsuited for current environment');
            }

            $this->data['object'] = new \stdClass();
            $this->data['large_string'] = str_repeat('apples', (int) ceil(200000 / 6));
        }
    }

    protected function getFreshDriver(array $options = null): ?DriverInterface
    {
        /** @var \Stash\Interfaces\DriverInterface $driverClass */
        $driverClass = $this->driverClass;

        if ($options === null) {
            $options = $this->getOptions();
        }

        if (!$driverClass::isAvailable()) {
            return null;
        }

        return new $driverClass($options);
    }

    public function testSetOptions(): DriverInterface
    {
        $driverType = $this->driverClass;
        $options = $this->getOptions();
        $driver = new $driverType($options);
        static::assertInstanceOf(
            $driverType,
            $driver,
            "Driver is an instance of $driverType",
        );
        static::assertInstanceOf(
            \Stash\Interfaces\DriverInterface::class,
            $driver,
            'Driver implements the \Stash\Driver\DriverInterface interface',
        );

        return $driver;
    }

    protected function getOptions(): array
    {
        return [];
    }

    /**
     * @depends testSetOptions
     */
    public function testStoreData(DriverInterface $driver): DriverInterface
    {
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            static::assertTrue(
                $driver->storeData($key, $value, $this->expiration),
                "Driver class able to store data type $type",
            );
        }

        return $driver;
    }

    /**
     * @depends testStoreData
     */
    public function testGetData($driver): DriverInterface
    {
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $return = $driver->getData($key);

            static::assertTrue(is_array($return), "getData $type returns array");

            static::assertArrayHasKey('expiration', $return, "getData $type has expiration");
            static::assertLessThanOrEqual(
                $this->expiration,
                $return['expiration'],
                "getData $type returns same expiration that is equal to or sooner than the one passed.",
            );

            if (!is_null($return['expiration'])) {
                static::assertGreaterThan(
                    $this->startTime,
                    $return['expiration'],
                    "getData $type returns expiration that after it's storage time",
                );
            }

            static::assertArrayHasKey('data', $return, "getData $type has data");
            static::assertEquals($value, $return['data'], "getData $type returns same item as stored");
        }

        return $driver;
    }

    /**
     * @depends testGetData
     */
    public function testClear(DriverInterface $driver): DriverInterface
    {
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $keyString = implode('::', $key);

            $return = $driver->getData($key);
            static::assertArrayHasKey('data', $return, "Repopulating $type stores data");
            static::assertEquals($value, $return['data'], "Repopulating $type returns same item as stored");

            static::assertTrue($driver->clear($key), "clear of $keyString returned true");
            static::assertSame([], $driver->getData($key), "clear of $keyString removed data");
        }

        // Repopulate.
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];

            $driver->storeData($key, $value, $this->expiration);
            $return = $driver->getData($key);
            static::assertArrayHasKey('data', $return, "Repopulating $type stores data");
            static::assertEquals($value, $return['data'], "Repopulating $type returns same item as stored");
        }

        static::assertTrue($driver->clear(['base']), 'clear of base node returned true');

        foreach ($this->data as $type => $value) {
            static::assertSame([], $driver->getData(['base', $type]), 'clear of base node removed data');
        }

        // Repopulate
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];

            $driver->storeData($key, $value, $this->expiration);

            $return = $driver->getData($key);
            static::assertArrayHasKey('data', $return, 'Repopulating ' . $type . ' stores data');
            static::assertEquals($value, $return['data'], 'Repopulating ' . $type . ' returns same item as stored');
        }

        static::assertTrue($driver->clear(), 'clear of root node returned true');

        foreach ($this->data as $type => $value) {
            static::assertSame([], $driver->getData(['base', $type]), 'clear of root node removed data');
        }

        return $driver;
    }

    /**
     * @depends testClear
     */
    public function testPurge($driver): DriverInterface
    {
        // We're going to populate this with both stale and fresh data, but we're only checking that the stale data
        // is removed. This is to give drivers the flexibility to introduce their own removal algorithms- our only
        // restriction is that they can't keep things for longer than the developers tell them to, but it's okay to
        // remove things early.

        foreach ($this->data as $type => $value) {
            $driver->storeData(['base', 'fresh', $type], $value, $this->expiration);
        }

        foreach ($this->data as $type => $value) {
            $driver->storeData(['base', 'stale', $type], $value, $this->startTime - 600);
        }

        static::assertTrue($driver->purge());

        foreach ($this->data as $type => $value) {
            static::assertSame([], $driver->getData(['base', 'stale', $type]), 'purge removed stale data');
        }

        return $driver;
    }

    /**
     * @depends testPurge
     */
    public function testDestructor(DriverInterface $driver): void
    {
        $this->expectNotToPerformAssertions();
        unset($driver);
    }

    public function testIsPersistent(): void
    {
        if (!$driver = $this->getFreshDriver()) {
            $this->markTestSkipped('Driver class unsuited for current environment');
        }
        static::assertEquals($this->persistence, $driver->isPersistent());
    }
}
