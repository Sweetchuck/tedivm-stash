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

use Stash\Driver\Memcache;
use Stash\Test\Helper\MemcacheSetUp;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class MemcachedTest extends MemcacheTest
{
    use MemcacheSetUp;

    protected string $extension = 'memcached';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $memcachedOptions = array('hash' => 'default',
                                  'distribution' => 'modula',
                                  'serializer' => 'php',
                                  'buffer_writes' => false,
                                  'connect_timeout' => 500,
                                  'prefix_key' => 'cheese'
        );

        return array_merge($options, $memcachedOptions);
    }

    public function testIsAvailable()
    {
        $this->assertTrue(\Stash\Driver\Sub\Memcached::isAvailable());
    }

    public function testSetHashException()
    {
        $this->expectException('RuntimeException');
        $options = array();
        $options['servers'][] = array('127.0.0.1', '11211', '50');
        $options['servers'][] = array('127.0.0.1', '11211');
        $options['hash'] = 'InvalidOption';
        $driver = new Memcache($options);
    }

    public function testSetDistributionException()
    {
        $this->expectException('RuntimeException');
        $options = array();
        $options['servers'][] = array('127.0.0.1', '11211', '50');
        $options['servers'][] = array('127.0.0.1', '11211');
        $options['distribution'] = 'InvalidOption';
        $driver = new Memcache($options);
    }

    public function testSetSerializerException()
    {
        $this->expectException('RuntimeException');
        $options = array();
        $options['servers'][] = array('127.0.0.1', '11211', '50');
        $options['servers'][] = array('127.0.0.1', '11211');
        $options['serializer'] = 'InvalidOption';
        $driver = new Memcache($options);
    }

    public function testSetNumberedValueException()
    {
        $this->expectException('RuntimeException');
        $options = array();
        $options['servers'][] = array('127.0.0.1', '11211', '50');
        $options['servers'][] = array('127.0.0.1', '11211');
        $options['connect_timeout'] = 'InvalidOption';
        $driver = new Memcache($options);
    }

    public function testSetBooleanValueException()
    {
        $this->expectException('RuntimeException');
        $options = array();
        $options['servers'][] = array('127.0.0.1', '11211', '50');
        $options['servers'][] = array('127.0.0.1', '11211');
        $options['cache_lookups'] = 'InvalidOption';
        $driver = new Memcache($options);
    }
}
