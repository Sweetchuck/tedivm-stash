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

namespace Stash;

/**
 * Contains a grouping of invalidation flags. These are passed to the Item
 * class to tell it which method to use when dealing with stale data.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
enum InvalidationMethod: int
{
    /**
     * Does nothing special when data is stale,
     * it simply returns true for "isMiss" and null for get.
     */
    case None = 0;

    /**
     * If the old data is present then get will return it.
     * isMiss will return false while another Item is being populated.
     */
    case Old = 1;

    /**
     * When one Item is regenerating the cache other items will returns a
     * supplied value and isMiss will return false.
     */
    case Value = 2;

    /**
     * When one Item is regenerating the cache other items will sleep and wait for it.
     * If the wait times out "isMiss" will return true.
     */
    case Sleep = 3;

    /**
     * When the Item is close to it's expiration one is chosen to "miss"
     * so the Item's value can be regenerated before it actually expires.
     * While one item is regenerating the rest are still using the cached data
     * as it is still good.
     */
    case Precompute = 4;
}
