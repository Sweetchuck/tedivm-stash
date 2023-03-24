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

namespace Stash\Interfaces;

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareInterface;
use Stash\InvalidationMethod;

interface ItemInterface extends CacheItemInterface, LoggerAwareInterface
{
    /**
     * Sets the Parent Pool for the Item class to use.
     *
     * Typically called by Pool directly, and *must* be called before running caching functions.
     */
    public function setPool(PoolInterface $pool): static;

    /**
     * Takes and sets the key and namespace.
     *
     * Typically called by Pool directly, and *must* be called before running caching functions.
     *
     * @param string[] $key
     */
    public function setKey(array $key, ?string $namespace = null): static;

    /**
     * This disables any IO operations by this object, effectively preventing
     * the reading and writing of new data.
     */
    public function disable(): bool;

    /**
     * Clears the current Item. If hierarchical or "stackable" caching is being
     * used this function will also remove children Items.
     */
    public function clear(): bool;

    /**
     * Returns true if the cached item needs to be refreshed.
     */
    public function isMiss(): bool;

    /**
     * Enables stampede protection by marking this specific instance of the Item
     * as the one regenerating the cache.
     */
    public function lock(?int $ttl = null): bool;

    /**
     * Extends the expiration on the current cached item. For some engines this
     * can be faster than storing the item again.
     */
    public function extend(null|int|\DateInterval $ttl = null): bool;

    /**
     * Return true if caching is disabled.
     */
    public function isDisabled(): bool;

    /**
     * Returns the record's creation time or false if it isn't set
     */
    public function getCreation(): ?\DateTimeInterface;

    /**
     * Returns the record's expiration timestamp or false if no expiration timestamp is set
     */
    public function getExpiration(): ?\DateTimeInterface;

    /**
    * Sets the expiration based off a an integer, date interval, or date
    */
    public function setTTL(null|int|\DateInterval|\DateTimeInterface $ttl = null): static;

    /**
    * Set the cache invalidation method for this item.
    */
    public function setInvalidationMethod(
        InvalidationMethod $invalidation,
        mixed $arg1 = null,
        mixed $arg2 = null,
    ): static;

    /**
    * Persists the Item's value to the backend storage.
    */
    public function save(): bool;
}
