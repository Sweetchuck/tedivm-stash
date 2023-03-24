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

use PHPUnit\Framework\TestCase;
use Stash\Test\Helper\DriverUnavailableStub;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class UnavailableDriverTest extends TestCase
{
    public function testUnavailableDriver()
    {
        $this->expectException(\RuntimeException::class);
        new DriverUnavailableStub();
    }
}
