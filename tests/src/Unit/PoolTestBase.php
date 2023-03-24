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

use ColinODell\PsrTestLogger\TestLogger;
use Stash\Exception\InvalidArgumentException;
use Stash\Interfaces\ItemInterface;
use Stash\InvalidationMethod;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Stash\Test\Helper\DriverExceptionStub;
use Stash\Test\Helper\TestException;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\Pool
 * @covers \Stash\Item
 */
class PoolTestBase extends TestBase
{
    protected array $data = [
        ['test', 'test'],
    ];

    protected array $multiData = [
        'key' => 'value',
        'key1' => 'value1',
        'key2' => 'value2',
        'key3' => 'value3'
    ];

    protected string $poolClass = Pool::class;

    public function testSetDriver(): void
    {
        $driver = new Ephemeral();
        $pool = new $this->poolClass($driver);
        static::assertAttributeEquals($driver, 'driver', $pool);
    }

    public function testSetItemDriver(): void
    {
        $pool = $this->getTestPool();
        $stash = $pool->getItem('test');
        static::assertAttributeInstanceOf(
            Ephemeral::class,
            'driver',
            $stash,
            'set driver is pushed to new stash objects',
        );
    }

    public function testSetItemClass(): void
    {
        $mockItem = $this->createMock(ItemInterface::class);
        $mockClassName = get_class($mockItem);
        $pool = $this->getTestPool();

        try {
            $pool->setItemClass($mockClassName);
        } catch (\Throwable $e) {
            static::fail();
        }

        static::assertAttributeEquals($mockClassName, 'itemClass', $pool);
    }

    public function testSetItemClassFakeClassException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $pool = $this->getTestPool();
        $pool->setItemClass('FakeClassName');
    }

    public function testSetItemClassImproperClassException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $pool = $this->getTestPool();
        $pool->setItemClass('\stdClass');
    }

    public function testGetItem(): void
    {
        $pool = $this->getTestPool();

        $stash = $pool->getItem('base.one');
        static::assertInstanceOf(\Stash\Item::class, $stash, 'getItem returns a Stash\Item object');

        $stash->set($this->data)->save();
        $storedData = $stash->get();
        static::assertSame($this->data, $storedData, 'getItem returns working Stash\Item object');

        $key = $stash->getKey();
        static::assertSame('base.one', $key, 'Pool sets proper Item key.');

        $pool->setNamespace('TestNamespace');
        $item = $pool->getItem('test.item');

        static::assertAttributeEquals('TestNamespace', 'namespace', $item, 'Pool sets Item namespace.');
    }

    public function testSaveItem(): void
    {
        $pool = $this->getTestPool();

        static::assertFalse($pool->hasItem('base.one'), 'Pool->hasItem() returns false for item without stored data.');
        $item = $pool->getItem('base.one');
        static::assertInstanceOf(\Stash\Item::class, $item, 'getItem returns a Stash\Item object');

        $key = $item->getKey();
        static::assertEquals('base.one', $key, 'Pool sets proper Item key.');

        $item->set($this->data);
        static::assertTrue($pool->save($item), 'Pool->save() returns true.');
        $storedData = $item->get();
        static::assertEquals($this->data, $storedData, 'Pool->save() returns proper data on passed Item.');

        $item = $pool->getItem('base.one');
        $storedData = $item->get();
        static::assertEquals($this->data, $storedData, 'Pool->save() returns proper data on new Item instance.');

        static::assertTrue($pool->hasItem('base.one'), 'Pool->hasItem() returns true for item with stored data.');

        $pool->setNamespace('TestNamespace');
        $item = $pool->getItem('test.item');

        static::assertAttributeEquals('TestNamespace', 'namespace', $item, 'Pool sets Item namespace.');
    }


    public function testSaveDeferredItem(): void
    {
        $pool = $this->getTestPool();

        static::assertFalse($pool->hasItem('base.one'), 'Pool->hasItem() returns false for item without stored data.');
        $item = $pool->getItem('base.one');
        static::assertInstanceOf(\Stash\Item::class, $item, 'getItem returns a Stash\Item object');

        $key = $item->getKey();
        static::assertEquals('base.one', $key, 'Pool sets proper Item key.');

        $item->set($this->data);
        static::assertTrue($pool->saveDeferred($item), 'Pool->save() returns true.');
        $storedData = $item->get();
        static::assertEquals($this->data, $storedData, 'Pool->save() returns proper data on passed Item.');

        $item = $pool->getItem('base.one');
        $storedData = $item->get();
        static::assertEquals($this->data, $storedData, 'Pool->save() returns proper data on new Item instance.');

        static::assertTrue($pool->hasItem('base.one'), 'Pool->hasItem() returns true for item with stored data.');

        $pool->setNamespace('TestNamespace');
        $item = $pool->getItem('test.item');

        static::assertAttributeEquals('TestNamespace', 'namespace', $item, 'Pool sets Item namespace.');
    }

    public function testHasItem(): void
    {
        $pool = $this->getTestPool();
        static::assertFalse($pool->hasItem('base.one'), 'Pool->hasItem() returns false for item without stored data.');
        $item = $pool->getItem('base.one');
        $item->set($this->data);
        $pool->save($item);
        static::assertTrue($pool->hasItem('base.one'), 'Pool->hasItem() returns true for item with stored data.');
    }

    public function testCommit(): void
    {
        $pool = $this->getTestPool();
        static::assertTrue($pool->commit());
    }


    public function testGetItemInvalidKeyMissingNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $pool = $this->getTestPool();
        $pool->getItem('This/Test/Fail');
    }

    public function testGetItems(): void
    {
        $pool = $this->getTestPool();

        $keys = array_keys($this->multiData);

        $cacheIterator = $pool->getItems($keys);
        $keyData = $this->multiData;
        foreach ($cacheIterator as $key => $stash) {
            static::assertTrue($stash->isMiss(), 'new Cache in iterator is empty');
            $stash->set($keyData[$key])->save();
            unset($keyData[$key]);
        }
        static::assertCount(0, $keyData, 'all keys are accounted for the in cache iterator');

        $cacheIterator = $pool->getItems($keys);
        foreach ($cacheIterator as $key => $stash) {
            static::assertSame($key, $stash->getKey(), 'Item key is not equals key in iterator');
            $data = $stash->get($key);
            static::assertSame(
                $this->multiData[$key],
                $data,
                'data put into the pool comes back the same through iterators.',
            );
        }
    }

    public function testDeleteItems(): void
    {
        $pool = $this->getTestPool();

        $keys = array_keys($this->multiData);

        $cacheIterator = $pool->getItems($keys);
        $keyData = $this->multiData;
        foreach ($cacheIterator as $stash) {
            $key = $stash->getKey();
            static::assertTrue($stash->isMiss(), 'new Cache in iterator is empty');
            $stash->set($keyData[$key])->save();
            unset($keyData[$key]);
        }
        static::assertCount(0, $keyData, 'all keys are accounted for the in cache iterator');

        $cacheIterator = $pool->getItems($keys);
        foreach ($cacheIterator as $item) {
            $key = $item->getKey();
            $data = $item->get($key);
            static::assertSame(
                $this->multiData[$key],
                $data,
                'data put into the pool comes back the same through iterators.',
            );
        }

        static::assertTrue($pool->deleteItems($keys), 'deleteItems returns true.');
        $cacheIterator = $pool->getItems($keys);
        foreach ($cacheIterator as $item) {
            static::assertTrue($item->isMiss(), 'data cleared using deleteItems is removed from the cache.');
        }
    }

    public function testClearCache(): void
    {
        $pool = $this->getTestPool();

        $stash = $pool->getItem('base.one');
        $stash->set($this->data)->save();
        static::assertTrue($pool->clear(), 'clear returns true');

        $stash = $pool->getItem('base.one');
        static::assertNull($stash->get(), 'clear removes item');
        static::assertTrue($stash->isMiss(), 'clear causes cache miss');
    }

    public function testPurgeCache(): void
    {
        $pool = $this->getTestPool();

        $stash = $pool->getItem('base.one');
        $stash->set($this->data)->expiresAfter(-600)->save();
        static::assertTrue($pool->purge(), 'purge returns true');

        $stash = $pool->getItem('base.one');
        static::assertNull($stash->get(), 'purge removes item');
        static::assertTrue($stash->isMiss(), 'purge causes cache miss');
    }

    public function testNamespacing(): void
    {
        $pool = $this->getTestPool();

        static::assertAttributeEquals(
            null,
            'namespace',
            $pool,
            'Namespace starts as empty string.',
        );
        $pool->setNamespace('TestSpace');
        static::assertAttributeEquals('TestSpace', 'namespace', $pool, 'setNamespace sets the namespace.');
        static::assertEquals('TestSpace', $pool->getNamespace(), 'getNamespace returns current namespace.');

        $pool->setNamespace();
        static::assertAttributeEquals(null, 'namespace', $pool, 'setNamespace() empties namespace.');
        static::assertNull($pool->getNamespace(), 'getNamespace returns NULL when no namespace is set.');
    }

    public function testInvalidNamespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $pool = $this->getTestPool();
        $pool->setNamespace('!@#$%^&*(');
    }

    public function testSetLogger(): void
    {
        $pool = $this->getTestPool();

        $driver = new DriverExceptionStub();
        $pool->setDriver($driver);

        $logger = new TestLogger();
        $pool->setLogger($logger);

        static::assertAttributeInstanceOf(
            TestLogger::class,
            'logger',
            $pool,
            'setLogger injects logger into Pool.',
        );

        $item = $pool->getItem('testItem');
        static::assertAttributeInstanceOf(
            TestLogger::class,
            'logger',
            $item,
            'setLogger injects logger into Pool.',
        );
    }

    public function testLoggerClear(): void
    {
        $pool = $this->getTestPool();

        $driver = new DriverExceptionStub();
        $pool->setDriver($driver);

        $logger = new TestLogger();
        $pool->setLogger($logger);

        // Trigger logging.
        $pool->clear();

        $logEntry = end($logger->records);
        static::assertInstanceOf(
            TestException::class,
            $logEntry['context']['exception'],
            'Logger was passed exception in event context.',
        );

        static::assertTrue(strlen($logEntry['message']) > 0, 'Logger message set after "get" exception.');
        static::assertSame('critical', $logEntry['level'], 'Exceptions logged as critical.');
    }

    public function testLoggerPurge(): void
    {
        $pool = $this->getTestPool();

        $driver = new DriverExceptionStub();
        $pool->setDriver($driver);

        $logger = new TestLogger();
        $pool->setLogger($logger);

        // Trigger logging.
        $pool->purge();

        $logEntry = end($logger->records);
        static::assertInstanceOf(
            TestException::class,
            $logEntry['context']['exception'],
            'Logger was passed exception in event context.',
        );
        static::assertTrue(strlen($logEntry['message']) > 0, 'Logger message set after "set" exception.');
        static::assertSame('critical', $logEntry['level'], 'Exceptions logged as critical.');
    }

    public function testSetInvalidationMethod(): void
    {
        $pool = $this->getTestPool();

        $pool->setInvalidationMethod(InvalidationMethod::Old, 'test1', 'test2');
        $item = $pool->getItem('test.item');

        static::assertAttributeEquals(
            InvalidationMethod::Old,
            'invalidationMethod',
            $item,
            'Pool sets Item invalidation constant.',
        );
        static::assertAttributeEquals(
            'test1',
            'invalidationArg1',
            $item,
            'Pool sets Item invalidation argument 1.',
        );
        static::assertAttributeEquals(
            'test2',
            'invalidationArg2',
            $item,
            'Pool sets Item invalidation argument 2.',
        );
    }

    protected function getTestPool(): Pool
    {
        return new $this->poolClass();
    }
}
