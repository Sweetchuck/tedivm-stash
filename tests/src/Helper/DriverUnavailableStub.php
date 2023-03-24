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

namespace Stash\Test\Helper;

use Stash;
use Stash\Driver\AbstractDriver;

/**
 * DriverExceptionStub is used for testing how Stash reacts to thrown errors. Every function but the constructor throws
 * an exception.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @codeCoverageIgnore
 */
class DriverUnavailableStub extends AbstractDriver
{

    public static function isAvailable(): bool
    {
        return false;
    }

    protected array $store = [];

    public function getData(array $key): array
    {
        throw new TestException('Test exception for ' . __FUNCTION__ . ' call');
    }

    protected function getKeyIndex($key)
    {
        throw new TestException('Test exception for ' . __FUNCTION__ . ' call');
    }

    public function storeData(array $key, mixed $data, int $expiration): bool
    {
        throw new TestException('Test exception for ' . __FUNCTION__ . ' call');
    }

    public function clear(?array $key = null): bool
    {
        throw new TestException('Test exception for ' . __FUNCTION__ . ' call');
    }

    public function purge(): bool
    {
        throw new TestException('Test exception for ' . __FUNCTION__ . ' call');
    }

    public function canEnable()
    {
        return false;
    }
}
