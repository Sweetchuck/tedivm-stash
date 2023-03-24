<?php

declare(strict_types = 1);

/**
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Test\Unit;

use Stash\Interfaces\DriverInterface;
use Stash\Interfaces\ItemInterface;
use Stash\Item;
use Stash\InvalidationMethod;
use Stash\Test\Helper\DriverCallCheckStub;
use Stash\Utilities;
use Stash\Driver\Ephemeral;
use Stash\Test\Helper\PoolGetDriverStub;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @todo find out why this has to be abstract to work (see https://github.com/tedivm/Stash/pull/10)
 */
abstract class ItemTestBase extends TestBase
{
    protected array $data = [
        'string' => 'Hello world!',
        'complexString' => "\t\t\t\tHello\r\n\rWorld!",
        'int' => 4234,
        'negint' => -6534,
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
    ];

    protected int $expiration;

    protected int $startTime;
    protected bool $setup = false;

    protected ?DriverInterface $driver = null;

    protected string $itemClass = Item::class;

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass(): void
    {
        Utilities::deleteRecursive(Utilities::getBaseDirectory());
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        if (!$this->setup) {
            $this->startTime = time();
            $this->expiration = $this->startTime + 3600;
            $this->data['object'] = new \stdClass();
        }
    }

    /**
     * This just makes it slightly easier to extend AbstractCacheTest to
     * other Item types.
     */
    protected function getItem(): ItemInterface
    {
        return new $this->itemClass();
    }

    /**
     * @todo Rename this method.
     */
    public function testConstruct(array $key = []): ItemInterface
    {
        if (!isset($this->driver)) {
            $this->driver = new Ephemeral([]);
        }

        $item = $this->getItem();
        static::assertInstanceOf(Item::class, $item, 'Test object is an instance of Stash');

        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($this->driver);
        $item->setPool($poolStub);

        $item->setKey($key);

        return $item;
    }

    public function testSetupKey(): void
    {
        $keyString = 'this/is/the/key';
        $keyArray = ['this', 'is', 'the', 'key'];

        $stashArray = $this->testConstruct($keyArray);
        static::assertAttributeInternalType('string', 'keyString', $stashArray, 'Argument based keys setup keyString');
        static::assertAttributeInternalType('array', 'key', $stashArray, 'Array based keys setup key');

        $returnedKey = $stashArray->getKey();
        static::assertEquals($keyString, $returnedKey, 'getKey returns properly normalized key from array argument.');
    }

    public function testSet(): void
    {
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $stash = $this->testConstruct($key);
            static::assertAttributeInternalType('string', 'keyString', $stash, 'Argument based keys setup keystring');
            static::assertAttributeInternalType('array', 'key', $stash, 'Argument based keys setup key');

            static::assertTrue($stash->set($value)->save(), "Driver class able to store data type $type");
        }

        $item = $this->getItem();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver(new Ephemeral([]));
        $item->setPool($poolStub);
        static::assertTrue($item->getKey() === '', 'Key is still empty.');
    }

    /**
     * @depends testSet
     */
    public function testGet(): void
    {
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $stash = $this->testConstruct($key);
            $stash->set($value)->save();

            // New object, but same backend.
            $stash = $this->testConstruct($key);
            $data = $stash->get();
            static::assertEquals($value, $data, "getData $type returns same item as stored");
        }

        if (!isset($this->driver)) {
            $this->driver = new Ephemeral();
        }

        $item = $this->getItem();

        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver(new Ephemeral());
        $item->setPool($poolStub);

        static::assertEquals(null, $item->get(), 'Item without key returns null for get.');
    }

    public function testLock(): void
    {
        $item = $this->getItem();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver(new Ephemeral());
        $item->setPool($poolStub);
        static::assertFalse($item->lock(), 'Item without key returns false for lock.');
    }

    public function testInvalidation(): void
    {
        $key = ['path', 'to', 'item'];
        $oldValue = 'oldValue';
        $newValue = 'newValue';

        $runningStash = $this->testConstruct($key);
        $runningStash->set($oldValue)->expiresAfter(-300)->save();

        // Test without stampede.
        $controlStash = $this->testConstruct($key);
        $controlStash->setInvalidationMethod(InvalidationMethod::Value, $newValue);
        $return = $controlStash->get();
        static::assertNull($return, 'NULL is returned when isHit is false');
        static::assertFalse($controlStash->isHit());
        unset($controlStash);

        // Enable stampede control.
        $runningStash->lock();
        static::assertAttributeEquals(true, 'stampedeRunning', $runningStash, 'Stampede flag is set.');

        // Old.
        $oldStash = $this->testConstruct($key);
        $oldStash->setInvalidationMethod(InvalidationMethod::Old);
        $return = $oldStash->get();
        static::assertEquals($oldValue, $return, 'Old value is returned');
        static::assertFalse($oldStash->isMiss());
        unset($oldStash);

        // Value.
        $valueStash = $this->testConstruct($key);
        $valueStash->setInvalidationMethod(InvalidationMethod::Value, $newValue);
        $return = $valueStash->get();
        static::assertEquals($newValue, $return, 'New value is returned');
        static::assertFalse($valueStash->isMiss());
        unset($valueStash);

        // Sleep.
        $sleepStash = $this->testConstruct($key);
        $sleepStash->setInvalidationMethod(InvalidationMethod::Sleep, 250, 2);
        $start = microtime(true);
        $return = $sleepStash->get();
        $end = microtime(true);

        static::assertTrue($sleepStash->isMiss());
        $sleepTime = ($end - $start) * 1000;

        static::assertGreaterThan(500, $sleepTime, 'Sleep method sleeps for required time.');
        static::assertLessThan(550, $sleepTime, 'Sleep method does not oversleep.');

        unset($sleepStash);

        // Unknown - if a random, unknown method is passed for invalidation we should rely on the default method.
        $unknownStash = $this->testConstruct($key);

        $return = $unknownStash->get(78);
        static::assertNull($return, 'NULL is returned when isHit is false');
        static::assertFalse($unknownStash->isHit(), 'Cache is marked as miss');
        unset($unknownStash);

        // Test that storing the cache turns off stampede mode.
        $runningStash->set($newValue)->expiresAfter(30)->save();
        static::assertAttributeEquals(false, 'stampedeRunning', $runningStash, 'Stampede flag is off.');
        unset($runningStash);

        // Precompute - test outside limit
        $precomputeStash = $this->testConstruct($key);
        $precomputeStash->setInvalidationMethod(InvalidationMethod::Precompute, 10);
        $return = $precomputeStash->get();
        static::assertFalse($precomputeStash->isMiss(), 'Cache is marked as hit');
        unset($precomputeStash);

        // Precompute - test inside limit.
        $precomputeStash = $this->testConstruct($key);
        $precomputeStash->setInvalidationMethod(InvalidationMethod::Precompute, 35);
        $return = $precomputeStash->get();
        static::assertTrue($precomputeStash->isMiss(), 'Cache is marked as miss');
        unset($precomputeStash);

        // Test Stampede Flag Expiration.
        $key = ['stampede', 'expire'];
        $itemStampede = $this->testConstruct($key);
        $itemStampede->setInvalidationMethod(InvalidationMethod::Value, $newValue);
        $itemStampede->set($oldValue)->expiresAfter(300)->save();
        $itemStampede->lock(-5);
        $itemStampede = $this->testConstruct($key);
        static::assertEquals($oldValue, $itemStampede->get(), 'Expired lock is ignored');
    }

    public function testSetTTLDatetime(): void
    {
        $expiration = new \DateTime('now');
        $expiration->add(new \DateInterval('P1D'));

        $key = ['ttl', 'expiration', 'test'];
        $stash = $this->testConstruct($key);

        $stash
            ->set([1, 2, 3, 'apples'])
            ->setTTL($expiration)
            ->save();
        static::assertLessThanOrEqual($expiration->getTimestamp(), $stash->getExpiration()->getTimestamp());

        $stash = $this->testConstruct($key);
        $data = $stash->get();
        static::assertEquals([1, 2, 3, 'apples'], $data, 'getData returns data stores using a datetime expiration');
        static::assertLessThanOrEqual($expiration->getTimestamp(), $stash->getExpiration()->getTimestamp());
    }

    public function testSetTTLDateInterval(): void
    {
        $interval = new \DateInterval('P1D');
        $expiration = new \DateTime('now');
        $expiration->add($interval);

        $key = ['ttl', 'expiration', 'test'];
        $stash = $this->testConstruct($key);
        $stash
            ->set([1, 2, 3, 'apples'])
            ->setTTL($interval)
            ->save();

        $stash = $this->testConstruct($key);
        $data = $stash->get();
        static::assertEquals([1, 2, 3, 'apples'], $data, 'getData returns data stores using a datetime expiration');
        static::assertLessThanOrEqual($expiration->getTimestamp(), $stash->getExpiration()->getTimestamp());
    }

    public function testSetTTLNulll(): void
    {
        $key = ['ttl', 'expiration', 'test'];
        $stash = $this->testConstruct($key);
        $stash
            ->set([1, 2, 3, 'apples'])
            ->setTTL(null)
            ->save();

        static::assertAttributeEquals(null, 'expiration', $stash);
    }

    public function testExpiresAt(): void
    {
        $expiration = new \DateTime('now');
        $expiration->add(new \DateInterval('P1D'));

        $key = ['base', 'expiration', 'test'];
        $stash = $this->testConstruct($key);

        $stash
            ->set([1, 2, 3, 'apples'])
            ->expiresAt($expiration)
            ->save();

        $stash = $this->testConstruct($key);
        $data = $stash->get();
        static::assertEquals([1, 2, 3, 'apples'], $data, 'getData returns data stores using a datetime expiration');
        static::assertLessThanOrEqual($expiration->getTimestamp(), $stash->getExpiration()->getTimestamp());
    }

    public function testExpiresAfterWithDateTimeInterval(): void
    {
        $key = ['base', 'expiration', 'test'];
        $stash = $this->testConstruct($key);

        $stash
            ->set([1, 2, 3, 'apples'])
            ->expiresAfter(new \DateInterval('P1D'))
            ->save();

        $stash = $this->testConstruct($key);
        $data = $stash->get();
        static::assertEquals([1, 2, 3, 'apples'], $data, 'getData returns data stores using a datetime expiration');
    }

    public function testGetCreation(): void
    {
        $creation = new \DateTime('now');
        // Expire 10 seconds after createdOn.
        $creation->add(new \DateInterval('PT10S'));
        $creationTS = $creation->getTimestamp();

        $key = ['getCreation', 'test'];
        $stash = $this->testConstruct($key);

        static::assertNull($stash->getCreation(), 'no record exists yet, return null');

        $stash->set(['stuff'])->save();

        $stash = $this->testConstruct($key);
        $createdOn = $stash->getCreation();
        static::assertInstanceOf(\DateTime::class, $createdOn, 'getCreation returns DateTime');
        $itemCreationTimestamp = $createdOn->getTimestamp();
        static::assertEquals($creationTS - 10, $itemCreationTimestamp, 'createdOn is 10 seconds before expiration');
    }

    public function testGetExpiration(): void
    {
        $now = new \DateTime();

        $key = ['getExpiration', 'test'];
        $item = $this->testConstruct($key);

        static::assertNull($item->getExpiration());

        $item->setTTL(new \DateInterval('P1D'));

        $expiration = $item->getExpiration();
        $expirationTS = $expiration->getTimestamp();

        static::assertLessThanOrEqual(
            2,
            $now->getTimestamp() - $expirationTS,
            'No record set, return as expired.',
        );

        $item->set(['stuff'])->expiresAt($expiration)->save();

        $item = $this->testConstruct($key);
        $itemExpiration = $item->getExpiration();
        static::assertInstanceOf('\DateTime', $itemExpiration, 'getExpiration returns DateTime');
        $itemExpirationTimestamp = $itemExpiration->getTimestamp();
        static::assertLessThanOrEqual(
            $expirationTS,
            $itemExpirationTimestamp,
            'sometime before explicitly set expiration',
        );
    }

    public function testIsMiss(): void
    {
        $stash = $this->testConstruct(['This', 'Should', 'Fail']);
        static::assertTrue($stash->isMiss(), 'isMiss returns true for missing data');
        $data = $stash->get();
        static::assertNull($data, 'getData returns null for missing data');

        $key = ['isMiss', 'test'];

        $stash = $this->testConstruct($key);
        $stash->set('testString')->save();

        $stash = $this->testConstruct($key);
        static::assertTrue(!$stash->isMiss(), 'isMiss returns false for valid data');
    }

    public function testIsHit(): void
    {
        $stash = $this->testConstruct(['This', 'Should', 'Fail']);
        static::assertFalse($stash->isHit(), 'isHit returns false for missing data');
        $data = $stash->get();
        static::assertNull($data, 'getData returns null for missing data');

        $key = ['isHit', 'test'];

        $stash = $this->testConstruct($key);
        $stash->set('testString')->save();

        $stash = $this->testConstruct($key);
        static::assertTrue($stash->isHit(), 'isHit returns true for valid data');
    }

    public function testClear(): void
    {
        // Repopulate.
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $stash = $this->testConstruct($key);
            $stash->set($value)->save();
            static::assertAttributeInternalType('string', 'keyString', $stash, 'Argument based keys setup keystring');
            static::assertAttributeInternalType('array', 'key', $stash, 'Argument based keys setup key');

            static::assertTrue($stash->set($value)->save(), 'Driver class able to store data type ' . $type);
        }

        foreach ($this->data as $type => $value) {
            $key = ['base', $type];

            // Make sure its actually populated. This has the added bonus of making sure one clear doesn't empty the
            // entire cache.
            $stash = $this->testConstruct($key);
            $data = $stash->get();
            static::assertEquals(
                $value,
                $data,
                "getData $type returns same item as stored after other data is cleared",
            );


            // Run the clear, make sure it says it works.
            $stash = $this->testConstruct($key);
            static::assertTrue($stash->clear(), 'clear returns true');


            // Finally verify that the data has actually been removed.
            $stash = $this->testConstruct($key);
            $data = $stash->get();
            static::assertNull($data, "getData $type returns null once deleted");
            static::assertTrue($stash->isMiss(), 'isMiss returns true for deleted data');
        }

        // Repopulate.
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];
            $stash = $this->testConstruct($key);
            $stash->set($value)->save();
        }

        // Clear.
        $stash = $this->testConstruct();
        static::assertTrue($stash->clear(), 'clear returns true');

        // Make sure all the keys are gone.
        foreach ($this->data as $type => $value) {
            $key = ['base', $type];

            // Finally verify that the data has actually been removed.
            $stash = $this->testConstruct($key);
            $data = $stash->get();
            static::assertNull($data, 'getData ' . $type . ' returns null once deleted');
            static::assertTrue($stash->isMiss(), 'isMiss returns true for deleted data');
        }
    }

    public function testDisable(): void
    {
        $stash = $this->testConstruct(['path', 'to', 'key']);
        $stash->disable();
        static::assertDisabledStash($stash);
    }

    public function testDisableCacheWillNeverCallDriver(): void
    {
        $item = $this->getItem();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($this->getMockedDriver());
        $item->setPool($poolStub);
        $item->setKey(['test', 'key']);
        $item->disable();

        static::assertTrue($item->isDisabled());
        static::assertDisabledStash($item);
    }

    public function testDisableCacheGlobally(): void
    {
        Item::$runtimeDisable = true;
        $testDriver = $this->getMockedDriver();

        $item = $this->getItem();
        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($this->getMockedDriver());
        $item->setPool($poolStub);
        $item->setKey(['test', 'key']);

        static::assertDisabledStash($item);
        static::assertTrue($item->isDisabled());
        static::assertFalse($testDriver->wasCalled(), 'Driver was not called after Item was disabled.');
        Item::$runtimeDisable = false;
    }

    protected function getMockedDriver(): DriverInterface
    {
        return new DriverCallCheckStub();
    }

    protected function assertDisabledStash(ItemInterface $item): void
    {
        static::assertEquals($item, $item->set('true'), 'storeData returns self for disabled cache');
        static::assertNull($item->get(), 'getData returns null for disabled cache');
        static::assertFalse($item->clear(), 'clear returns false for disabled cache');
        static::assertTrue($item->isMiss(), 'isMiss returns true for disabled cache');
        static::assertFalse($item->extend(), 'extend returns false for disabled cache');
        static::assertTrue($item->lock(100), 'lock returns true for disabled cache');
    }
}
