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

use Stash\Driver\Apc;
use Stash\Interfaces\DriverInterface;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class ApcTest extends DriverTestBase
{
    protected string $driverClass = Apc::class;

    protected bool $persistence = true;

    public function testSetOptions(): DriverInterface
    {
        $driverType = $this->driverClass;
        $options = $this->getOptions();
        $options['namespace'] = 'namespace_test';
        $options['ttl'] = 15;
        $driver = new $driverType($options);

        static::assertAttributeEquals('namespace_test', 'apcNamespace', $driver, 'APC is setting supplied namespace.');
        static::assertAttributeEquals(15, 'ttl', $driver, 'APC is setting supplied ttl.');

        return parent::testSetOptions();
    }
}
