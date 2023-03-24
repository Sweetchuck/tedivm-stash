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
use Stash\Driver\Ephemeral;
use Stash\Test\Helper\DriverExceptionStub;
use Stash\Test\Helper\PoolGetDriverStub;
use Stash\Item;
use Stash\Test\Helper\TestException;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class ItemLoggerTestBase extends TestBase
{
    protected function getItem(array $key, bool $exceptionDriver = false): Item
    {
        $fullDriver = $exceptionDriver ?
            DriverExceptionStub::class
            : Ephemeral::class;

        $item = new Item();

        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver(new $fullDriver());
        $item->setPool($poolStub);
        $item->setKey($key);

        return $item;
    }

    public function testSetLogger(): void
    {
        $item = $this->getItem(['path', 'to', 'constructor']);

        $logger = new TestLogger();
        $item->setLogger($logger);
        static::assertAttributeInstanceOf(
            TestLogger::class,
            'logger',
            $item,
            'setLogger injects logger into Item.',
        );
    }

    public function testGet(): void
    {
        $logger = new TestLogger();

        $item = $this->getItem(['path', 'to', 'get'], true);
        $item->setLogger($logger);

        // Trigger logging.
        $item->get('test_key');

        $logEntry = end($logger->records);

        static::assertInstanceOf(
            TestException::class,
            $logEntry['context']['exception'],
            'Logger was passed exception in event context.',
        );

        static::assertTrue(strlen($logEntry['message']) > 0, 'Logger message set after "get" exception.');
        static::assertSame('critical', $logEntry['level'], 'Exceptions logged as critical.');
    }

    public function testSet(): void
    {
        $logger = new TestLogger();

        $item = $this->getItem(['path', 'to', 'set'], true);
        $item->setLogger($logger);

        // Trigger logging.
        $item->set('test_key')->save();

        $logEntry = end($logger->records);

        static::assertInstanceOf(
            TestException::class,
            $logEntry['context']['exception'],
            'Logger was passed exception in event context.',
        );
        static::assertTrue(strlen($logEntry['message']) > 0, 'Logger message set after "set" exception.');
        static::assertSame('critical', $logEntry['level'], 'Exceptions logged as critical.');
    }

    public function testClear()
    {
        $logger = new TestLogger();

        $item = $this->getItem(['path', 'to', 'clear'], true);
        $item->setLogger($logger);

        // Trigger logging.
        $item->clear();

        $logEntry = end($logger->records);
        static::assertInstanceOf(
            TestException::class,
            $logEntry['context']['exception'],
            'Logger was passed exception in event context.',
        );
        static::assertTrue(strlen($logEntry['message']) > 0, 'Logger message set after "clear" exception.');
        static::assertSame('critical', $logEntry['level'], 'Exceptions logged as critical.');
    }
}
