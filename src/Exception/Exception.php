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

namespace Stash\Exception;

use \Psr\Cache\CacheException;

/**
 * Interface for the Stash exceptions.
 *
 * Interface Exception
 * @package Stash\Exception
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
interface Exception extends CacheException
{
}
