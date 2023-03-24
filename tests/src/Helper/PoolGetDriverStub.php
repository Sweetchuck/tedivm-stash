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

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Stash\Interfaces\DriverInterface;
use Stash\Interfaces\PoolInterface;
use Stash\InvalidationMethod;
use Stash\Item;

/**
 *
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class PoolGetDriverStub implements PoolInterface
{
    protected DriverInterface $driver;

    /**
     * {@inheritdoc}
     */
    public function setDriver(DriverInterface $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    public function setItemClass(string $class): static
    {
        return $this;
    }

    public function getItem(string $key): CacheItemInterface
    {
        return new Item();
    }

    public function getItems(array $keys = []): iterable
    {
        return [];
    }

    public function clear(): bool
    {
        return false;
    }

    public function purge(): bool
    {
        return false;
    }

    public function setNamespace(?string $namespace = null): static
    {
        return $this;
    }

    public function getNamespace(): ?string
    {
        return null;
    }

    public function setLogger(LoggerInterface $logger): void
    {
    }

    public function setInvalidationMethod(
        InvalidationMethod $invalidationMethod = InvalidationMethod::Precompute,
        mixed $arg1 = null,
        mixed $arg2 = null,
    ): static {
        return $this;
    }

    public function hasItem(string $key): bool
    {
        return false;
    }

    public function commit(): bool
    {
        return false;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return false;
    }

    public function save(CacheItemInterface $item): bool
    {
        return false;
    }

    public function deleteItems(array $keys): bool
    {
        return false;
    }

    public function deleteItem(string $key): bool
    {
        return false;
    }
}
