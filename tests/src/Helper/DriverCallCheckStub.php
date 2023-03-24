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
 */
class DriverCallCheckStub extends AbstractDriver
{

    public static function isAvailable(): bool
    {
        return getenv('STASH_TESTING') === '1';
    }

    protected array $store = [];
    protected bool $wasCalled = false;

    public function getData(array $key): array
    {
        $this->wasCalled = true;

        return [];
    }

    protected function getKeyIndex($key)
    {
        $this->wasCalled = true;
    }

    public function storeData(array $key, mixed $data, int $expiration): bool
    {
        $this->wasCalled = true;

        return true;
    }

    public function clear(?array $key = null): bool
    {
        $this->wasCalled = true;

        return true;
    }

    public function purge(): bool
    {
        $this->wasCalled = true;

        return true;
    }

    public function wasCalled(): bool
    {
        return $this->wasCalled;
    }

    public function canEnable(): bool
    {
        return getenv('STASH_TESTING') === '1';
    }
}
