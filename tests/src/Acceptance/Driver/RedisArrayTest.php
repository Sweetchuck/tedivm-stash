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

use Stash\Driver\Redis;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\Driver\Redis
 */
class RedisArrayTest extends RedisTest
{
    protected string $driverClass = Redis::class;

    protected bool $persistence = true;

    protected function setUp() : void
    {
        parent::setUp();

        if (!$this->setup) {
            $this->startTime = time();
            $this->expiration = $this->startTime + 3600;

            $this
                ->setUpCheckServer($this->servers['url1'], true)
                ->setUpCheckServer($this->servers['url2'], true);

            if (!$this->getFreshDriver()) {
                static::markTestSkipped('Driver class unsuited for current environment');
            }

            $this->data['object'] = new \stdClass();
            $this->data['large_string'] = str_repeat('apples', (int) ceil(200000 / 6));
        }
    }

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        $options['servers'] = [
            [
                'server' => $this->servers['url1']['host'],
                'port' => $this->servers['url1']['port'],
                'ttl' => 0.1,
            ],
            [
                'server' => $this->servers['url2']['host'],
                'port' => $this->servers['url2']['port'],
                'ttl' => 0.1,
            ],
        ];

        return $options;
    }

    public function testItShouldConstructARedisArray(): void
    {
        $driver = $this->getFreshDriver();
        $class = new \ReflectionClass($driver);
        $redisProperty = $class->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisArray = $redisProperty->getValue($driver);

        static::assertInstanceOf(\RedisArray::class, $redisArray);
    }

    public function testItShouldPassOptionsToRedisArray(): void
    {
        $redisArrayOptions = [
            'previous' => 'something',
            'function' => function ($key) {
                return $key;
            },
            'distributor' => function ($key) {
                return 0;
            },
            'index' => 'something',
            'autorehash' => 'something',
            'pconnect' => 'something',
            'retry_interval' => 'something',
            'lazy_connect' => 'something',
            'connect_timeout' => 'something',
        ];

        $driverOptions = array_merge(
            $this->getOptions(),
            $redisArrayOptions,
        );

        $driver = $this->getFreshDriver($driverOptions);
        $class = new \ReflectionClass($driver);
        $redisProperty = $class->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisArray = $redisProperty->getValue($driver);
        static::assertInstanceOf(\RedisArray::class, $redisArray);
        static::assertSame(2, count($redisArray->_hosts()));
    }
}
